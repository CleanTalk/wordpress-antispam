<?php

/**
 * Function for SpamFireWall check
 */
function apbct_sfw__check()
{
    global $apbct, $spbc, $cleantalk_url_exclusions;

    // Turn off the SpamFireWall if current url in the exceptions list and WordPress core pages
    if ( ! empty($cleantalk_url_exclusions) && is_array($cleantalk_url_exclusions) ) {
        $core_page_to_skip_check = array('/feed');
        foreach ( array_merge($cleantalk_url_exclusions, $core_page_to_skip_check) as $v ) {
            if ( apbct_is_in_uri($v) ) {
                return;
            }
        }
    }

    // Skip the check
    if ( ! empty(Get::get('access')) ) {
        $spbc_settings = get_option('spbc_settings');
        $spbc_key      = ! empty($spbc_settings['spbc_key']) ? $spbc_settings['spbc_key'] : false;
        if ( Get::get('access') === $apbct->api_key || ($spbc_key !== false && Get::get('access') === $spbc_key) ) {
            Cookie::set(
                'spbc_firewall_pass_key',
                md5(Server::get('REMOTE_ADDR') . $spbc_key),
                time() + 1200,
                '/',
                ''
            );
            Cookie::set(
                'ct_sfw_pass_key',
                md5(Server::get('REMOTE_ADDR') . $apbct->api_key),
                time() + 1200,
                '/',
                ''
            );

            return;
        }
        unset($spbc_settings, $spbc_key);
    }

    // Turn off the SpamFireWall if Remote Call is in progress
    if ( $apbct->rc_running || ( ! empty($spbc) && $spbc->rc_running) ) {
        return;
    }

    // update mode - skip checking
    if ( isset($apbct->fw_stats['update_mode']) && $apbct->fw_stats['update_mode'] === 1 ) {
        return;
    }

    // Checking if database was outdated
    $is_sfw_outdated = $apbct->stats['sfw']['last_update_time'] + $apbct->stats['sfw']['update_period'] * 3 < time();

    $apbct->errorToggle(
        $is_sfw_outdated,
        'sfw_outdated',
        esc_html__(
            'SpamFireWall database is outdated. Please, try to synchronize with the cloud.',
            'cleantalk-spam-protect'
        )
    );

    if ( $is_sfw_outdated ) {
        return;
    }

    $firewall = new Firewall(
        DB::getInstance()
    );

    $sfw_tables_names = SFW::getSFWTablesNames();

    if (!$sfw_tables_names) {
            $apbct->errorAdd(
                'sfw',
                esc_html__(
                    'Can not get SFW table names from main blog options',
                    'cleantalk-spam-protect'
                )
            );
            return;
    }

    $firewall->loadFwModule(
        new SFW(
            APBCT_TBL_FIREWALL_LOG,
            $sfw_tables_names['sfw_personal_table_name'],
            array(
                'sfw_counter'       => $apbct->settings['admin_bar__sfw_counter'],
                'api_key'           => $apbct->api_key,
                'apbct'             => $apbct,
                'cookie_domain'     => parse_url(get_option('home'), PHP_URL_HOST),
                'data__cookies_type' => $apbct->data['cookies_type'],
                'sfw_common_table_name'  => $sfw_tables_names['sfw_common_table_name'],
            )
        )
    );

    if ( $apbct->settings['sfw__anti_crawler'] && $apbct->stats['sfw']['entries'] > 50 ) {
        $firewall->loadFwModule(
            new \Cleantalk\ApbctWP\Firewall\AntiCrawler(
                APBCT_TBL_FIREWALL_LOG,
                APBCT_TBL_AC_LOG,
                array(
                    'api_key' => $apbct->api_key,
                    'apbct'   => $apbct,
                )
            )
        );
    }

    if ( $apbct->settings['sfw__anti_flood'] && is_null(apbct_wp_get_current_user()) ) {
        $firewall->loadFwModule(
            new AntiFlood(
                APBCT_TBL_FIREWALL_LOG,
                APBCT_TBL_AC_LOG,
                array(
                    'api_key'    => $apbct->api_key,
                    'view_limit' => $apbct->settings['sfw__anti_flood__view_limit'],
                    'apbct'      => $apbct,
                )
            )
        );
    }

    $firewall->run();
}

// This action triggered by  wp_schedule_single_event( time() + 720, 'apbct_sfw_update__init' );
add_action('apbct_sfw_update__init', 'apbct_sfw_update__init');

/**
 * * * * * * SFW UPDATE ACTIONS * * * * * *
 */

/**
 * Called by sfw_update remote call
 * Starts SFW update and could use a delay before start
 *
 * @param int $delay
 *
 * @return bool|string|string[]
 */
function apbct_sfw_update__init($delay = 0)
{
    global $apbct;

    //do not run sfw update on subsites if mutual key is set
    if ($apbct->network_settings['multisite__work_mode'] === 2 && !is_main_site()) {
        return false;
    }

    // Prevent start an update if update is already running and started less than 10 minutes ago
    if (
        $apbct->fw_stats['firewall_updating_id'] &&
        time() - $apbct->fw_stats['firewall_updating_last_start'] < 600 &&
        SFWUpdateHelper::updateIsInProgress() &&
        ! SFWUpdateHelper::updateIsFrozen()
    ) {
        return false;
    }

    if ( ! $apbct->settings['sfw__enabled'] ) {
        return false;
    }

    // The Access key is empty
    if ( ! $apbct->api_key && ! $apbct->ip_license ) {
        return array('error' => 'SFW UPDATE INIT: KEY_IS_EMPTY');
    }

    if ( ! $apbct->data['key_is_ok'] ) {
        return array('error' => 'SFW UPDATE INIT: KEY_IS_NOT_VALID');
    }

    // Get update period for server
    $update_period = DNS::getRecord('spamfirewall-ttl-txt.cleantalk.org', true, true);
    $update_period = isset($update_period['txt']) ? $update_period['txt'] : 0;
    $update_period = (int)$update_period > 14400 ? (int)$update_period : 14400;
    if ( $apbct->stats['sfw']['update_period'] != $update_period ) {
        $apbct->stats['sfw']['update_period'] = $update_period;
        $apbct->save('stats');
    }

    $sfw_tables_names = SFW::getSFWTablesNames();

    if ( !$sfw_tables_names ) {
        //try to create tables
        $sfw_tables_names = apbct_sfw_update__create_tables(false, true);
        if (!$sfw_tables_names) {
            return array('error' => 'Can not get SFW table names from main blog options');
        }
    }

    $apbct->data['sfw_common_table_name'] = TT::getArrayValueAsString($sfw_tables_names, 'sfw_common_table_name');
    $apbct->data['sfw_personal_table_name'] = TT::getArrayValueAsString($sfw_tables_names, 'sfw_personal_table_name');
    $apbct->save('data');

    $wp_upload_dir = wp_upload_dir();
    $base_dir = TT::getArrayValueAsString($wp_upload_dir, 'basedir');
    $apbct->fw_stats['updating_folder'] = $base_dir . DIRECTORY_SEPARATOR . 'cleantalk_fw_files_for_blog_' . get_current_blog_id() . DIRECTORY_SEPARATOR;
    //update only common tables if moderate 0
    if ( ! $apbct->moderate ) {
        $apbct->data['sfw_load_type'] = 'common';
    }

    if ( $apbct->network_settings['multisite__work_mode'] == 3) {
        $apbct->data['sfw_load_type'] = 'all';
        $apbct->save('data');
    }

    if (apbct_sfw_update__switch_to_direct()) {
        return SFWUpdateHelper::directUpdate();
    }

    // Set a new update ID and an update time start
    $apbct->fw_stats['calls']                        = 0;
    $apbct->fw_stats['firewall_updating_id']         = md5((string)rand(0, 100000));
    $apbct->fw_stats['firewall_updating_last_start'] = time();
    $apbct->fw_stats['common_lists_url_id'] = '';
    $apbct->fw_stats['personal_lists_url_id'] = '';
    $apbct->save('fw_stats');

    $apbct->sfw_update_sentinel->seekId($apbct->fw_stats['firewall_updating_id']);

    // Delete update errors
    $apbct->errorDelete('sfw_update', 'save_data');
    $apbct->errorDelete('sfw_update', 'save_data', 'cron');

    \Cleantalk\ApbctWP\Queue::clearQueue();

    $queue = new \Cleantalk\ApbctWP\Queue();
    //this is the first stage, select what type of SFW load need
    $load_type = isset($apbct->data['sfw_load_type']) ? TT::toString($apbct->data['sfw_load_type']) : 'all';
    $get_multifiles_params = array();
    if ( $load_type === 'all' ) {
        $queue->addStage('apbct_sfw_update__get_multifiles_all');
    } else {
        $get_multifiles_params['type'] = $load_type;
        $get_multifiles_params['do_return_urls'] = false;
        $queue->addStage('apbct_sfw_update__get_multifiles_of_type', $get_multifiles_params);
    }

    $cron = new Cron();
    $watch_dog_period = $apbct->sfw_update_sentinel->getWatchDogCronPeriod();
    $cron->addTask('sfw_update_sentinel_watchdog', 'apbct_sfw_update_sentinel__run_watchdog', $watch_dog_period, time() + $watch_dog_period);
    $cron->addTask('sfw_update_checker', 'apbct_sfw_update__checker', 15);

    return Helper::httpRequestRcToHost(
        'sfw_update__worker',
        array(
            'firewall_updating_id' => $apbct->fw_stats['firewall_updating_id'],
            'delay'                => $delay
        ),
        array('async')
    );
}

/**
 * Decide need to force direct update
 *
 * @return bool
 * @psalm-suppress NullArgument
 */
function apbct_sfw_update__switch_to_direct()
{
    global $apbct;

    $apbct->fw_stats['reason_direct_update_log'] = null;

    if (defined('APBCT_SFW_FORCE_DIRECT_UPDATE')) {
        $apbct->fw_stats['reason_direct_update_log'] = 'const APBCT_SFW_FORCE_DIRECT_UPDATE exists';
        return true;
    }

    $prepare_dir__result = SFWUpdateHelper::prepareUpdDir();
    if (!empty($prepare_dir__result['error'])) {
        $apbct->fw_stats['reason_direct_update_log'] = 'variable prepare_dir__result has error';
        return true;
    }

    $test_rc_result = Helper::httpRequestRcToHostTest(
        'sfw_update__worker',
        array(
            'spbc_remote_call_token' => md5($apbct->api_key),
            'spbc_remote_call_action' => 'sfw_update__worker',
            'plugin_name' => 'apbct'
        )
    );
    if (!empty($test_rc_result['error'])) {
        $apbct->fw_stats['reason_direct_update_log'] = 'test remote call has error';
        return true;
    }

    if (isset($apbct->fw_stats['firewall_updating_last_start'], $apbct->stats['sfw']['update_period']) &&
    ((int)$apbct->fw_stats['firewall_updating_last_start'] + (int)$apbct->stats['sfw']['update_period'] + 3600) < time()) {
        $apbct->fw_stats['reason_direct_update_log'] = 'general update is freezing';
        return true;
    }

    return false;
}

/**
 * Called by sfw_update__worker remote call
 * gather all process about SFW updating
 *
 * @param null|string $updating_id
 * @param null|string $multifile_url
 * @param null|string|int $url_count
 * @param null|string|int $current_url
 * @param string $useragent_url
 *
 * @return array|bool|int|string|string[]
 */
function apbct_sfw_update__worker($checker_work = false)
{
    global $apbct;

    if ( ! $apbct->data['key_is_ok'] ) {
        return array('error' => 'Worker: KEY_IS_NOT_VALID');
    }

    if ( ! $apbct->settings['sfw__enabled'] ) {
        return false;
    }

    if ( ! $checker_work ) {
        if (
            Request::equal('firewall_updating_id', '') ||
            ! Request::equal('firewall_updating_id', $apbct->fw_stats['firewall_updating_id'])
        ) {
            return array('error' => 'Worker: WRONG_UPDATE_ID');
        }
    }

    if ( ! isset($apbct->fw_stats['calls']) ) {
        $apbct->fw_stats['calls'] = 0;
    }

    $apbct->fw_stats['calls']++;
    $apbct->save('fw_stats');

    if ( $apbct->fw_stats['calls'] > 600 ) {
        $apbct->errorAdd('sfw_update', 'WORKER_CALL_LIMIT_EXCEEDED');
        $apbct->saveErrors();

        return 'WORKER_CALL_LIMIT_EXCEEDED';
    }

    $queue = new \Cleantalk\ApbctWP\Queue();

    if ( count($queue->queue['stages']) === 0 ) {
        // Queue is already empty. Exit.
        return true;
    }

    $result = $queue->executeStage();

    if ( $result === null ) {
        // The stage is in progress, will try to wait up to 5 seconds to its complete
        for ( $i = 0; $i < 5; $i++ ) {
            sleep(1);
            $queue->refreshQueue();
            if ( ! $queue->isQueueInProgress() ) {
                // The stage executed, break waiting and continue sfw_update__worker process
                break;
            }
            if ( $i >= 4 ) {
                // The stage still not executed, exit from sfw_update__worker
                return true;
            }
        }
    }

    if ( isset($result['error'], $result['status']) && $result['status'] === 'FINISHED' ) {
        SFWUpdateHelper::fallback();

        $direct_upd_res = SFWUpdateHelper::directUpdate();

        if ( !empty($direct_upd_res['error']) ) {
            $apbct->errorAdd('queue', $result['error'], 'sfw_update');
            $apbct->errorAdd('direct', $direct_upd_res['error'], 'sfw_update');
            $apbct->saveErrors();

            return $direct_upd_res['error'];
        }

        //stop seeking updates on success direct update
        $apbct->sfw_update_sentinel->clearSentinelData();

        return true;
    }

    if ( $queue->isQueueFinished() ) {
        $queue->queue['finished'] = time();
        $queue->saveQueue($queue->queue);
        foreach ( $queue->queue['stages'] as $stage ) {
            if ( isset($stage['error'], $stage['status']) && $stage['status'] !== 'FINISHED' ) {
                //there could be an array of errors of files processed
                if ( is_array($stage['error']) ) {
                    $error = implode(" ", array_values($stage['error']));
                } else {
                    $error = $result['error'];
                }
                $apbct->errorAdd('sfw_update', $error);
            }
        }

        // Do logging the queue process here
        return true;
    }

    // This is the repeat stage request, do not generate any new RC
    if ( stripos(TT::toString(Request::get('stage')), 'Repeat') !== false ) {
        return true;
    }

    return Helper::httpRequestRcToHost(
        'sfw_update__worker',
        array('firewall_updating_id' => $apbct->fw_stats['firewall_updating_id']),
        array('async')
    );
}

/**
 * QUEUE STAGES *
 */

/**
 * Queue stage. Get both types of multifiles (common/personal) url for next downloading.
 * @return array|array[]|string[]
 */
function apbct_sfw_update__get_multifiles_all()
{
    global $apbct;

    if ( ! $apbct->data['key_is_ok'] ) {
        return array('error' => 'Get multifiles: KEY_IS_NOT_VALID');
    }

    $get_multifiles_common_urls = apbct_sfw_update__get_multifiles_of_type(array('type' => 'common', 'do_return_urls' => true));
    if ( !empty($get_multifiles_common_urls['error']) ) {
        $output_error = $get_multifiles_common_urls['error'];
    }

    $get_multifiles_personal_urls = apbct_sfw_update__get_multifiles_of_type(array('type' => 'personal', 'do_return_urls' => true));
    if ( !empty($get_multifiles_personal_urls['error']) ) {
        $output_error = $get_multifiles_personal_urls['error'];
    }

    $file_urls = array_merge($get_multifiles_common_urls, $get_multifiles_personal_urls);

    if ( empty($file_urls) ) {
        $output_error = 'SFW_UPDATE_FILES_URLS_IS_EMPTY';
    }

    if ( empty($output_error) ) {
        $apbct->fw_stats['firewall_update_percent'] = round(100 / count($file_urls), 2);
        $apbct->save('fw_stats');

        return array(
            'next_stage' => array(
                'name'    => 'apbct_sfw_update__download_files',
                'args'    => $file_urls,
                'is_last' => '0'
            )
        );
    } else {
        return array('error' => $output_error);
    }
}

/**
 * Queue stage. Get multifiles url for next downloading. Can return urls directly if flag is set instead of next stage call.
 * @param array $params 'type' -> type of SFW load, 'do_return_urls' -> return urls if true, array call for next stage otherwise(default false)
 * @return array|array[]|string[]
 */
function apbct_sfw_update__get_multifiles_of_type(array $params)
{
    global $apbct;
    //check key
    if ( ! $apbct->data['key_is_ok'] ) {
        return array('error' => 'Get multifiles: KEY_IS_NOT_VALID');
    }

    //chek type
    if ( !isset($params['type']) || !in_array($params['type'], array('common', 'personal')) ) {
        return array('error' => 'Get multifiles: bad params');
    } else {
        $type = $params['type'];
    }

    //check needs return urls instead of next stage
    $do_return_urls = TT::getArrayValueAsBool($params, 'do_return_urls');

    $output_error = array();
    // getting urls
    try {
        $direction_files = SFWUpdateHelper::getDirectionUrlsOfType($type);
        $direction_url_id = TT::getArrayValueAsString($direction_files, 'url_id');
        if ( $type === 'personal' ) {
            $apbct->fw_stats['personal_lists_url_id'] = $direction_url_id;
        } else {
            $apbct->fw_stats['common_lists_url_id'] = $direction_url_id;
        }
    } catch (\Exception $e) {
        $output_error[] = $e->getMessage();
    }

    if ( empty($output_error) ) {
        if ( !empty($direction_files['files_urls']) ) {
            $urls = array();
            foreach ( $direction_files['files_urls'] as $value ) {
                $urls[] = $value[0];
            }

            $apbct->fw_stats['firewall_update_percent'] = round(100 / count($urls), 2);
            $apbct->save('fw_stats');

            // return urls directly on do load all multifiles, otherwise proceed to next queue stage
            if ( !$do_return_urls) {
                return array(
                    'next_stage' => array(
                        'name'    => 'apbct_sfw_update__download_files',
                        'args'    => $urls,
                        'is_last' => '0'
                    )
                );
            } else {
                return $urls;
            }
        } else {
            return array('error' => 'SFW_UPDATE_FILES_URLS_IS_EMPTY');
        }
    } else {
        return array('error' => $output_error);
    }
}

/**
 * Queue stage. Do load multifiles with networks on their urls.
 * @param $urls
 * @return array|array[]|bool|string|string[]
 */
function apbct_sfw_update__download_files($urls, $direct_update = false)
{
    global $apbct;

    sleep(3);

    if ( ! is_writable($apbct->fw_stats['updating_folder']) ) {
        return array('error' => 'SFW update folder is not writable.');
    }

    //Reset keys
    $urls          = array_values(array_unique($urls));
    $results       = Helper::httpMultiRequest($urls, $apbct->fw_stats['updating_folder']);
    $results       = TT::toArray($results);
    $count_urls    = count($urls);
    $count_results = count($results);


    if ( empty($results['error']) && ($count_urls === $count_results) ) {
        if ( $direct_update ) {
            return true;
        }
        $download_again = array();
        $results        = array_values($results);
        for ( $i = 0; $i < $count_results; $i++ ) {
            if ( $results[$i] === 'error' ) {
                $download_again[] = $urls[$i];
            }
        }

        if ( count($download_again) !== 0 ) {
            return array(
                'error'       => 'Files download not completed.',
                'update_args' => array(
                    'args' => $download_again
                )
            );
        }

        return array(
            'next_stage' => array(
                'name' => 'apbct_sfw_update__create_tables'
            )
        );
    }

    if ( ! empty($results['error']) ) {
        return $results;
    }

    return array('error' => 'Files download not completed.');
}

/**
 * Queue stage. Create SFW origin tables to make sure they are exists.
 * @return array[]|bool|string[]
 */
function apbct_sfw_update__create_tables($direct_update = false, $return_new_tables_names = false)
{
    global $apbct, $wpdb;
    // Preparing database infrastructure

    // Creating SFW tables to make sure that they are exists
    $db_tables_creator = new DbTablesCreator();

    //common table
    $common_table_name = $wpdb->base_prefix . Schema::getSchemaTablePrefix() . 'sfw';
    $db_tables_creator->createTable($common_table_name);
    $apbct->data['sfw_common_table_name'] = $common_table_name;
    //personal table
    $table_name_personal = $apbct->db_prefix . Schema::getSchemaTablePrefix() . 'sfw_personal';
    $db_tables_creator->createTable($table_name_personal);
    $apbct->data['sfw_personal_table_name'] = $table_name_personal;
    //ua table
    $personal_ua_bl_table_name = $apbct->db_prefix . Schema::getSchemaTablePrefix() . 'ua_bl';
    $db_tables_creator->createTable($personal_ua_bl_table_name);
    $apbct->data['sfw_personal_ua_bl_table_name'] = $personal_ua_bl_table_name;

    $apbct->saveData();

    if ( $return_new_tables_names ) {
        return array(
            'sfw_common_table_name' => $common_table_name,
            'sfw_personal_table_name' => $table_name_personal,
            'sfw_personal_ua_bl_table_name' => $personal_ua_bl_table_name,
            );
    }

    return $direct_update ? true : array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__create_temp_tables',
        )
    );
}

/**
 * Queue stage. Create SFW temporary tables. They will replace origin tables after update.
 * @return array[]|bool
 */
function apbct_sfw_update__create_temp_tables($direct_update = false)
{
    global $apbct;

    // Create common table
    $result = SFW::createTempTables(DB::getInstance(), $apbct->data['sfw_common_table_name']);
    if ( ! empty($result['error']) ) {
        return $result;
    }
    // Create personal table
    $result = SFW::createTempTables(DB::getInstance(), $apbct->data['sfw_personal_table_name']);
    if ( ! empty($result['error']) ) {
        return $result;
    }

    $result__clear_db = AntiCrawler::clearDataTable(
        \Cleantalk\ApbctWP\DB::getInstance(),
        APBCT_TBL_AC_UA_BL
    );

    if ( ! empty($result__clear_db['error']) ) {
        return $result__clear_db['error'];
    }

    return $direct_update ? true : array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__process_files',
        )
    );
}

/**
 * Queue stage. Process all of downloaded multifiles that collected to the update folder.
 * @return array[]|int|string[]|null
 */
function apbct_sfw_update__process_files()
{
    global $apbct;

    // get list of files in the upd folder
    $files = glob($apbct->fw_stats['updating_folder'] . '/*csv.gz');
    $files = array_filter($files, static function ($element) {
        return strpos($element, 'list') !== false;
    });

    if ( count($files) ) {
        reset($files);
        $concrete_file = current($files);

        //get direction on how the file should be processed (common/personal)
        if (
            // we should have a personal list id (hash) to make sure the file belongs to private lists
            !empty($apbct->fw_stats['personal_lists_url_id'])
            && strpos($concrete_file, $apbct->fw_stats['personal_lists_url_id']) !== false
        ) {
            $direction = 'personal';
        } elseif (
            // we should have a common list id (hash) to make sure the file belongs to common lists
            !empty($apbct->fw_stats['common_lists_url_id'])
            && strpos($concrete_file, $apbct->fw_stats['common_lists_url_id']) !== false ) {
            $direction = 'common';
        } else {
            // no id found in fw_stats or file namse does not contain any of them
            return array('error' => 'SFW_DIRECTION_FAILED');
        }

        // do proceed file with networks itself
        if ( strpos($concrete_file, 'bl_list') !== false ) {
            //$result = apbct_sfw_update__process_file($concrete_file, $direction);
            $result = SFWUpdateHelper::processFile($concrete_file, $direction);
        }

        // do proceed ua file
        if ( strpos($concrete_file, 'ua_list') !== false ) {
            $result = SFWUpdateHelper::processUA($concrete_file);
        }

        // do proceed checking file
        if ( strpos($concrete_file, 'ck_list') !== false ) {
            $result = SFWUpdateHelper::processCK($concrete_file, $direction);
        }

        if ( ! empty($result['error']) ) {
            return $result;
        }

        $apbct->fw_stats['firewall_update_percent'] = round(100 / count($files), 2);
        $apbct->save('fw_stats');

        return array(
            'next_stage' => array(
                'name' => 'apbct_sfw_update__process_files',
            )
        );
    }

    return array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__process_exclusions',
        )
    );
}

/**
 * Queue stage. Process hardcoded exclusion to the SFW temp table.
 * @return array[]|string[]|bool
 */
function apbct_sfw_update__process_exclusions($direct_update = false)
{
    global $apbct;

    $result = SFW::updateWriteToDbExclusions(
        DB::getInstance(),
        APBCT_TBL_FIREWALL_DATA_PERSONAL . '_temp'
    );

    if ( ! empty($result['error']) ) {
        return array('error' => 'EXCLUSIONS: ' . $result['error']);
    }

    if ( ! is_int($result) ) {
        return array('error' => 'EXCLUSIONS: WRONG_RESPONSE update__write_to_db__exclusions');
    }

    /**
     * Update expected_networks_count
     */
    if ( $result > 0 ) {
        $apbct->fw_stats['expected_networks_count_personal'] += $result;
        $apbct->save('fw_stats');
    }

    return $direct_update ? true : array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__end_of_update__renaming_tables',
            'accepted_tries' => 1
        )
    );
}

/**
 * Queue stage. Delete origin tables and rename temporary tables.
 * @return array|array[]|string[]|bool
 */
function apbct_sfw_update__end_of_update__renaming_tables($direct_update = false)
{
    global $apbct;

    $check = SFWUpdateHelper::checkTablesIntegrityBeforeRenaming($apbct->data['sfw_load_type']);

    if ( !empty($check['error']) ) {
        return array('error' => $check['error']);
    }

    $apbct->fw_stats['update_mode'] = 1;
    $apbct->save('fw_stats');
    usleep(10000);

    // REMOVE AND RENAME
    try {
        SFWUpdateHelper::removeAndRenameSfwTables($apbct->data['sfw_load_type']);
    } catch (\Exception $e) {
        $apbct->fw_stats['update_mode'] = 0;
        $apbct->save('fw_stats');
        return array('error' => $e->getMessage());
    }

    $apbct->fw_stats['update_mode'] = 0;
    $apbct->save('fw_stats');

    return $direct_update ? true : array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__end_of_update__checking_data',
            'accepted_tries' => 1
        )
    );
}

/**
 * Queue stage. Check data after all the SFW update actions.
 * @return array|array[]|string[]|bool
 */
function apbct_sfw_update__end_of_update__checking_data($direct_update = false)
{
    global $apbct, $wpdb;

    try {
        SFWUpdateHelper::checkTablesIntegrityAfterRenaming($apbct->data['sfw_load_type']);
    } catch (\Exception $e) {
        return array('error' => $e->getMessage());
    }

    $apbct->stats['sfw']['entries'] = $wpdb->get_var('SELECT COUNT(*) FROM ' . $apbct->data['sfw_common_table_name']);
    $apbct->stats['sfw']['entries_personal'] = $wpdb->get_var('SELECT COUNT(*) FROM ' . $apbct->data['sfw_personal_table_name']);
    $apbct->save('stats');

    /**
     * Checking the integrity of the sfw database update
     */
    if ( in_array($apbct->data['sfw_load_type'], array('all','common'))
        && isset($apbct->stats['sfw']['entries'])
        && ($apbct->stats['sfw']['entries'] != $apbct->fw_stats['expected_networks_count'] ) ) {
        return array(
            'error' =>
                'The discrepancy between the amount of data received for the update and in the final table: '
                . $apbct->data['sfw_common_table_name']
                . '. RECEIVED: ' . $apbct->fw_stats['expected_networks_count']
                . '. ADDED: ' . $apbct->stats['sfw']['entries']
        );
    }

    if ( in_array($apbct->data['sfw_load_type'], array('all','personal'))
        && isset($apbct->stats['sfw']['entries_personal'])
        && ( $apbct->stats['sfw']['entries_personal'] != $apbct->fw_stats['expected_networks_count_personal'] ) ) {
        return array(
            'error' =>
                'The discrepancy between the amount of data received for the update and in the final table: '
                . $apbct->data['sfw_personal_table_name']
                . '. RECEIVED: ' . $apbct->fw_stats['expected_networks_count_personal']
                . '. ADDED: ' . $apbct->stats['sfw']['entries_personal']
        );
    }

    return $direct_update ? true : array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__end_of_update__updating_stats',
            'accepted_tries' => 1
        )
    );
}

/**
 * Queue stage. Update stats.
 * @param $direct_update
 * @return array[]
 */
function apbct_sfw_update__end_of_update__updating_stats($direct_update = false)
{
    global $apbct;

    $is_first_updating = ! $apbct->stats['sfw']['last_update_time'];
    $apbct->stats['sfw']['last_update_time'] = time();
    $apbct->stats['sfw']['last_update_way']  = $direct_update ? 'Direct update' : 'Queue update';
    $apbct->save('stats');

    return array(
        'next_stage' => array(
            'name' => 'apbct_sfw_update__end_of_update',
            'accepted_tries' => 1,
            'args' => $is_first_updating
        )
    );
}

/**
 * Final queue stage. Reset all misc data and set new cron.
 * @param $is_first_updating
 * @return true
 */
function apbct_sfw_update__end_of_update($is_first_updating = false)
{
    global $apbct;

    // Delete update errors
    $apbct->errorDelete('sfw_update', true);

    // Running sfw update once again in 12 min if entries is < 4000
    if ( $is_first_updating &&
        $apbct->stats['sfw']['entries'] < 4000
    ) {
        wp_schedule_single_event(time() + 720, 'apbct_sfw_update__init');
    }

    $cron = new Cron();
    $cron->updateTask('sfw_update', 'apbct_sfw_update__init', $apbct->stats['sfw']['update_period']);
    $cron->removeTask('sfw_update_checker');

    SFWUpdateHelper::removeUpdFolder($apbct->fw_stats['updating_folder']);

    // Reset all FW stats
    $apbct->sfw_update_sentinel->clearSentinelData();
    $apbct->fw_stats['firewall_update_percent'] = 0;
    $apbct->fw_stats['firewall_updating_id']    = null;
    $apbct->fw_stats['expected_networks_count'] = false;
    $apbct->fw_stats['expected_ua_count'] = false;
    $apbct->save('fw_stats');

    return true;
}

/**
 * Cron task handler.
 * @return array|bool|int|string|string[]
 */
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

/**
 * * * * * * SFW COMMON ACTIONS * * * * * *
 */

function apbct_sfw__clear()
{
    global $apbct, $wpdb;

    $wpdb->query('DELETE FROM ' . APBCT_TBL_FIREWALL_DATA . ';');

    $apbct->stats['sfw']['entries'] = 0;
    $apbct->save('stats');
}

/**
 * Send SFW logs to the cloud.
 * @param $api_key
 * @return array|bool|int[]|string[]
 */
function ct_sfw_send_logs($api_key = '')
{
    global $apbct;

    $api_key = ! empty($apbct->api_key) ? $apbct->api_key : $api_key;

    if (
        time() - $apbct->stats['sfw']['sending_logs__timestamp'] < 180 ||
        empty($api_key) ||
        $apbct->settings['sfw__enabled'] != 1
    ) {
        return true;
    }

    $apbct->stats['sfw']['sending_logs__timestamp'] = time();
    $apbct->save('stats');

    $result = SFW::sendLog(
        DB::getInstance(),
        APBCT_TBL_FIREWALL_LOG,
        $api_key
    );

    if ( empty($result['error']) ) {
        $apbct->stats['sfw']['last_send_time']   = time();
        $apbct->stats['sfw']['last_send_amount'] = TT::getArrayValueAsInt($result, 'rows');
        $apbct->errorDelete('sfw_send_logs', true);
        $apbct->save('stats');
    }

    return $result;
}

/**
 * Handle SFW private_records remote call.
 * @param $action
 * @param null|string $test_data
 * @return string JSON string of results
 * @throws Exception
 */
function apbct_sfw_private_records_handler($action, $test_data = null)
{

    $error = 'sfw_private_records_handler: ';

    if ( !empty($action) && (in_array($action, array('add', 'delete'))) ) {
        $metadata = !empty($test_data) ? TT::toString($test_data) : TT::toString(Post::get('metadata'));

        if ( !empty($metadata) ) {
            $metadata = json_decode(stripslashes($metadata), true);
            if ( $metadata === 'NULL' || $metadata === null ) {
                throw new InvalidArgumentException($error . 'metadata JSON decoding failed');
            }
        } else {
            throw new InvalidArgumentException($error . 'metadata is empty');
        }

        foreach ( $metadata as $_key => &$row ) {
            $row = explode(',', $row);
            //do this to get info more obvious
            $metadata_assoc_array = array(
                'network' => TT::getArrayValueAsInt($row, 0),
                'mask' => TT::getArrayValueAsInt($row, 1),
                'status' => isset($row[2]) ? TT::toInt($row[2]) : null
            );
            //validate
            $validation_error = '';
            if ( $metadata_assoc_array['network'] === 0
                || $metadata_assoc_array['network'] > 4294967295
            ) {
                $validation_error = 'metadata validate failed on "network" value';
            }
            if ( $metadata_assoc_array['mask'] === 0
                || $metadata_assoc_array['mask'] > 4294967295
            ) {
                $validation_error = 'metadata validate failed on "mask" value';
            }
            //only for adding
            if ( $action === 'add' ) {
                if ( $metadata_assoc_array['status'] !== 1 && $metadata_assoc_array['status'] !== 0 ) {
                    $validation_error = 'metadata validate failed on "status" value';
                }
            }

            if ( !empty($validation_error) ) {
                throw new InvalidArgumentException($error . $validation_error);
            }
            $row = $metadata_assoc_array;
        }
        unset($row);

        //method selection
        if ( $action === 'add' ) {
            $handler_output = SFW::privateRecordsAdd(
                DB::getInstance(),
                SFW::getSFWTablesNames()['sfw_personal_table_name'],
                $metadata
            );
        } elseif ( $action === 'delete' ) {
            $handler_output = SFW::privateRecordsDelete(
                DB::getInstance(),
                SFW::getSFWTablesNames()['sfw_personal_table_name'],
                $metadata
            );
        } else {
            $error .= 'unknown action name: ' . $action;
            throw new InvalidArgumentException($error);
        }
    } else {
        throw new InvalidArgumentException($error . 'empty action name');
    }

    return json_encode(array('OK' => $handler_output));
}

/**
 * @param $blog_id
 * @param $_drop
 *
 * @return void
 * @psalm-suppress UnusedParam
 */
function apbct_sfw__delete_tables($blog_id)
{
    global $wpdb;

    $initial_blog = get_current_blog_id();

    switch_to_blog($blog_id);
    $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'cleantalk_sfw`;');       // Deleting SFW data
    $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'cleantalk_sfw_logs`;');  // Deleting SFW logs
    $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'cleantalk_ac_log`;');  // Deleting SFW logs
    $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'cleantalk_ua_bl`;');   // Deleting AC UA black lists

    switch_to_blog($initial_blog);
}

function apbct_sfw_update_sentinel__run_watchdog()
{
    global $apbct;
    $apbct->sfw_update_sentinel->runWatchDog();
}
