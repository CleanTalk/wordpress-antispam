<?php

use Cleantalk\ApbctWP\API;
use Cleantalk\ApbctWP\Cron;
use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\Firewall\AntiCrawler;
use Cleantalk\ApbctWP\Firewall\SFW;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\UpdatePlugin\DbTablesCreator;
use Cleantalk\Common\Schema;

global $apbct, $wpdb, $pagenow;

/**
 *  SFW UPDATE INNER FUNCTIONS *
 */
/**
 * Not queue stage. Write a file content to the SFW temp common/personal table depends on type of SFW load.
 * @param $file_path
 * @param $direction
 * @return int|string[]
 */
function apbct_sfw_update__process_file($file_path, $direction = 'common')
{
    if ( ! file_exists($file_path) ) {
        return array('error' => 'PROCESS FILE: ' . $file_path . ' is not exists.');
    }

    $table_name = $direction === 'common'
        ? SFW::getSFWCommonTableName() . '_temp'
        : APBCT_TBL_FIREWALL_DATA_PERSONAL . '_temp';

    $result = SFW::updateWriteToDb(
        DB::getInstance(),
        $table_name,
        $file_path
    );

    if ( ! empty($result['error']) ) {
        return array('error' => 'PROCESS FILE: ' . $result['error']);
    }

    if ( ! is_int($result) ) {
        return array('error' => 'PROCESS FILE: WRONG RESPONSE FROM update__write_to_db');
    }

    return $result;
}

/**
 * Not queue stage. Write a file content to the Anti-Crawler table.
 * @param $file_path
 * @return int|string[]
 */
function apbct_sfw_update__process_ua($file_path)
{
    $result = AntiCrawler::update($file_path);

    if ( ! empty($result['error']) ) {
        return array('error' => 'UPDATING UA LIST: ' . $result['error']);
    }

    if ( ! is_int($result) ) {
        return array('error' => 'UPDATING UA LIST: : WRONG_RESPONSE AntiCrawler::update');
    }

    return $result;
}

/**
 * Not queue stage. Proceed checking of upload integrity.
 * @param $file_path
 * @param $direction
 * @return string[]|void
 */
function apbct_sfw_update__process_ck($file_path, $direction = 'common')
{
    global $apbct;

    // Save expected_networks_count and expected_ua_count if exists
    $file_content = file_get_contents($file_path);

    if ( function_exists('gzdecode') ) {
        $unzipped_content = gzdecode($file_content);

        if ( $unzipped_content !== false ) {
            $file_ck_url__data = Helper::bufferParseCsv($unzipped_content);

            if ( ! empty($file_ck_url__data['error']) ) {
                return array('error' => 'GET EXPECTED RECORDS COUNT DATA: ' . $file_ck_url__data['error']);
            }

            $expected_networks_count = 0;
            $expected_ua_count       = 0;

            foreach ( $file_ck_url__data as $value ) {
                if ( trim($value[0], '"') === 'networks_count' ) {
                    $expected_networks_count = $value[1];
                }
                if ( trim($value[0], '"') === 'ua_count' ) {
                    $expected_ua_count = $value[1];
                }
            }

            if ( $direction === 'common' ) {
                $apbct->fw_stats['expected_networks_count'] = $expected_networks_count;
                $apbct->fw_stats['expected_ua_count']       = $expected_ua_count;
            } else {
                $apbct->fw_stats['expected_networks_count_personal'] = $expected_networks_count;
                $apbct->fw_stats['expected_ua_count_personal']       = $expected_ua_count;
            }

            $apbct->save('fw_stats');

            if ( file_exists($file_path) ) {
                unlink($file_path);
            }
        } else {
            return array('error' => 'Can not unpack datafile');
        }
    } else {
        return array('error' => 'Function gzdecode not exists. Please update your PHP at least to version 5.4 ');
    }
}

/**
 * Not a queue stage. Get direction files urls and their url_id depends on type of SFW load.
 * @param $type
 * @return array
 * @throws Exception
 */
function apbct_sfw_update__get_direction_urls_of_type($type = 'common')
{
    global $apbct;

    $type_sign = $type === 'common' ? 1 : 0;

    //get main file link
    $api_result = API::methodGet2sBlacklistsDb($apbct->api_key, 'multifiles', '3_2', $type_sign);

    // check if no api error
    if ( !empty($api_result['error']) ) {
        throw new \Exception($api_result['error']);
    }

    // check if main file url persists
    if ( empty($api_result['file_url']) ) {
        throw new \Exception('DIRECTION_FILE_URL_IS_EMPTY');
    }

    // get direction files from main file link
    $file_urls = Helper::httpGetDataFromRemoteGzAndParseCsv($api_result['file_url']);
    if ( !empty($file_urls['error']) ) {
        throw new \Exception($file_urls['error']);
    }
    preg_match('/bl_list_(.+)\.multifiles/m', $api_result['file_url'], $url_id);

    // check if direction pregmatch found
    if ( !isset($url_id[1]) ) {
        throw new \Exception('CANNOT_GET_DIRECTION_URL_ID');
    }

    // add user agents url
    if ( !empty($api_result['file_ua_url']) ) {
        $file_urls[][0] = $api_result['file_ua_url'];
    }

    // add cheking file url
    if ( !empty($api_result['file_ck_url']) ) {
        $file_urls[][0] = $api_result['file_ck_url'];
    }

    return array(
        //direction files
        'files_urls' => $file_urls,
        //common/personal url_id
        'url_id' => $url_id[1]
    );
}

/**
 * Not queue stage. Delete origin tables and remove temporary tables depends on type of SFW load after renaming.
 * @param $sfw_load_type
 * @return true
 * @throws Exception
 */
function apbct_sfw_update__remove_and_rename_sfw_tables($sfw_load_type)
{
    if ( $sfw_load_type === 'all' ) {
        //common table delete
        $result_deletion = SFW::dataTablesDelete(DB::getInstance(), SFW::getSFWCommonTableName());
        if ( !empty($result_deletion['error']) ) {
            throw new \Exception('SFW_COMMON_TABLE_DELETION_ERROR');
        }
        //common table rename
        $result_renaming = SFW::renameDataTablesFromTempToMain(DB::getInstance(), SFW::getSFWCommonTableName());
        if ( !empty($result_renaming['error']) ) {
            throw new \Exception('SFW_COMMON_TABLE_RENAME_ERROR');
        }

        //personal table delete
        $result_deletion = SFW::dataTablesDelete(DB::getInstance(), APBCT_TBL_FIREWALL_DATA_PERSONAL);
        if ( !empty($result_deletion['error']) ) {
            throw new \Exception('SFW_PERSONAL_TABLE_DELETION_ERROR');
        }
        //personal table rename
        $result_renaming = SFW::renameDataTablesFromTempToMain(DB::getInstance(), APBCT_TBL_FIREWALL_DATA_PERSONAL);
        if ( !empty($result_renaming['error']) ) {
            throw new \Exception('SFW_PERSONAL_TABLE_RENAME_ERROR');
        }
    } elseif ( $sfw_load_type === 'personal' ) {
        //personal table delete
        $result_deletion = SFW::dataTablesDelete(DB::getInstance(), APBCT_TBL_FIREWALL_DATA_PERSONAL);
        if ( !empty($result_deletion['error']) ) {
            throw new \Exception('SFW_PERSONAL_TABLE_DELETION_ERROR');
        }
        //personal table rename
        $result_renaming = SFW::renameDataTablesFromTempToMain(DB::getInstance(), APBCT_TBL_FIREWALL_DATA_PERSONAL);
        if ( !empty($result_renaming['error']) ) {
            throw new \Exception('SFW_PERSONAL_TABLE_RENAME_ERROR');
        }
    } elseif ( $sfw_load_type === 'common' ) {
        //common table delete
        $result_deletion = SFW::dataTablesDelete(DB::getInstance(), SFW::getSFWCommonTableName());
        if ( !empty($result_deletion['error']) ) {
            throw new \Exception('SFW_COMMON_TABLE_DELETION_ERROR');
        }
        //common table rename
        $result_renaming = SFW::renameDataTablesFromTempToMain(DB::getInstance(), SFW::getSFWCommonTableName());
        if ( !empty($result_renaming['error']) ) {
            throw new \Exception('SFW_COMMON_TABLE_RENAME_ERROR');
        }
    }
    return true;
}

/**
 * Not queue stage. Check tables integrity depends on type of SFW load before renaming.
 * @param $sfw_load_type
 * @return string[]|true
 */
function apbct_sfw_update__check_tables_integrity_before_renaming($sfw_load_type)
{
    if ( $sfw_load_type === 'all' ) {
        if ( !DB::getInstance()->isTableExists(SFW::getSFWCommonTableName()) ) {
            return array('error' => 'Error while completing data: SFW main table does not exist.');
        }
        if ( !DB::getInstance()->isTableExists(SFW::getSFWCommonTableName() . '_temp') ) {
            return array('error' => 'Error while completing data: SFW temp table does not exist.');
        }
        if ( ! DB::getInstance()->isTableExists(APBCT_TBL_FIREWALL_DATA_PERSONAL) ) {
            return array('error' => 'Error while completing data: SFW_PERSONAL main table does not exist.');
        }

        if ( ! DB::getInstance()->isTableExists(APBCT_TBL_FIREWALL_DATA_PERSONAL . '_temp') ) {
            return array('error' => 'Error while completing data: SFW_PERSONAL temp table does not exist.');
        }
    } elseif ( $sfw_load_type === 'personal' ) {
        //personal tables
        if ( ! DB::getInstance()->isTableExists(APBCT_TBL_FIREWALL_DATA_PERSONAL) ) {
            return array('error' => 'Error while completing data: SFW_PERSONAL main table does not exist.');
        }

        if ( ! DB::getInstance()->isTableExists(APBCT_TBL_FIREWALL_DATA_PERSONAL . '_temp') ) {
            return array('error' => 'Error while completing data: SFW_PERSONAL temp table does not exist.');
        }
    } elseif ( $sfw_load_type === 'common' ) {
        //common tables
        if ( !DB::getInstance()->isTableExists(SFW::getSFWCommonTableName()) ) {
            return array('error' => 'Error while completing data: SFW main table does not exist.');
        }

        if ( !DB::getInstance()->isTableExists(SFW::getSFWCommonTableName() . '_temp') ) {
            return array('error' => 'Error while completing data: SFW temp table does not exist.');
        }
    }
    return true;
}

/**
 * Not queue stage. Check tables integrity depends on type of SFW load after renaming.
 * @param $sfw_load_type
 * @return true
 * @throws Exception
 */
function apbct_sfw_update__check_tables_integrity_after_renaming($sfw_load_type)
{
    if ( $sfw_load_type === 'all' ) {
        //personal tables
        if ( ! DB::getInstance()->isTableExists(APBCT_TBL_FIREWALL_DATA_PERSONAL) ) {
            throw new \Exception('Error while checking data: SFW main table does not exist.');
        }
        //common tables
        if ( ! DB::getInstance()->isTableExists(SFW::getSFWCommonTableName()) ) {
            throw new \Exception('Error while checking data: SFW main table does not exist.');
        }
    } elseif ( $sfw_load_type === 'personal' ) {
        //personal tables
        if ( ! DB::getInstance()->isTableExists(APBCT_TBL_FIREWALL_DATA_PERSONAL) ) {
            throw new \Exception('Error while checking data: SFW main table does not exist.');
        }
    } elseif ( $sfw_load_type === 'common' ) {
        //common tables
        if ( ! DB::getInstance()->isTableExists(SFW::getSFWCommonTableName()) ) {
            throw new \Exception('Error while checking data: SFW main table does not exist.');
        }
    }
    return true;
}

function apbct_sfw_update__is_in_progress()
{
    $queue = new \Cleantalk\ApbctWP\Queue();

    return $queue->isQueueInProgress();
}

function apbct_sfw_update__prepare_upd_dir()
{
    global $apbct;

    $dir_name = $apbct->fw_stats['updating_folder'];

    if ( $dir_name === '' ) {
        return array('error' => 'FW dir can not be blank.');
    }

    if ( ! is_dir($dir_name) ) {
        if ( ! mkdir($dir_name) && ! is_dir($dir_name) ) {
            return array('error' => 'Can not to make FW dir.');
        }
    } else {
        $files = glob($dir_name . '/*');
        if ( $files === false ) {
            return array('error' => 'Can not find FW files.');
        }
        if ( count($files) === 0 ) {
            return (bool)file_put_contents($dir_name . 'index.php', '<?php' . PHP_EOL);
        }
        foreach ( $files as $file ) {
            if ( is_file($file) && unlink($file) === false ) {
                return array('error' => 'Can not delete the FW file: ' . $file);
            }
        }
    }

    return (bool)file_put_contents($dir_name . 'index.php', '<?php');
}

function apbct_sfw_update__remove_upd_folder($dir_name)
{
    if ( is_dir($dir_name) ) {
        $files = glob($dir_name . '/*');

        if ( ! empty($files) ) {
            foreach ( $files as $file ) {
                if ( is_file($file) ) {
                    unlink($file);
                }
                if ( is_dir($file) ) {
                    apbct_sfw_update__remove_upd_folder($file);
                }
            }
        }

        //add more paths if some strange files has been detected
        $non_cleantalk_files_filepaths = array(
            $dir_name . '.last.jpegoptim'
        );

        foreach ( $non_cleantalk_files_filepaths as $filepath ) {
            if ( file_exists($filepath) && is_file($filepath) && !is_writable($filepath) ) {
                unlink($filepath);
            }
        }

        rmdir($dir_name);
    }
}

function apbct_sfw_update__checker()
{
    $queue = new \Cleantalk\ApbctWP\Queue();
    if ( count($queue->queue['stages']) ) {
        foreach ( $queue->queue['stages'] as $stage ) {
            if ( $stage['status'] === 'NULL' ) {
                return apbct_sfw_update__worker(true);
            }
        }
    }

    return true;
}

function apbct_sfw_direct_update()
{
    global $apbct;

    $api_key = $apbct->api_key;

    // The Access key is empty
    if ( empty($api_key) ) {
        return array('error' => 'SFW DIRECT UPDATE: KEY_IS_EMPTY');
    }

    // Getting BL
    $result = SFW::directUpdateGetBlackLists($api_key);

    if ( empty($result['error']) ) {
        $blacklists = $result['blacklist'];
        $useragents = $result['useragents'];
        $bl_count   = $result['bl_count'];
        $ua_count   = $result['ua_count'];

        if ( isset($bl_count, $ua_count) ) {
            $apbct->fw_stats['expected_networks_count'] = $bl_count;
            $apbct->fw_stats['expected_ua_count']       = $ua_count;
            $apbct->save('fw_stats');
        }

        // Preparing database infrastructure
        // @ToDo need to implement returning result of the Activator::createTables work.
        $db_tables_creator = new DbTablesCreator();
        $table_name = $apbct->db_prefix . Schema::getSchemaTablePrefix() . 'sfw';
        $db_tables_creator->createTable($table_name);

        $result__creating_tmp_table = SFW::createTempTables(DB::getInstance(), APBCT_TBL_FIREWALL_DATA);
        if ( ! empty($result__creating_tmp_table['error']) ) {
            return array('error' => 'DIRECT UPDATING CREATE TMP TABLE: ' . $result__creating_tmp_table['error']);
        }

        /**
         * UPDATING UA LIST
         */
        if ( $useragents && ($apbct->settings['sfw__anti_crawler'] || $apbct->settings['sfw__anti_flood']) ) {
            $ua_result = AntiCrawler::directUpdate($useragents);

            if ( ! empty($ua_result['error']) ) {
                return array('error' => 'DIRECT UPDATING UA LIST: ' . $result['error']);
            }

            if ( ! is_int($ua_result) ) {
                return array('error' => 'DIRECT UPDATING UA LIST: : WRONG_RESPONSE AntiCrawler::directUpdate');
            }
        }

        /**
         * UPDATING BLACK LIST
         */
        $upd_result = SFW::directUpdate(
            DB::getInstance(),
            APBCT_TBL_FIREWALL_DATA . '_temp',
            $blacklists
        );

        if ( ! empty($upd_result['error']) ) {
            return array('error' => 'DIRECT UPDATING BLACK LIST: ' . $upd_result['error']);
        }

        if ( ! is_int($upd_result) ) {
            return array('error' => 'DIRECT UPDATING BLACK LIST: WRONG RESPONSE FROM SFW::directUpdate');
        }

        /**
         * UPDATING EXCLUSIONS LIST
         */
        $excl_result = apbct_sfw_update__process_exclusions();

        if ( ! empty($excl_result['error']) ) {
            return array('error' => 'DIRECT UPDATING EXCLUSIONS: ' . $excl_result['error']);
        }

        /**
         * DELETING AND RENAMING THE TABLES
         */
        $rename_tables_res = apbct_sfw_update__end_of_update__renaming_tables();
        if ( ! empty($rename_tables_res['error']) ) {
            return array('error' => 'DIRECT UPDATING BLACK LIST: ' . $rename_tables_res['error']);
        }

        /**
         * CHECKING THE UPDATE
         */
        $check_data_res = apbct_sfw_update__end_of_update__checking_data();
        if ( ! empty($check_data_res['error']) ) {
            return array('error' => 'DIRECT UPDATING BLACK LIST: ' . $check_data_res['error']);
        }

        /**
         * WRITE UPDATING STATS
         */
        apbct_sfw_update__end_of_update__updating_stats(true);

        /**
         * END OF UPDATE
         */
        return apbct_sfw_update__end_of_update();
    }

    return $result;
}

function apbct_sfw_update__cleanData()
{
    global $apbct;

    SFW::dataTablesDelete(DB::getInstance(), SFW::getSFWCommonTableName() . '_temp');
    SFW::dataTablesDelete(DB::getInstance(), APBCT_TBL_FIREWALL_DATA_PERSONAL . '_temp');

    $apbct->fw_stats['firewall_update_percent'] = 0;
    $apbct->fw_stats['firewall_updating_id']    = null;
    $apbct->save('fw_stats');
}

function apbct_sfw_update__fallback()
{
    global $apbct;

    /**
     * Remove the upd folder
     */
    if ( $apbct->fw_stats['updating_folder'] ) {
        apbct_sfw_update__remove_upd_folder($apbct->fw_stats['updating_folder']);
    }

    /**
     * Remove SFW updating checker cron-task
     */
    $cron = new Cron();
    $cron->removeTask('sfw_update_checker');
    $cron->updateTask('sfw_update', 'apbct_sfw_update__init', $apbct->stats['sfw']['update_period']);

    /**
     * Remove _temp table
     */
    apbct_sfw_update__cleanData();

    /**
     * Create SFW table if not exists
     */
    apbct_sfw_update__create_tables();
}
