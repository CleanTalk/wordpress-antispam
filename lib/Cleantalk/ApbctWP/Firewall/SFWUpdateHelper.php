<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\ApbctWP\API;
use Cleantalk\ApbctWP\Cron;
use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\Common\TT;

class SFWUpdateHelper
{
    /**
     *  SFW UPDATE INNER FUNCTIONS *
     */
    /**
     * Not queue stage. Write a file content to the SFW temp common/personal table depends on type of SFW load.
     * @param string $file_path
     * @param string $direction
     * @return int|string[]
     */
    public static function processFile($file_path, $direction = 'common')
    {
        global $apbct;

        if (empty($file_path) || ! file_exists($file_path) ) {
            return array('error' => 'PROCESS FILE: ' . $file_path . ' is not exists.');
        }

        $table_name = $direction === 'common'
            ? $apbct->data['sfw_common_table_name'] . '_temp'
            : $apbct->data['sfw_personal_table_name'] . '_temp';

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
    public static function processUA($file_path)
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
    public static function processCK($file_path, $direction = 'common')
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
     * @throws \Exception
     */
    public static function getDirectionUrlsOfType($type = 'common')
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
     * @throws \Exception
     */
    public static function removeAndRenameSfwTables($sfw_load_type)
    {
        global $apbct;

        if ( $sfw_load_type === 'all' ) {
            //common table delete
            $result_deletion = SFW::dataTablesDelete(DB::getInstance(), $apbct->data['sfw_common_table_name']);
            if ( !empty($result_deletion['error']) ) {
                throw new \Exception('SFW_COMMON_TABLE_DELETION_ERROR');
            }
            //common table rename
            $result_renaming = SFW::renameDataTablesFromTempToMain(DB::getInstance(), $apbct->data['sfw_common_table_name']);
            if ( !empty($result_renaming['error']) ) {
                throw new \Exception('SFW_COMMON_TABLE_RENAME_ERROR');
            }

            //personal table delete
            $result_deletion = SFW::dataTablesDelete(DB::getInstance(), $apbct->data['sfw_personal_table_name']);
            if ( !empty($result_deletion['error']) ) {
                throw new \Exception('SFW_PERSONAL_TABLE_DELETION_ERROR');
            }
            //personal table rename
            $result_renaming = SFW::renameDataTablesFromTempToMain(DB::getInstance(), $apbct->data['sfw_personal_table_name']);
            if ( !empty($result_renaming['error']) ) {
                throw new \Exception('SFW_PERSONAL_TABLE_RENAME_ERROR');
            }
        } elseif ( $sfw_load_type === 'personal' ) {
            //personal table delete
            $result_deletion = SFW::dataTablesDelete(DB::getInstance(), $apbct->data['sfw_personal_table_name']);
            if ( !empty($result_deletion['error']) ) {
                throw new \Exception('SFW_PERSONAL_TABLE_DELETION_ERROR');
            }
            //personal table rename
            $result_renaming = SFW::renameDataTablesFromTempToMain(DB::getInstance(), $apbct->data['sfw_personal_table_name']);
            if ( !empty($result_renaming['error']) ) {
                throw new \Exception('SFW_PERSONAL_TABLE_RENAME_ERROR');
            }
        } elseif ( $sfw_load_type === 'common' ) {
            //common table delete
            $result_deletion = SFW::dataTablesDelete(DB::getInstance(), $apbct->data['sfw_common_table_name']);
            if ( !empty($result_deletion['error']) ) {
                throw new \Exception('SFW_COMMON_TABLE_DELETION_ERROR');
            }
            //common table rename
            $result_renaming = SFW::renameDataTablesFromTempToMain(DB::getInstance(), $apbct->data['sfw_common_table_name']);
            if ( !empty($result_renaming['error']) ) {
                throw new \Exception('SFW_COMMON_TABLE_RENAME_ERROR');
            }
        }
    }

    /**
     * Not queue stage. Check tables integrity depends on type of SFW load before renaming.
     * @param $sfw_load_type
     * @return string[]|true
     */
    public static function checkTablesIntegrityBeforeRenaming($sfw_load_type)
    {
        global $apbct;

        if ( $sfw_load_type === 'all' ) {
            if ( !DB::getInstance()->isTableExists($apbct->data['sfw_common_table_name']) ) {
                return array('error' => 'Error while completing data: SFW main table does not exist.');
            }
            if ( !DB::getInstance()->isTableExists($apbct->data['sfw_common_table_name'] . '_temp') ) {
                return array('error' => 'Error while completing data: SFW temp table does not exist.');
            }
            if ( ! DB::getInstance()->isTableExists($apbct->data['sfw_personal_table_name']) ) {
                return array('error' => 'Error while completing data: SFW_PERSONAL main table does not exist.');
            }

            if ( ! DB::getInstance()->isTableExists($apbct->data['sfw_personal_table_name'] . '_temp') ) {
                return array('error' => 'Error while completing data: SFW_PERSONAL temp table does not exist.');
            }
        } elseif ( $sfw_load_type === 'personal' ) {
            //personal tables
            if ( ! DB::getInstance()->isTableExists($apbct->data['sfw_personal_table_name']) ) {
                return array('error' => 'Error while completing data: SFW_PERSONAL main table does not exist.');
            }

            if ( ! DB::getInstance()->isTableExists($apbct->data['sfw_personal_table_name'] . '_temp') ) {
                return array('error' => 'Error while completing data: SFW_PERSONAL temp table does not exist.');
            }
        } elseif ( $sfw_load_type === 'common' ) {
            //common tables
            if ( !DB::getInstance()->isTableExists($apbct->data['sfw_common_table_name']) ) {
                return array('error' => 'Error while completing data: SFW main table does not exist.');
            }

            if ( !DB::getInstance()->isTableExists($apbct->data['sfw_common_table_name'] . '_temp') ) {
                return array('error' => 'Error while completing data: SFW temp table does not exist.');
            }
        }
        return true;
    }

    /**
     * Not queue stage. Check tables integrity depends on type of SFW load after renaming.
     * @param $sfw_load_type
     * @throws \Exception
     */
    public static function checkTablesIntegrityAfterRenaming($sfw_load_type)
    {
        global $apbct;

        if ( $sfw_load_type === 'all' ) {
            //personal tables
            if ( ! DB::getInstance()->isTableExists($apbct->data['sfw_personal_table_name']) ) {
                throw new \Exception('Error while checking data: SFW personal table does not exist.');
            }
            //common tables
            if ( ! DB::getInstance()->isTableExists($apbct->data['sfw_common_table_name']) ) {
                throw new \Exception('Error while checking data: SFW main table does not exist.');
            }
        } elseif ( $sfw_load_type === 'personal' ) {
            //personal tables
            if ( ! DB::getInstance()->isTableExists($apbct->data['sfw_personal_table_name']) ) {
                throw new \Exception('Error while checking data: SFW personal table does not exist.');
            }
        } elseif ( $sfw_load_type === 'common' ) {
            //common tables
            if ( ! DB::getInstance()->isTableExists($apbct->data['sfw_common_table_name']) ) {
                throw new \Exception('Error while checking data: SFW main table does not exist.');
            }
        }
    }

    public static function updateIsInProgress()
    {
        $queue = new \Cleantalk\ApbctWP\Queue();

        return ! $queue->isQueueFinished();
    }

    public static function updateIsFrozen()
    {
        $queue = new \Cleantalk\ApbctWP\Queue();

        return ! $queue->isQueueFinished() && $queue->queue['started'] > time() - 86400;
    }

    public static function prepareUpdDir()
    {
        global $apbct;

        $ready_fw_uploads_dir        = '';
        $wp_fw_uploads_dir           = $apbct->fw_stats['updating_folder'];
        $apbct_custom_fw_uploads_dir = APBCT_DIR_PATH . 'cleantalk_fw_files_for_blog_' . get_current_blog_id() . DIRECTORY_SEPARATOR;

        if ( ! is_dir($wp_fw_uploads_dir) ) {
            if ( mkdir($wp_fw_uploads_dir) && is_dir($wp_fw_uploads_dir) && is_writable($wp_fw_uploads_dir)) {
                $ready_fw_uploads_dir = $wp_fw_uploads_dir;
            }
        } elseif ( is_writable($wp_fw_uploads_dir) ) {
            $ready_fw_uploads_dir = $wp_fw_uploads_dir;
        }

        if ( $ready_fw_uploads_dir === '' ) {
            if ( ! is_dir($apbct_custom_fw_uploads_dir) ) {
                if ( mkdir($apbct_custom_fw_uploads_dir) && is_dir($apbct_custom_fw_uploads_dir) && is_writable($apbct_custom_fw_uploads_dir)) {
                    $ready_fw_uploads_dir = $apbct_custom_fw_uploads_dir;
                }
            } elseif ( is_writable($apbct_custom_fw_uploads_dir) ) {
                $ready_fw_uploads_dir = $apbct_custom_fw_uploads_dir;
                $apbct->fw_stats['updating_folder'] = $ready_fw_uploads_dir;
                $apbct->save('fw_stats');
            }
        }

        if ( $ready_fw_uploads_dir === '' ) {
            return array('error' => 'Can not make FW update directory.');
        }

        $files = glob($ready_fw_uploads_dir . '/*');
        if ( $files === false ) {
            return array('error' => 'Can not find FW files.');
        }

        if ( count($files) !== 0 ) {
            foreach ($files as $file) {
                if ( @is_file($file) && @unlink($file) === false ) {
                    if (strpos($file, 'index.php') === false) {
                        return array('error' => 'Can not delete the FW file: ' . $file);
                    }
                }
            }
        }

        if ( ! file_exists($ready_fw_uploads_dir . 'index.php') ) {
            return file_put_contents($ready_fw_uploads_dir . 'index.php', '<?php' . PHP_EOL)
                ? true
                : array('error' => 'Can not modify FW index file: ' . $ready_fw_uploads_dir . 'index.php');
        }

        return true;
    }

    public static function removeUpdFolder($dir_name)
    {
        if ( is_dir($dir_name) ) {
            $files = glob($dir_name . '/*');

            if ( ! empty($files) ) {
                foreach ( $files as $file ) {
                    if ( is_file($file) ) {
                        unlink($file);
                    }
                    if ( is_dir($file) ) {
                        SFWUpdateHelper::removeUpdFolder($file);
                    }
                }
            }

            $safe_base_dir = rtrim(ABSPATH, '/') . '/wp-content/uploads/cleantalk_fw_files';
            $allowed_filenames = array('.last.jpegoptim');
            foreach ($allowed_filenames as $filename) {
                $filepath = $safe_base_dir . '/' . $filename;
                if (strpos($filepath, $safe_base_dir) === 0 && file_exists($filepath) && is_file($filepath) && !is_writable($filepath)) {
                    if (!unlink($filepath)) {
                        error_log('Failed to delete file: ' . $filepath);
                    }
                }
            }

            rmdir($dir_name);
        }
    }

    public static function directUpdate()
    {
        global $apbct;
        $apbct->fw_stats['direct_update_log'] = null;
        $apbct->fw_stats['firewall_updating_last_start'] = time();
        $apbct->save('fw_stats');

        $direct_update_log = array();

        try {
            $api_key = $apbct->api_key;

            // The Access key is empty
            if ( empty($api_key) ) {
                throw new \Exception('SFW DIRECT UPDATE: KEY_IS_EMPTY');
            }

            // Getting BL
            $get_multifiles_params = [];
            $load_type = isset($apbct->data['sfw_load_type']) ? $apbct->data['sfw_load_type'] : 'all';
            if ( $load_type === 'all' ) {
                $get_multifiles_result = apbct_sfw_update__get_multifiles_all();
            } else {
                $get_multifiles_params['type'] = $load_type;
                $get_multifiles_params['do_return_urls'] = false;
                $get_multifiles_result = apbct_sfw_update__get_multifiles_of_type($get_multifiles_params);
            }
            $direct_update_log['apbct_sfw_get_multifiles'] = $get_multifiles_result;
            if (!empty($get_multifiles_result['error'])) {
                $error = is_string($get_multifiles_result['error']) ? $get_multifiles_result['error'] : 'unknown apbct_sfw_update__get_multifiles error';
                throw new \Exception($error);
            }

            //prepare files directory
            $prepare_upd_dir_result = self::prepareUpdDir();
            $direct_update_log['apbct_sfw_update__prepare_dir'] = $prepare_upd_dir_result;
            if (!empty($prepare_upd_dir_result['error'])) {
                $error = is_string($prepare_upd_dir_result['error']) ? $prepare_upd_dir_result['error'] : 'unknown apbct_sfw_update__prepare_dir error';
                throw new \Exception($error);
            }

            //download files
            $urls = isset($get_multifiles_result['next_stage'], $get_multifiles_result['next_stage']['args'])
                ? $get_multifiles_result['next_stage']['args']
                : array();
            $download_files_result = apbct_sfw_update__download_files($urls, true);
            $direct_update_log['apbct_sfw_update__download_files'] = $download_files_result;
            if (!empty($download_files_result['error'])) {
                $error = is_string($download_files_result['error']) ? $download_files_result['error'] : 'unknown apbct_sfw_update__download_files error';
                throw new \Exception($error);
            }

            //create tables
            $create_tables_result = apbct_sfw_update__create_tables(true);
            $direct_update_log['apbct_sfw_update__create_tables'] = $create_tables_result;
            if (!empty($create_tables_result['error'])) {
                $error = is_string($create_tables_result['error']) ? $create_tables_result['error'] : 'unknown apbct_sfw_update__create_tables error';
                throw new \Exception($error);
            }

            //create temp tables
            $create_temp_tables_result = apbct_sfw_update__create_temp_tables(true);
            $direct_update_log['apbct_sfw_update__create_temp_tables'] = $create_temp_tables_result;
            if (!empty($create_temp_tables_result['error'])) {
                $error = is_string($create_temp_tables_result['error']) ? $create_temp_tables_result['error'] : 'unknown apbct_sfw_update__create_temp_tables error';
                throw new \Exception($error);
            }

            //process files
            $update_processing_files_result = SFW::directUpdateProcessFiles();
            $direct_update_log['directUpdateProcessFiles'] = $update_processing_files_result;
            if (!empty($update_processing_files_result['error'])) {
                $error = is_string($update_processing_files_result['error']) ? $update_processing_files_result['error'] : 'unknown directUpdateProcessFiles error';
                throw new \Exception($error);
            }

            //process_exclusions
            $process_exclusions_result = apbct_sfw_update__process_exclusions(true);
            $direct_update_log['apbct_sfw_update__process_exclusions'] = $process_exclusions_result;
            if (!empty($process_exclusions_result['error'])) {
                $error = is_string($process_exclusions_result['error']) ? $process_exclusions_result['error'] : 'unknown apbct_sfw_update__process_exclusions error';
                throw new \Exception($error);
            }

            //renaming tables
            $renaming_tables_result = apbct_sfw_update__end_of_update__renaming_tables(true);
            $direct_update_log['apbct_sfw_update__end_of_update__renaming_tables'] = $renaming_tables_result;
            if (!empty($renaming_tables_result['error'])) {
                $error = is_string($renaming_tables_result['error']) ? $renaming_tables_result['error'] : 'unknown apbct_sfw_update__end_of_update__renaming_tables error';
                throw new \Exception($error);
            }

            //checking data
            $checking_data_result = apbct_sfw_update__end_of_update__checking_data(true);
            $direct_update_log['apbct_sfw_update__end_of_update__checking_data'] = $checking_data_result;
            if (!empty($checking_data_result['error'])) {
                $error = is_string($checking_data_result['error']) ? $checking_data_result['error'] : 'unknown apbct_sfw_update__end_of_update__checking_data error';
                throw new \Exception($error);
            }

            //updating stats
            $updating_stats_result = apbct_sfw_update__end_of_update__updating_stats(true);
            $direct_update_log['apbct_sfw_update__end_of_update__updating_stats'] = $updating_stats_result;
            if (!empty($updating_stats_result['error'])) {
                $error = is_string($updating_stats_result['error']) ? $updating_stats_result['error'] : 'unknown apbct_sfw_update__end_of_update__updating_stats error';
                throw new \Exception($error);
            }

            //end of update
            $is_first_update = (
                isset($get_multifiles_result['next_stage']['args']) &&
                TT::toBool($get_multifiles_result['next_stage']['args'])
            );
            $end_of_update_result = apbct_sfw_update__end_of_update($is_first_update);
            $direct_update_log['apbct_sfw_update__end_of_update__updating_stats'] = $end_of_update_result;

            $final_result = $end_of_update_result;
        } catch (\Exception $e) {
            $direct_update_log['direct_update_stop_reason'] = $e->getMessage();
            $final_result = array('error' => $e->getMessage());
        }

        $apbct->fw_stats['direct_update_log'] = $direct_update_log;
        $apbct->save('fw_stats');
        return $final_result;
    }

    public static function cleanData()
    {
        global $apbct;

        SFW::dataTablesDelete(DB::getInstance(), $apbct->data['sfw_common_table_name'] . '_temp');
        SFW::dataTablesDelete(DB::getInstance(), $apbct->data['sfw_personal_table_name'] . '_temp');

        $apbct->fw_stats['firewall_update_percent'] = 0;
        $apbct->fw_stats['firewall_updating_id']    = null;
        $apbct->save('fw_stats');
    }

    public static function fallback()
    {
        global $apbct;
        /**
         * Remove the upd folder
         */
        if ( $apbct->fw_stats['updating_folder'] ) {
            self::removeUpdFolder($apbct->fw_stats['updating_folder']);
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
        self::cleanData();

        /**
         * Create SFW table if not exists
         */
        apbct_sfw_update__create_tables();
    }
}
