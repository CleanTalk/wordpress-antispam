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

    $apbct->fw_stats['direct_update_log'] = null;
    $apbct->save('fw_stats', true, false);

    $direct_update_log = array();

    try {
        $api_key = $apbct->api_key;

        // The Access key is empty
        if ( empty($api_key) ) {
            throw new \Exception('SFW DIRECT UPDATE: KEY_IS_EMPTY');
        }

        // Getting BL
        $load_type = isset($apbct->data['sfw_load_type']) ? $apbct->data['sfw_load_type'] : 'all';
        if ( $load_type === 'all' ) {
            $get_multifiles_result = apbct_sfw_update__get_multifiles_all();
        } else {
            $get_multifiles_params['type'] = $apbct->fw_stats['load_type'];
            $get_multifiles_params['do_return_urls'] = false;
            $get_multifiles_result = apbct_sfw_update__get_multifiles_of_type($get_multifiles_params);
        }
        $direct_update_log['apbct_sfw_get_multifiles'] = $get_multifiles_result;
        if (!empty($get_multifiles_result['error'])) {
            throw new \Exception($get_multifiles_result['error']);
        }

        //download files
        $download_files_result = apbct_sfw_update__download_files($get_multifiles_result['next_stage']['args'], true);
        $direct_update_log['apbct_sfw_update__create_tables'] = $download_files_result;
        if (!empty($download_files_result['error'])) {
            throw new \Exception($download_files_result['error']);
        }

        //create tables
        $create_tables_result = apbct_sfw_update__create_tables(true);
        $direct_update_log['apbct_sfw_update__create_tables'] = $create_tables_result;
        if (!empty($create_tables_result['error'])) {
            throw new \Exception($create_tables_result['error']);
        }

        //create temp tables
        $create_temp_tables_result = apbct_sfw_update__create_temp_tables(true);
        $direct_update_log['apbct_sfw_update__create_temp_tables'] = $create_temp_tables_result;
        if (!empty($create_temp_tables_result['error'])) {
            throw new \Exception($create_temp_tables_result['error']);
        }

        //process files
        $update_processing_files_result = SFW::directUpdateProcessFiles();
        $direct_update_log['directUpdateProcessFiles'] = $update_processing_files_result;
        if (!empty($update_processing_files_result['error'])) {
            throw new \Exception($update_processing_files_result['error']);
        }

        //process_exclusions
        $process_exclusions_result = apbct_sfw_update__process_exclusions(true);
        $direct_update_log['apbct_sfw_update__process_exclusions'] = $process_exclusions_result;
        if (!empty($process_exclusions_result['error'])) {
            throw new \Exception($process_exclusions_result['error']);
        }

        //renaming tables
        $renaming_tables_result = apbct_sfw_update__end_of_update__renaming_tables(true);
        $direct_update_log['apbct_sfw_update__end_of_update__renaming_tables'] = $renaming_tables_result;
        if (!empty($renaming_tables_result['error'])) {
            throw new \Exception($renaming_tables_result['error']);
        }

        //checking data
        $checking_data_result = apbct_sfw_update__end_of_update__checking_data(true);
        $direct_update_log['apbct_sfw_update__end_of_update__checking_data'] = $checking_data_result;
        if (!empty($checking_data_result['error'])) {
            throw new \Exception($checking_data_result['error']);
        }

        //updating stats
        $updating_stats_result = apbct_sfw_update__end_of_update__updating_stats(true);
        $direct_update_log['apbct_sfw_update__end_of_update__updating_stats'] = $updating_stats_result;
        if (!empty($updating_stats_result['error'])) {
            throw new \Exception($updating_stats_result['error']);
        }

        //end of update
        $end_of_update_result = apbct_sfw_update__end_of_update($updating_stats_result['next_stage']['args']);
        $direct_update_log['apbct_sfw_update__end_of_update__updating_stats'] = $end_of_update_result;
        if (!empty($end_of_update_result['error'])) {
            throw new \Exception($end_of_update_result['error']);
        }

        $final_result = $end_of_update_result;

    } catch (Exception $e) {
        $direct_update_log['direct_update_stop_reason'] = $e;
        $final_result = array('error' => $e);
    }

    $apbct->fw_stats['direct_update_log'] = $direct_update_log;
    $apbct->save('fw_stats', true, false);
    return $final_result;
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
