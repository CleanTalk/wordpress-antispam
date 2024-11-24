<?php

/**
 * Redirects admin to plugin settings after activation.
 * @psalm-suppress UnusedVariable
 */
function apbct_plugin_redirect()
{
    global $apbct;
    wp_suspend_cache_addition(true);
    if (
        get_option('ct_plugin_do_activation_redirect', false) &&
        delete_option('ct_plugin_do_activation_redirect') &&
        ! Get::get('activate-multi')
    ) {
        ct_account_status_check(null, false);
        apbct_sfw_update__init(3); // Updating SFW
        wp_redirect($apbct->settings_link);
    }
    wp_suspend_cache_addition(false);
}

/**
 * @param string $data
 * @psalm-suppress UnusedVariable
 */
function apbct_write_js_errors($data)
{
    if (!is_string($data) || empty($data)) {
        return false;
    }
    $tmp = substr($data, strlen('_ct_no_cookie_data_'));
    $errors = json_decode(base64_decode($tmp), true);
    if (!isset($errors['ct_js_errors'])) {
        return false;
    }
    $errors = $errors['ct_js_errors'];
    $exist_errors = get_option(APBCT_JS_ERRORS);

    if (!$exist_errors) {
        return update_option(APBCT_JS_ERRORS, $errors);
    }

    $errors_collection_msgs = [];
    foreach ($exist_errors as $err_index => $err_value) {
        array_push($errors_collection_msgs, $err_value['err']['msg']);
    }

    foreach ($errors as $err_index => $err_value) {
        if (!in_array($err_value['err']['msg'], $errors_collection_msgs)) {
            array_push($exist_errors, $err_value);
        }
    }

    return update_option(APBCT_JS_ERRORS, $exist_errors);
}

/**
 * Add event to the stats
 *
 * @param $event_type
 * @psalm-suppress  UnusedVariable
 */
function ct_add_event($event_type)
{
    global $apbct, $cleantalk_executed;

    //
    // To migrate on the new version of ct_add_event().
    //
    switch ( $event_type ) {
        case '0':
            $event_type = 'no';
            break;
        case '1':
            $event_type = 'yes';
            break;
    }

    $current_hour = (int)date('G');

    // Updating current hour
    if ( $current_hour != $apbct->data['current_hour'] ) {
        $apbct->data['current_hour']                  = $current_hour;
        $apbct->data['array_accepted'][$current_hour] = 0;
        $apbct->data['array_blocked'][$current_hour]  = 0;
    }

    //Add 1 to counters
    if ( $event_type === 'yes' ) {
        $apbct->data['array_accepted'][$current_hour]++;
        $apbct->data['admin_bar__all_time_counter']['accepted']++;
        $apbct->data['user_counter']['accepted']++;
    }
    if ( $event_type === 'no' ) {
        $apbct->data['array_blocked'][$current_hour]++;
        $apbct->data['admin_bar__all_time_counter']['blocked']++;
        $apbct->data['user_counter']['blocked']++;
    }

    $apbct->saveData();

    $cleantalk_executed = true;
}

/**
 * return new cookie value
 */
function ct_get_cookie()
{
    $ct_checkjs_key = ct_get_checkjs_value();
    print $ct_checkjs_key;
    die();
}

/**
 * Cron task handler. Clear anti-flood table.
 * @return void
 */
function apbct_antiflood__clear_table()
{
    global $apbct;

    if ( $apbct->settings['sfw__anti_flood'] || $apbct->settings['sfw__anti_crawler'] ) {
        $anti_flood = new AntiFlood(
            APBCT_TBL_FIREWALL_LOG,
            APBCT_TBL_AC_LOG,
            array(
                'chance_to_clean' => 100,
            )
        );
        $anti_flood->setDb(DB::getInstance());
        $anti_flood->clearTable();
        unset($anti_flood);

        // Clear table APBCT_TBL_AC_LOG once a day
        $anticrawler = new AntiCrawler(
            APBCT_TBL_FIREWALL_LOG,
            APBCT_TBL_AC_LOG
        );
        $anticrawler->setDb(DB::getInstance());
        $anticrawler->clearTable();
        unset($anticrawler);
    }
}

/**
 * Putting WordPress to maintenance mode.
 * For given duration in seconds
 *
 * @param $duration
 *
 * @return bool
 */
function apbct_maintenance_mode__enable($duration)
{
    apbct_maintenance_mode__disable();
    $content = "<?php\n\n"
               . '$upgrading = ' . (time() - (60 * 10) + $duration) . ';';

    return (bool)file_put_contents(ABSPATH . '.maintenance', $content);
}

/**
 * Disabling maintenance mode by deleting .maintenance file.
 *
 * @return void
 */
function apbct_maintenance_mode__disable()
{
    $maintenance_file = ABSPATH . '.maintenance';
    if ( file_exists($maintenance_file) ) {
        unlink($maintenance_file);
    }
}

/**
 * @param $stage
 * @param $result
 * @param array $response
 *
 * @return void
 */
function apbct_update__outputResult($stage, $result, $response = array())
{
    $response['stage'] = $stage;
    $response['error'] = isset($response['error']) ? $response['error'] : '';

    if ( $result === true ) {
        $result = 'OK';
    }
    if ( $result === false ) {
        $result = 'FAIL';
    }

    $response['error'] = $response['error'] ?: '';
    $response['error'] = $result !== 'OK' && empty($response['error']) ? $result : $response['error'];
    $response['agent'] = APBCT_AGENT;

    echo \Cleantalk\ApbctWP\Escape::escHtml($result . ' ' . json_encode($response));

    if ( $result === 'FAIL' ) {
        die();
    }

    echo '<br>';
}

/**
 * Getting brief data
 *
 * @param null|string $api_key
 */
function cleantalk_get_brief_data($api_key = null)
{
    global $apbct;

    $api_key = is_null($api_key) ? $apbct->api_key : $api_key;

    $apbct->data['brief_data'] = API::methodGetAntispamReportBreif($api_key);

    # expanding data about the country
    if ( isset($apbct->data['brief_data']['top5_spam_ip']) && ! empty($apbct->data['brief_data']['top5_spam_ip']) ) {
        foreach ( $apbct->data['brief_data']['top5_spam_ip'] as $key => $ip_data ) {
            $ip         = $ip_data[0];
            $ip_data[1] = array(
                'country_name' => 'Unknown',
                'country_code' => 'cleantalk'
            );

            if ( isset($ip) ) {
                $country_data       = TT::toArray(API::methodIpInfo($ip));
                $country_data_clear = current($country_data);

                if (
                    is_array($country_data_clear) &&
                    isset($country_data_clear['country_name']) &&
                    isset($country_data_clear['country_code'])
                ) {
                    $ip_data[1] = array(
                        'country_name' => $country_data_clear['country_name'],
                        'country_code' => ( ! preg_match(
                            '/[^A-Za-z0-9]/',
                            $country_data_clear['country_code']
                        )) ? $country_data_clear['country_code'] : 'cleantalk'
                    );
                }
            }

            $apbct->data['brief_data']['top5_spam_ip'][$key] = $ip_data;
        }
    }

    $apbct->saveData();
}

/**
 * Delete cookie for admin trial notice
 */
function apbct__hook__wp_logout__delete_trial_notice_cookie()
{
    if ( ! headers_sent() ) {
        Cookie::setNativeCookie('ct_trial_banner_closed', '', time() - 3600);
    }
}

/**
 * Store URLs
 */
function apbct_store__urls()
{
    global $apbct;

    if (
        ! empty($apbct->headers_sent)              // Headers sent
    ) {
        return false;
    }

    if ( $apbct->settings['misc__store_urls'] && empty($apbct->flags__url_stored) && ! headers_sent() ) {
        // URLs HISTORY
        // Get current url
        $current_url = TT::toString(Server::get('HTTP_HOST'))
            . TT::toString(Server::get('REQUEST_URI'));
        $current_url = $current_url ? substr($current_url, 0, 128) : 'UNKNOWN';
        $site_url    = parse_url(TT::toString(get_option('home')), PHP_URL_HOST);

        // Get already stored URLs
        $urls_json = TT::toString(RequestParameters::getCommonStorage('apbct_urls'));
        $urls = !empty($urls_json) ? json_decode($urls_json, true) : array();
        $urls = ! is_array($urls) ? [] : $urls;

        $urls[$current_url][] = time();

        // Saving only latest 5 visit for each of 5 last urls
        $urls_count_to_keep = 5;
        $visits_to_keep = 5;

        //Rotating.
        $urls[$current_url] = count($urls[$current_url]) > $visits_to_keep
            ? array_slice(
                $urls[$current_url],
                1,
                $visits_to_keep
            )
            : $urls[$current_url];
        $urls               = count($urls) > $urls_count_to_keep
            ? array_slice($urls, 1, $urls_count_to_keep)
            : $urls;

        // Saving
        RequestParameters::setCommonStorage('apbct_urls', json_encode($urls, JSON_UNESCAPED_SLASHES));


        // REFERER
        // Get current referer
        $new_site_referer = TT::toString(Server::get('HTTP_REFERER'));
        $new_site_referer = $new_site_referer !== '' ? $new_site_referer : 'UNKNOWN';

        // Get already stored referer
        $site_referer = Cookie::get('apbct_site_referer');

        // Save if empty
        if (
            $site_url &&
            (
                ! $site_referer ||
                parse_url($new_site_referer, PHP_URL_HOST) !== Server::get('HTTP_HOST')
            ) && $apbct->data['cookies_type'] === 'native'
        ) {
            Cookie::set('apbct_site_referer', $new_site_referer, time() + 86400 * 3, '/', $site_url, null, true, 'Lax', true);
        }

        $apbct->flags__url_stored = true;
    }
}

/**
 * Set Cookies test for cookie test
 * Sets cookies with params timestamp && landing_timestamp && previous_referer
 * Sets test cookie with all other cookies
 * @return bool
 */
function apbct_cookie()
{
    global $apbct;

    if (
        ! empty($apbct->flags__cookies_setuped) || // Cookies already set
        ! empty($apbct->headers_sent) ||             // Headers sent
        Post::get('fusion_login_box') // Avada Fusion registration form exclusion
    ) {
        return false;
    }

    // Prevent headers sent error
    if ( headers_sent($file, $line) ) {
        $apbct->headers_sent        = true;
        $apbct->headers_sent__hook  = current_filter();
        $apbct->headers_sent__where = $file . ':' . $line;

        return false;
    }

    // Cookie names to validate
    $cookie_test_value = array(
        'cookies_names' => array(),
        'check_value'   => $apbct->api_key,
    );

    // We need to skip the domain attribute for prevent including the dot to the cookie's domain on the client.
    $domain = '';

    // Submit time
    if ( empty($_POST) || Post::get('action') === 'apbct_set_important_parameters' ) {
        $apbct_timestamp = time();
        RequestParameters::set('apbct_timestamp', (string)$apbct_timestamp, true);
        $cookie_test_value['cookies_names'][] = 'apbct_timestamp';
        $cookie_test_value['check_value']     .= $apbct_timestamp;
    }

    // Landing time
    $site_landing_timestamp = RequestParameters::get('apbct_site_landing_ts', true);

    if ( ! $site_landing_timestamp ) {
        $site_landing_timestamp = time();
        RequestParameters::set('apbct_site_landing_ts', TT::toString($site_landing_timestamp), true);
    }

    if ($apbct->data['cookies_type'] === 'native') {
        $http_referrer = TT::toString(Server::get('HTTP_REFERER'));
        // Previous referer
        if ( $http_referrer ) {
            Cookie::set('apbct_prev_referer', $http_referrer, 0, '/', $domain, null, true, 'Lax', true);
            $cookie_test_value['cookies_names'][] = 'apbct_prev_referer';
            $cookie_test_value['check_value']     .= $http_referrer;
        }

        // Page hits
        // Get
        $page_hits = TT::toInt(Cookie::get('apbct_page_hits'));

        // Set / Increase
        // todo if cookies disabled there is no way to keep this data without DB:( always will be 1
        $page_hits = $page_hits ? $page_hits + 1 : 1;

        Cookie::set('apbct_page_hits', (string)$page_hits, 0, '/', $domain, null, true, 'Lax', true);

        $cookie_test_value['cookies_names'][] = 'apbct_page_hits';
        $cookie_test_value['check_value']     .= $page_hits;
    }

    // Cookies test
    $cookie_test_value['check_value'] = md5($cookie_test_value['check_value']);
    if ( $apbct->data['cookies_type'] !== 'alternative' ) {
        Cookie::set('apbct_cookies_test', urlencode(json_encode($cookie_test_value)), 0, '/', $domain, null, true);
    }

    $apbct->flags__cookies_setuped = true;

    return $apbct->flags__cookies_setuped;
}

/**
 * Cookies test for sender
 * Also checks for valid timestamp in $_COOKIE['apbct_timestamp'] and other apbct_ COOKIES
 * @return null|int null|0|1
 * @throws JsonException
 */
function apbct_cookies_test()
{
    global $apbct;

    if ( $apbct->data['cookies_type'] !== 'native' || Cookie::$force_alt_cookies_global) {
        return 1;
    }

    if ( Cookie::get('apbct_cookies_test') ) {
        $apbct_cookies_test = TT::toString(Cookie::get('apbct_cookies_test'));
        $cookie_test = json_decode(urldecode($apbct_cookies_test), true);

        if ( ! is_array($cookie_test) ) {
            return 0;
        }

        $check_string = $apbct->api_key;
        // generate value
        $cookie_names = TT::getArrayValueAsArray($cookie_test, 'cookies_names');
        foreach ( $cookie_names as $cookie_name ) {
            $check_string .= Cookie::get($cookie_name);
        }
        // check generated value with current cookie
        $check_value = TT::getArrayValueAsString($cookie_test, 'check_value');
        if ( $check_value === md5($check_string) ) {
            return 1;
        }

        return 0;
    }

    return null;
}

/**
 * Gets submit time
 * Uses Cookies with check via apbct_cookies_test()
 * @return null|int
 * @throws JsonException
 */
function apbct_get_submit_time()
{
    $apbct_timestamp = (int) RequestParameters::get('apbct_timestamp', true);

    return apbct_cookies_test() === 1 && $apbct_timestamp !== 0 ? time() - $apbct_timestamp : null;
}

/**
 * Inner function - Account status check. Scheduled in 1800 seconds for default!
 * @param $api_key
 * @param $process_errors
 * @return bool
 */
function ct_account_status_check($api_key = null, $process_errors = true)
{
    global $apbct;

    $api_key = $api_key ?: $apbct->api_key;
    $result  = API::methodNoticePaidTill(
        $api_key,
        preg_replace('/http[s]?:\/\//', '', get_option('home'), 1),
        ! is_main_site() && $apbct->white_label ? 'anti-spam-hosting' : 'antispam'
    );

    if ( empty($result['error']) || ! empty($result['valid']) ) {
        // Notices
        $apbct->data['notice_show']         = TT::getArrayValueAsInt($result, 'show_notice', 0);
        $apbct->data['notice_renew']        = TT::getArrayValueAsInt($result, 'renew', 0);
        $apbct->data['notice_trial']        = TT::getArrayValueAsInt($result, 'trial', 0);
        $apbct->data['notice_review']       = TT::getArrayValueAsInt($result, 'show_review', 0);
        $apbct->data['notice_auto_update']  = TT::getArrayValueAsInt($result, 'show_auto_update_notice', 0);

        // Other
        $apbct->data['service_id']          = TT::getArrayValueAsInt($result, 'service_id', 0);
        $apbct->data['user_id']             = TT::getArrayValueAsInt($result, 'user_id', 0);
        $apbct->data['valid']               = TT::getArrayValueAsInt($result, 'valid', 0);
        $apbct->data['moderate']            = TT::getArrayValueAsInt($result, 'moderate', 0);
        $apbct->data['ip_license']          = TT::getArrayValueAsInt($result, 'ip_license', 0);
        $apbct->data['spam_count']          = TT::getArrayValueAsInt($result, 'spam_count', 0);
        $apbct->data['auto_update']         = TT::getArrayValueAsInt($result, 'auto_update_app', 0);
        $apbct->data['user_token']          = TT::getArrayValueAsString($result, 'user_token', '');
        $apbct->data['license_trial']       = TT::getArrayValueAsInt($result, 'license_trial', 0);
        $apbct->data['account_name_ob']     = TT::getArrayValueAsString($result, 'account_name_ob', '');
        $apbct->data['moderate_ip']         = isset($result['moderate_ip'], $result['ip_license']) ?
            TT::getArrayValueAsInt($result, 'moderate_ip', 0)
            : 0;

        //todo:temporary solution for description, until we found the way to transfer this from cloud
        if (defined('APBCT_WHITELABEL_PLUGIN_DESCRIPTION')) {
            $result['wl_antispam_description'] = APBCT_WHITELABEL_PLUGIN_DESCRIPTION;
        }

        //todo:temporary solution for FAQ
        if (defined('APBCT_WHITELABEL_FAQ_LINK')) {
            $result['wl_faq_url'] = APBCT_WHITELABEL_FAQ_LINK;
        }

        if ( isset($result['wl_status']) && $result['wl_status'] === 'ON' ) {
            $apbct->data['wl_mode_enabled'] = true;
            $apbct->data['wl_brandname']     = isset($result['wl_brandname'])
                ? Sanitize::cleanTextField($result['wl_brandname'])
                : $apbct->default_data['wl_brandname'];
            $apbct->data['wl_url']           = isset($result['wl_url'])
                ? Sanitize::cleanUrl($result['wl_url'])
                : $apbct->default_data['wl_url'];
            $apbct->data['wl_support_url']   = isset($result['wl_support_url'])
                ? Sanitize::cleanUrl($result['wl_support_url'])
                : $apbct->default_data['wl_support_url'];
            $apbct->data['wl_support_faq']   = isset($result['wl_faq_url'])
                ? Sanitize::cleanUrl($result['wl_faq_url'])
                //important, if missed get this from already set wl_support_url for now
                : $apbct->data['wl_support_url'];
            $apbct->data['wl_support_email'] = isset($result['wl_support_email'])
                ? Sanitize::cleanEmail($result['wl_support_email'])
                : $apbct->default_data['wl_support_email'];
            $plugin_data_wl = get_file_data('cleantalk-spam-protect/cleantalk.php', array('Description' => 'Description'));
            $plugin_data_wl = is_array($plugin_data_wl) && isset($plugin_data_wl['Description'])
                ? $plugin_data_wl['Description']
                : 'No description provided';
            $apbct->data['wl_antispam_description']     = isset($result['wl_antispam_description'])
                ? Sanitize::cleanTextField($result['wl_antispam_description'])
                : $plugin_data_wl;
        } else {
            $apbct->data['wl_mode_enabled'] = false;
            $apbct->data['wl_brandname']     = $apbct->default_data['wl_brandname'];
            $apbct->data['wl_url']           = $apbct->default_data['wl_url'];
            $apbct->data['wl_support_faq']   = $apbct->default_data['wl_support_url'];
            $apbct->data['wl_support_url']   = $apbct->default_data['wl_support_url'];
            $apbct->data['wl_support_email'] = $apbct->default_data['wl_support_email'];
        }

        $cron = new Cron();
        $cron->updateTask('check_account_status', 'ct_account_status_check', 86400);

        $apbct->errorDelete('account_check', true);
    } elseif ( $process_errors ) {
        $apbct->errorAdd('account_check', $result);
    }

    if ( ! empty($result['valid']) ) {
        $apbct->data['key_is_ok'] = true;
        $result                   = true;
    } else {
        $apbct->data['key_is_ok'] = false;
        $result                   = false;
    }

    $apbct->saveData();

    return $result;
}

/**
 * Is enable for user group
 *
 * @param WP_User $user
 *
 * @return boolean
 */
function apbct_is_user_enable($user = null)
{
    global $current_user;

    $user = $user !== null ? $user : $current_user;

    return ! (apbct_is_user_role_in(array('administrator', 'editor', 'author'), $user) || apbct_is_super_admin());
}

/**
 * Checks if the current user has role
 *
 * @param array $roles array of strings
 * @param int|string|WP_User|mixed $user User ID to check|user_login|WP_User
 *
 * @return boolean Does the user has this role|roles
 */
function apbct_is_user_role_in($roles, $user = false)
{
    if ( is_numeric($user) && function_exists('get_userdata') ) {
        $user = get_userdata((int)$user);
    }
    if ( is_string($user) && function_exists('get_user_by') ) {
        $user = get_user_by('login', $user);
    }

    if ( ! $user && function_exists('wp_get_current_user') ) {
        $user = wp_get_current_user();
    }

    if ( ! $user ) {
        $user = apbct_wp_get_current_user();
    }

    if ( !is_object($user) || empty($user->ID) ) {
        return false;
    }

    foreach ( (array)$roles as $role ) {
        if ( isset($user->caps[strtolower($role)]) || in_array(strtolower($role), $user->roles) ) {
            return true;
        }
    }

    return false;
}

/**
 * Write $message to the plugin's debug option
 *
 * @param string|array|object $message
 * @param null|string $func
 * @param array $params
 *
 * @return void
 */
function apbct_log($message = 'empty', $func = null, $params = array())
{
    global $apbct;

    $debug = get_option(APBCT_DEBUG);

    $function = $func ?: '';
    $cron     = in_array('cron', $params);
    $data     = in_array('data', $params);
    $settings = in_array('settings', $params);

    if ( is_array($message) || is_object($message) ) {
        $message = print_r($message, true);
    }

    if ( $message ) {
        $debug[date("Y-m-d H:i:s") . microtime(true) . "_ACTION_" . current_filter() . "_FUNCTION_" . $function] = $message;
    }
    if ( $cron ) {
        $debug[date("Y-m-d H:i:s") . microtime(true) . "_ACTION_" . current_filter(
        ) . "_FUNCTION_" . $function . '_cron'] = $apbct->cron;
    }
    if ( $data ) {
        $debug[date("Y-m-d H:i:s") . microtime(true) . "_ACTION_" . current_filter(
        ) . "_FUNCTION_" . $function . '_data'] = $apbct->data;
    }
    if ( $settings ) {
        $debug[date("Y-m-d H:i:s") . microtime(true) . "_ACTION_" . current_filter(
        ) . "_FUNCTION_" . $function . '_settings'] = $apbct->settings;
    }

    update_option(APBCT_DEBUG, $debug);
}

/**
 * Update and rotate statistics with requests execution time
 *
 * @param $exec_time
 */
function apbct_statistics__rotate($exec_time)
{
    global $apbct;

    $requests_counters = array_keys($apbct->stats['requests']);
    if ( !empty($requests_counters) ) {
        // Delete old stats
        $min_request_count_key = min($requests_counters);
        if ( $min_request_count_key < time() - (86400 * 7) ) {
            unset($apbct->stats['requests'][$min_request_count_key]);
        }

        // Create new if newest older than 1 day
        $max_request_count_key = max($requests_counters);
        if ( $max_request_count_key < time() - (86400 * 1) ) {
            $apbct->stats['requests'][time()] = array('amount' => 0, 'average_time' => 0);
        }
    }

    // Update all existing stats
    foreach ( $apbct->stats['requests'] as &$weak_stat ) {
        $weak_stat['average_time'] = ($weak_stat['average_time'] * $weak_stat['amount'] + $exec_time) / ++$weak_stat['amount'];
    }
    unset($weak_stat);

    $apbct->save('stats');
}
