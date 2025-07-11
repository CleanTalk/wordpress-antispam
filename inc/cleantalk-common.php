<?php

use Cleantalk\Antispam\Cleantalk;
use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\Antispam\CleantalkResponse;
use Cleantalk\ApbctWP\API;
use Cleantalk\ApbctWP\CleantalkSettingsTemplates;
use Cleantalk\ApbctWP\Cron;
use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\DTO\GetFieldsAnyDTO;
use Cleantalk\ApbctWP\Firewall\SFW;
use Cleantalk\ApbctWP\GetFieldsAny;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\Honeypot;
use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\ApbctWP\RequestParameters\RequestParameters;
use Cleantalk\Common\TT;

function apbct_array($array)
{
    return new \Cleantalk\Common\Arr($array);
}

$ct_checkjs_frm             = 'ct_checkjs_frm';
$ct_checkjs_register_form   = 'ct_checkjs_register_form';

$apbct_cookie_request_id_label  = 'request_id';
$apbct_cookie_register_ok_label = 'register_ok';

$ct_checkjs_cf7 = 'ct_checkjs_cf7';
$ct_cf7_comment = '';

$ct_checkjs_jpcf = 'ct_checkjs_jpcf';
$ct_jpcf_patched = false;
$ct_jpcf_fields  = array('name', 'email');

// Comment already proccessed
$ct_comment_done = false;

// Comment already proccessed
$ct_signup_done = false;

//Contains registration error
$ct_registration_error_comment = false;

// Default value for JS test
$ct_checkjs_def = 0;

// COOKIE label to store request id for last approved
$ct_approved_request_id_label = 'ct_approved_request_id';

// Last request id approved for publication
$ct_approved_request_id = null;

// Trial notice show time in minutes
$trial_notice_showtime = 10;

// Renew notice show time in minutes
$renew_notice_showtime = 10;

// COOKIE label for WP Landing Page proccessing result
$ct_wplp_result_label = 'ct_wplp_result';

// Flag indicates active JetPack comments
$ct_jp_comments = false;

// WP admin email notice interval in seconds
$ct_admin_notoice_period = 21600;

// Sevice negative comment to visitor.
// It uses for BuddyPress registrations to avoid double checks
$ct_negative_comment = null;

/**
 * Public action 'plugins_loaded' - Loads locale, see http://codex.wordpress.org/Function_Reference/load_plugin_textdomain
 */
function apbct_plugin_loaded()
{
    load_plugin_textdomain('cleantalk-spam-protect', false, APBCT_LANG_REL_PATH);
}

/**
 * Inner function - Request's wrapper for anything
 *
 * @param array Array of parameters:
 *  'message' - string
 *  'example' - string
 *  'checkjs' - int
 *  'sender_email' - string
 *  'sender_nickname' - string
 *  'sender_info' - array
 *  'post_info' - string
 *
 * @return array array('ct'=> Cleantalk, 'ct_result' => CleantalkResponse)
 */
function apbct_base_call($params = array(), $reg_flag = false)
{
    global $cleantalk_executed;

    if ( isset($params['post_info']['comment_type']) && (
        ($params['post_info']['comment_type'] === 'site_search_wordpress') ||
        ($params['post_info']['comment_type'] === 'jetpack_comment')
    )) {
        Cookie::$force_alt_cookies_global = true;
    }

    /* Exclusions */
    if ( $cleantalk_executed ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return array('ct_result' => new CleantalkResponse());
    }

    // URL, IP, Role, Form signs exclusions
    if ( apbct_exclusions_check() ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return array('ct_result' => new CleantalkResponse());
    }

    // Reversed url exclusions. Pass everything except one.
    if ( apbct_exclusions_check__url__reversed() ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return array('ct_result' => new CleantalkResponse());
    }

    global $apbct;

    $cleantalk_executed = true;

    /* Request ID rotation */
    $tmp = array();
    if ( $apbct->plugin_request_ids && ! empty($apbct->plugin_request_ids) ) {
        $plugin_request_id__lifetime = 2;
        foreach ( $apbct->plugin_request_ids as $request_id => $request_time ) {
            if ( time() - $request_time < $plugin_request_id__lifetime ) {
                $tmp[$request_id] = $request_time;
            }
        }
    }
    $apbct->plugin_request_ids = $tmp;
    $apbct->save('plugin_request_ids');

    // Skip duplicate requests
    if (
        isset($apbct->plugin_request_ids[ $apbct->plugin_request_id ]) &&
        current_filter() !== 'woocommerce_registration_errors' && // Prevent skip checking woocommerce registration during checkout
        current_filter() !== 'um_submit_form_register' // Prevent skip checking UltimateMember register
    ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return array('ct_result' => new CleantalkResponse());
    }

    $apbct->plugin_request_ids[ $apbct->plugin_request_id ] = time();
    $apbct->save('plugin_request_ids');
    /* End of Request ID rotation */

    if ( ! $apbct->stats['no_cookie_data_taken'] ) {
        apbct_form__get_no_cookie_data();
    }

    $sender_info = apbct_get_sender_info();

    if (isset($params['sender_info']) && !empty($params['sender_info'])) {
        $sender_info = \Cleantalk\ApbctWP\Helper::arrayMergeSaveNumericKeysRecursive(
            $sender_info,
            (array)$params['sender_info']
        );
    }

    $default_params = array(

        // IPs
        'sender_ip'       => defined('CT_TEST_IP')
            ? CT_TEST_IP
            : \Cleantalk\ApbctWP\Helper::ipGet('remote_addr', false),
        'x_forwarded_for' => \Cleantalk\ApbctWP\Helper::ipGet('x_forwarded_for', false),
        'x_real_ip'       => \Cleantalk\ApbctWP\Helper::ipGet('x_real_ip', false),

        // Misc
        'auth_key'        => $apbct->api_key,
        'js_on'           => apbct_js_test(Sanitize::cleanTextField(Cookie::get('ct_checkjs')), true) ? 1 : apbct_js_test(TT::toString(Post::get('ct_checkjs'))),

        'agent'       => APBCT_AGENT,
        'sender_info' => $sender_info,
        'submit_time' => apbct_get_submit_time(),
    );

    if (!isset($params['post_info']['post_url'])) {
        $params['post_info']['post_url'] = Server::get('HTTP_REFERER');
    }

    // Event Token
    $params['event_token'] = apbct_get_event_token($params);

    if (Cookie::get('typo')) {
        $default_params['sender_info']['typo'] = Cookie::get('typo');
    }

    if (RequestParameters::get('collecting_user_activity_data')) {
        $default_params['sender_info']['collecting_user_activity_data'] = RequestParameters::get('collecting_user_activity_data');
    }

    /**
     * Add exception_action sender email is empty
     */
    if (
        empty($params['sender_email']) &&
        ! isset($params['exception_action']) &&
        // No need to log excluded requests on the direct integrations
        ! empty($params['post_info']['comment_type']) &&
        strpos($params['post_info']['comment_type'], 'contact_form_wordpress_') === false &&
        ! preg_match('/comment$/', $params['post_info']['comment_type']) &&
        ! apbct_is_trackback() &&
        ! defined('XMLRPC_REQUEST')
    ) {
        /**
         * If the constant APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION is defined,
         * it means that the exception action should be disabled for empty email checks.
         *
         * Check all post data option ignore this constant.
         * @since 6.58.99
         */
        if (
            $apbct->service_constants->disable_empty_email_exception->isDefined() &&
            !$apbct->settings['data__general_postdata_test']
        ) {
            $params['exception_action'] = 0;
        } else {
            $params['exception_action'] = 1;
        }
    }
    /**
     * Skip checking excepted requests if the "Log excluded requests" option is disabled.
     */
    if ( isset($params['exception_action']) && $params['exception_action'] == 1 && ! $apbct->settings['exclusions__log_excluded_requests'] ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
        return array('ct_result' => new CleantalkResponse());
    }

    // Honeypot
    if ( isset($params['honeypot_field']) ) {
        $default_params['honeypot_field'] = $params['honeypot_field'];
    } else if ( $apbct->settings['data__honeypot_field'] ) {
        $honeypot = Honeypot::check();
        $params['honeypot_field'] = isset($honeypot['status']) ? $honeypot['status'] : null;

        if ( isset($honeypot['value'], $honeypot['source'], $params['sender_info']) ) {
            $params['sender_info']['honeypot_field_value'] = $honeypot['value'];
            $params['sender_info']['honeypot_field_source'] = $honeypot['source'];
        }
    }

    // Send $_SERVER if couldn't find IP
    if ( empty($default_params['sender_ip']) ) {
        $default_params['sender_info']['server_info'] = $_SERVER;
    }

    $ct         = new Cleantalk();
    $ct_request = new CleantalkRequest(
        \Cleantalk\ApbctWP\Helper::arrayMergeSaveNumericKeysRecursive($default_params, $params)
    );

    // Options store url without scheme because of DB error with ''://'
    $config             = ct_get_server();
    $ct->server_url     = APBCT_MODERATE_URL;
    $ct->work_url       = isset($config['ct_work_url']) && preg_match('/https:\/\/.+/', $config['ct_work_url']) ? $config['ct_work_url'] : null;
    $ct->server_ttl     = isset($config['ct_server_ttl']) ? $config['ct_server_ttl'] : null;
    $ct->server_changed = isset($config['ct_server_changed']) ? $config['ct_server_changed'] : null;

    $start     = microtime(true);
    $ct_result = $reg_flag
        ? @$ct->isAllowUser($ct_request)
        : @$ct->isAllowMessage($ct_request);
    $exec_time = microtime(true) - $start;

    // Statistics
    // Average request time
    apbct_statistics__rotate($exec_time);
    // Last request
    $apbct->stats['last_request']['time']   = time();
    $apbct->stats['last_request']['server'] = $ct->work_url;
    $apbct->save('stats');

    if ( $ct->server_change ) {
        update_option(
            'cleantalk_server',
            array(
                'ct_work_url'       => $ct->work_url,
                'ct_server_ttl'     => $ct->server_ttl,
                'ct_server_changed' => time(),
            )
        );
        $cron = new Cron();
        $cron->updateTask('rotate_moderate', 'apbct_rotate_moderate', 86400); // Rotate moderate server
    }

    //alternative checks and connection report handler
    if ($ct_result instanceof \Cleantalk\Antispam\CleantalkResponse) {
        $ct_result = ct_checks_on_cleantalk_errors($ct_request, $ct_result);
    }

    // Restart submit form counter for failed requests
    if ( is_object($ct_result) && $ct_result->allow == 0 ) {
        apbct_cookie(); // Setting page timer and cookies
        ct_add_event('no');
    } else {
        ct_add_event('yes');
    }


    // Set cookies if it's not.
    if ( empty($apbct->flags__cookies_setuped) ) {
        Cookie::$force_alt_cookies_global = false;
        apbct_cookie();
    }

    //clear POST and REQUEST superglobal from service data
    $_POST = apbct_clear_superglobal_service_data($_POST, 'post');
    $_REQUEST = apbct_clear_superglobal_service_data($_REQUEST, 'request');

    return array('ct' => $ct, 'ct_result' => $ct_result);
}

function apbct_rotate_moderate()
{
    $ct = new Cleantalk();
    $ct->server_url = APBCT_MODERATE_URL;
    $ct->rotateModerate();
    if ( $ct->server_change ) {
        update_option(
            'cleantalk_server',
            array(
                'ct_work_url'       => $ct->work_url,
                'ct_server_ttl'     => $ct->server_ttl,
                'ct_server_changed' => time(),
            )
        );
    }
}

function apbct_exclusions_check($func = null)
{
    global $apbct;

    if ( Post::get('apbct_do_not_exclude') ) {
        return false;
    }

    // Common exclusions
    if (
        apbct_exclusions_check__form_signs($_POST) ||
        apbct_exclusions_check__url() ||
        apbct_is_user_role_in($apbct->settings['exclusions__roles'])
    ) {
        return true;
    }

    // Personal exclusions
    switch ( $func ) {
        case 'ct_contact_form_validate_postdata':
            if (
                (defined('DOING_AJAX') && DOING_AJAX) ||
                apbct_array($_POST)->getKeys('members_search_submit')->result()
            ) {
                return true;
            }
            break;
        case 'ct_contact_form_validate':
            if (
                apbct_array($_POST)->getKeys('members_search_submit')->result()
                || (
                    (int)$apbct->settings['data__protect_logged_in'] === 1
                    && (
                        Post::equal('wpfaction', 'topic_add')
                        || Post::equal('wpfaction', 'post_add')
                        )
                    && apbct_is_plugin_active('wpforo/wpforo.php')
                )
            ) {
                return true;
            }
            break;
        default:
            return false;
    }

    return false;
}

/**
 * Check if the reversed exclusions is set and doesn't match.
 *
 * @return bool
 */
function apbct_exclusions_check__url__reversed()
{
    return defined('APBCT_URL_EXCLUSIONS__REVERSED') &&
           ! Server::hasString('REQUEST_URI', APBCT_URL_EXCLUSIONS__REVERSED);
}

/**
 * Checks if reuqest URI is in exclusion list
 *
 * @return bool
 */
function apbct_exclusions_check__url()
{
    global $apbct;

    if ( ! empty($apbct->settings['exclusions__urls']) ) {
        if ( strpos($apbct->settings['exclusions__urls'], "\r\n") !== false ) {
            $exclusions = explode("\r\n", $apbct->settings['exclusions__urls']);
        } elseif ( strpos($apbct->settings['exclusions__urls'], "\n") !== false ) {
            $exclusions = explode("\n", $apbct->settings['exclusions__urls']);
        } else {
            $exclusions = explode(',', $apbct->settings['exclusions__urls']);
        }

        $rest_url_only_path = apbct_get_rest_url_only_path();
        // Fix for AJAX and WP REST API forms
        $haystack =
            (
                Server::get('REQUEST_URI') === '/wp-admin/admin-ajax.php' ||
                stripos(TT::toString(Server::getString('REQUEST_URI')), '/wp-json/') === 0 ||
                (
                    $rest_url_only_path !== 'index.php' &&
                    stripos(TT::toString(Server::getString('REQUEST_URI')), $rest_url_only_path) === 0
                )
            ) &&
            TT::toString(Server::get('HTTP_REFERER'))
            ? str_ireplace(
                array('http://', 'https://', strval(TT::toString(Server::get('HTTP_HOST')))),
                '',
                TT::toString(Server::get('HTTP_REFERER'))
            )
            : TT::toString(Server::get('REQUEST_URI'));

        if ( $apbct->data['check_exclusion_as_url'] ) {
            $protocol = ! in_array(Server::get('HTTPS'), ['off', '']) || Server::get('SERVER_PORT') == 443 ? 'https://' : 'http://';
            $haystack = $protocol . TT::toString(Server::get('SERVER_NAME')) . TT::toString($haystack);
        }

        $haystack = TT::toString($haystack);

        foreach ( $exclusions as $exclusion ) {
            if (
                (
                    $apbct->settings['exclusions__urls__use_regexp'] &&
                    preg_match('@' . $exclusion . '@', $haystack) === 1
                ) ||
                stripos($haystack, $exclusion) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    return false;
}

/**
 * Check POST array for the exclusion form signs. Listen for array keys or for value in case if key is "action".
 * @param array $form_data The POST array or another filtered array of form data.
 * @return bool True if exclusion found in the keys of array, false otherwise.
 */
function apbct_exclusions_check__form_signs($form_data)
{
    global $apbct;

    if ( ! empty($apbct->settings['exclusions__form_signs']) ) {
        if ( strpos($apbct->settings['exclusions__form_signs'], "\r\n") !== false ) {
            $exclusions = explode("\r\n", $apbct->settings['exclusions__form_signs']);
        } elseif ( strpos($apbct->settings['exclusions__form_signs'], "\n") !== false ) {
            $exclusions = explode("\n", $apbct->settings['exclusions__form_signs']);
        } else {
            $exclusions = explode(',', $apbct->settings['exclusions__form_signs']);
        }

        foreach ( $exclusions as $exclusion ) {
            foreach ($form_data as $key => $value) {
                $haystack = ($key === 'action' || $key === 'data') ? $value : $key;
                if (
                    $haystack === $exclusion ||
                    (is_string($haystack) && stripos($haystack, $exclusion) !== false)  ||
                    (is_string($haystack) && preg_match('@' . $exclusion . '@', $haystack) === 1)
                ) {
                    return true;
                }
            }
        }
        return false;
    }
    return false;
}

/**
 * Inner function - Default data array for senders
 * @return array
 */
function apbct_get_sender_info()
{
    global $apbct;

    // Validate cookie from the backend
    $cookie_is_ok = apbct_cookies_test();

    if ( count($_POST) > 0 ) {
        foreach ( $_POST as $k => $v ) {
            if ( preg_match("/^(ct_check|checkjs).+/", (string)$k) ) {
                $checkjs_data_post = $v;
            }
        }
    }

    // Visible fields processing
    $visible_fields = GetFieldsAny::getVisibleFieldsData();

    // preparation of some parameters when cookies are disabled and data is received from localStorage
    if ($apbct->data['cookies_type'] === 'native') {
        $param_email_check = Cookie::getNativeCookieValue('ct_checked_emails');
        $param_screen_info = Cookie::getNativeCookieValue('ct_screen_info');
    } else {
        $param_email_check = Cookie::get('ct_checked_emails') ? json_encode(
            Cookie::get('ct_checked_emails')
        ) : null;
        $param_screen_info = Cookie::get('ct_screen_info')
            ? json_encode(Cookie::get('ct_screen_info'))
            : null;
    }

    $param_mouse_cursor_positions = Cookie::get('ct_pointer_data');
    $param_pixel_url = Cookie::get('apbct_pixel_url');

    if ($apbct->data['cookies_type'] === 'none') {
        $param_email_check = Cookie::get('ct_checked_emails') ? urldecode(
            TT::toString(Cookie::get('ct_checked_emails'))
        ) : null;
        $param_mouse_cursor_positions = urldecode(TT::toString(Cookie::get('ct_pointer_data')));
        $param_pixel_url = Cookie::get('apbct_pixel_url');
        $param_pixel_url = urldecode(is_string($param_pixel_url) ? $param_pixel_url : '');
        $param_screen_info = Cookie::get('ct_screen_info')
            ? urldecode(TT::toString(Cookie::get('ct_screen_info')))
            : null;
    }

    //cache plugins detection
    $cache_plugins_detected = apbct_is_cache_plugins_exists(true);
    $cache_plugins_detected = empty($cache_plugins_detected) ? false : $cache_plugins_detected;
    $cache_plugins_detected = json_encode($cache_plugins_detected);

    $apbct_urls = RequestParameters::getCommonStorage('apbct_urls');
    $apbct_urls = $apbct_urls ? json_encode(json_decode($apbct_urls, true)) : null;

    $site_landing_ts = RequestParameters::get('apbct_site_landing_ts', true);
    $site_landing_ts = !empty($site_landing_ts) ? TT::toString($site_landing_ts) : null;

    $site_referer = RequestParameters::get('apbct_site_referer', true);
    $site_referer = !empty($site_referer) ? TT::toString($site_referer) : 'UNKNOWN';

    /**
     * Important! Do not use just HTTP only flag here. Page hits are handled on JS side
     * and could be provided via NoCookie hidden field.
     * Also, forms with forced alt cookies does not provide it via hidden field and in the same time other forms do,
     * so we need a flag to know the source.
     * A.G.
     */
    $page_hits = RequestParameters::get('apbct_page_hits', Cookie::$force_alt_cookies_global);
    $page_hits = !empty($page_hits) ? TT::toString($page_hits) : null;

    //Let's keep $data_array for debugging
    $data_array = array(
        'plugin_request_id'         => $apbct->plugin_request_id,
        'wpms'                      => is_multisite() ? 'yes' : 'no',
        'remote_addr'               => \Cleantalk\ApbctWP\Helper::ipGet('remote_addr', false),
        'USER_AGENT'                => Server::get('HTTP_USER_AGENT'),
        'page_url'                  => apbct_sender_info___get_page_url(),
        'cms_lang'                  => substr(get_locale(), 0, 2),
        'ct_options'                => json_encode($apbct->settings, JSON_UNESCAPED_SLASHES),
        'fields_number'             => sizeof($_POST),
        'direct_post'               => $cookie_is_ok === null && apbct_is_post() ? 1 : 0,
        // Raw data to validated JavaScript test in the cloud
        'checkjs_data_cookies'      => Cookie::get('ct_checkjs') ?: null,
        'checkjs_data_post'         => !empty($checkjs_data_post) ? $checkjs_data_post : null,
        // PHP cookies
        'cookies_enabled'           => $cookie_is_ok,
        'data__set_cookies'         => $apbct->settings['data__set_cookies'],
        'data__cookies_type'        => $apbct->data['cookies_type'],
        'REFFERRER'                 => Server::getString('HTTP_REFERER'),
        'REFFERRER_PREVIOUS'        => !empty(Cookie::getString('apbct_prev_referer')) && $cookie_is_ok
            ? Cookie::getString('apbct_prev_referer')
            : null,
        'site_landing_ts'           => $site_landing_ts,
        'page_hits'                 => $page_hits,
        'mouse_cursor_positions'    => $param_mouse_cursor_positions,
        'js_timezone'               => Cookie::get('ct_timezone') ?: null,
        'key_press_timestamp'       => Cookie::get('ct_fkp_timestamp') ?: null,
        'page_set_timestamp'        => Cookie::get('ct_ps_timestamp') ?: null,
        'form_visible_inputs'       => !empty($visible_fields['visible_fields_count'])
            ? $visible_fields['visible_fields_count']
            : null,
        'apbct_visible_fields'      => !empty($visible_fields['visible_fields'])
            ? $visible_fields['visible_fields']
            : null,
        'form_invisible_inputs'     => !empty($visible_fields['invisible_fields_count'])
            ? $visible_fields['invisible_fields_count']
            : null,
        'apbct_invisible_fields'    => !empty($visible_fields['invisible_fields'])
            ? $visible_fields['invisible_fields']
            : null,
        // Misc
        'site_referer'              => $site_referer,
        'source_url'                => $apbct_urls,
        'pixel_url'                 => $param_pixel_url,
        'pixel_setting'             => $apbct->settings['data__pixel'],
        // Debug stuff
        'amp_detected'              => apbct_is_amp_request(),
        'hook'                      => current_filter() ? current_filter() : 'no_hook',
        'headers_sent'              => !empty($apbct->headers_sent) ? $apbct->headers_sent : false,
        'headers_sent__hook'        => !empty($apbct->headers_sent__hook) ? $apbct->headers_sent__hook : 'no_hook',
        'headers_sent__where'       => !empty($apbct->headers_sent__where) ? $apbct->headers_sent__where : false,
        'request_type'              => Server::get('REQUEST_METHOD') ?: 'UNKNOWN',
        'email_check'               => $param_email_check,
        'screen_info'               => $param_screen_info,
        'has_scrolled'              => Cookie::get('ct_has_scrolled') !== ''
            ? json_encode(Cookie::get('ct_has_scrolled'))
            : null,
        'mouse_moved'               => Cookie::get('ct_mouse_moved') !== ''
            ? json_encode(Cookie::get('ct_mouse_moved'))
            : null,
        'emulations_headless_mode'  => Cookie::get('apbct_headless') !== ''
            ? json_encode(Cookie::get('apbct_headless'))
            : null,
        'no_cookie_data_taken'      => isset($apbct->stats['no_cookie_data_taken']) ? $apbct->stats['no_cookie_data_taken'] : null,
        'no_cookie_data_post_source' => isset($apbct->stats['no_cookie_data_post_source'])
            ? $apbct->stats['no_cookie_data_post_source']
            : null,
        'has_key_up' => Cookie::get('ct_has_key_up') !== ''
            ? json_encode(Cookie::get('ct_has_key_up'))
            : null,
        'has_input_focused' => Cookie::get('ct_has_input_focused') !== ''
            ? json_encode(Cookie::get('ct_has_input_focused'))
            : null,
        'cache_plugins_detected' => $cache_plugins_detected,
        //bot detector data
        'bot_detector_fired_form_exclusions' => apbct__bot_detector_get_fired_exclusions(),
        'bot_detector_prepared_form_exclusions' => apbct__bot_detector_get_prepared_exclusion(),
        'bot_detector_frontend_data_log' => apbct__bot_detector_get_fd_log(),
    );

    // Unset cookies_enabled from sender_info if cookies_type === none
    if ($apbct->data['cookies_type'] === 'none') {
        unset($data_array['cookies_enabled']);
    }

    return $data_array;
}

function apbct_sender_info___get_page_url()
{
    if (
        ( apbct_is_ajax() || apbct_is_rest() )
        && Server::get('HTTP_REFERER')
    ) {
        return TT::toString(Server::get('HTTP_REFERER'));
    }
    $protocol = ! empty($_SERVER['HTTPS']) && 'off' !== strtolower($_SERVER['HTTPS']) ? "https://" : "http://";
    return  $protocol . TT::toString(Server::get('SERVER_NAME')) . TT::toString(Server::get('REQUEST_URI'));
}

function apbct_get_pixel_url($direct_call = false)
{
    global $apbct;

    $ip = Helper::ipGet();
    $ip_version = Helper::ipValidate(TT::toString($ip));

    $pixel_hash = md5(
        $ip
        . $apbct->api_key
        . Helper::timeGetIntervalStart(3600 * 3) // Unique for every 3 hours
    );

    //get params for caсhe plugins exclusion detection
    $cache_plugins_detected = apbct_is_cache_plugins_exists(true);
    $cache_exclusion_snippet = '';
    if ( !empty($cache_plugins_detected) ) {
        //NitroPack
        if ( in_array('NitroPack', $cache_plugins_detected) ) {
            $cache_exclusion_snippet = '?gclid=' . $pixel_hash;
        }
    }

    //construct URL
    $server           = get_option('cleantalk_server');
    $server_url       = isset($server['ct_work_url']) ? $apbct->server['ct_work_url'] : APBCT_MODERATE_URL;
    $server_url_with_version = $ip_version === 'v4' ? str_replace('.cleantalk.org', '-v4.cleantalk.org', $server_url) : $server_url;
    $pixel            = '/pixel/' . $pixel_hash . '.gif' . $cache_exclusion_snippet;
    $pixel_url = str_replace('http://', 'https://', $server_url_with_version) . $pixel;

    if ( $direct_call ) {
        return $pixel_url ;
    }

    die($pixel_url);
}

/**
 * Checking email before POST
 */
function apbct_email_check_before_post()
{
    $email = trim(TT::toString(Post::get('email')));

    if ( $email ) {
        $result = \Cleantalk\ApbctWP\API::methodEmailCheck($email);
        if ( isset($result['data']) ) {
            die(json_encode(array('result' => $result['data'])));
        }
        die(json_encode(array('error' => 'ERROR_CHECKING_EMAIL')));
    }
    die(json_encode(array('error' => 'EMPTY_DATA')));
}

/**
 * Checking email before POST
 */
function apbct_email_check_exist_post()
{
    global $apbct;
    $email = trim(TT::toString(Post::get('email')));
    $api_key = $apbct->api_key;

    if ( $email && $api_key ) {
        $result = \Cleantalk\ApbctWP\API::methodEmailCheckExist($email, $api_key);
        if ( isset($result['result']) ) {
            $text_result = '';
            if ( $result['result'] != 'EXISTS' ) {
                $text_result = __('The email doesn`t exist, double check the address. Anti-Spam by CleanTalk.', 'cleantalk-spam-protect');
            } else {
                $text_result = __('The email exists and is good to use! Anti-Spam by CleanTalk', 'cleantalk-spam-protect');
            }
            $result['text_result'] = $text_result;
            die(json_encode(array('result' => $result)));
        }

        die(json_encode(array('error' => 'ERROR_CHECKING_EMAIL')));
    }
    die(json_encode(array('error' => 'EMPTY_DATA')));
}

/**
 * Force protection check bot
 */
function apbct_force_protection_check_bot()
{
    die(\Cleantalk\ApbctWP\Antispam\ForceProtection::getInstance()->checkBot());
}

/**
 * Get ct_get_checkjs_value
 *
 * @return int|string|null
 */
function ct_get_checkjs_value()
{
    global $apbct;

    // Use static JS keys
    if ( $apbct->settings['data__use_static_js_key'] == 1 ) {
        $key = hash('sha256', $apbct->api_key . ct_get_admin_email() . $apbct->salt);
        // Auto detecting. Detected.
    } elseif (
        $apbct->settings['data__use_static_js_key'] == -1 &&
        (apbct_is_cache_plugins_exists() ||
         (apbct_is_post() && isset($apbct->data['cache_detected']) && $apbct->data['cache_detected'] == 1)
        )
    ) {
        $key = hash('sha256', $apbct->api_key . ct_get_admin_email() . $apbct->salt);
        if ( apbct_is_cache_plugins_exists() ) {
            $apbct->data['cache_detected'] = 1;
        }

        $apbct->saveData();
        // Using dynamic JS keys
    } else {
        $keys          = $apbct->data['js_keys'];
        $keys_checksum = md5(json_encode($keys));

        $key             = null;
        $latest_key_time = 0;

        foreach ( $keys as $k => $t ) {
            if (!is_object($t)) {
                // Removing key if it's to old
                if ( time() - $t > $apbct->data['js_keys_store_days'] * 86400 * 7 ) {
                    unset($keys[$k]);
                    continue;
                }

                if ( $t > $latest_key_time ) {
                    $latest_key_time = $t;
                    $key             = $k;
                }
            } else {
                $keys = array();
            }
        }

        // Set new key if the latest key is too old
        if ( time() - $latest_key_time > $apbct->data['js_key_lifetime'] ) {
            $key        = rand();
            $keys[$key] = time();
        }

        // Save keys if they were changed
        if ( md5(json_encode($keys)) != $keys_checksum ) {
            $apbct->data['js_keys'] = $keys;
            // $apbct->saveData();
        }

        $apbct->data['cache_detected'] = 0;

        $apbct->saveData();
    }

    return $key;
}

function apbct_is_cache_plugins_exists($return_names = false)
{
    $out = array();

    $constants_of_cache_plugins = array(
        'WP_ROCKET_VERSION'                           => 'WPRocket',
        'LSCWP_DIR'                                   => 'LiteSpeed Cache',
        'WPFC_WP_CONTENT_BASENAME'                    => 'WP Fastest Cache',
        'W3TC'                                        => 'W3 Total Cache',
        'WPO_VERSION'                                 => 'WP-Optimize – Clean, Compress, Cache',
        'AUTOPTIMIZE_PLUGIN_VERSION'                  => 'Autoptimize',
        'WPCACHEHOME'                                 => 'WP Super Cache',
        'WPHB_VERSION'                                => 'Hummingbird – Speed up, Cache, Optimize Your CSS and JS',
        'CE_FILE'                                     => 'Cache Enabler – WordPress Cache',
        'SiteGround_Optimizer\VERSION'                => 'SG Optimizer',
        'NITROPACK_VERSION'                           => 'NitroPack',
        'TWO_PLUGIN_FILE'                             => '10Web Booster',
        'FLYING_PRESS_VERSION'                        => 'Flying Press',
        'BREEZE_VERSION'                              => 'Breeze',
        'SPEEDYCACHE_VERSION'                         => 'SpeedyCache',
    );

    $classes_of_cache_plugins = array (
        '\RedisObjectCache' => 'Redis',
        '\WP_Rest_Cache_Plugin\Includes\Plugin' => 'Rest Cache'
    );

    $headers = array(
        'HTTP_X_VARNISH' => 'Varnish',
    );

    foreach ($constants_of_cache_plugins as $const => $_text) {
        if ( defined($const) ) {
            $out[] = $_text;
        }
    }

    foreach ($classes_of_cache_plugins as $class => $_text) {
        /**
         * @psalm-suppress DocblockTypeContradiction
         * @psalm-suppress TypeDoesNotContainType
         */
        if ( class_exists($class) ) {
            $out[] = $_text;
        }
    }

    foreach ($headers as $header => $_text) {
        if ( isset($_SERVER[$header]) ) {
            $out[] = $_text;
        }
    }

    return $return_names ? $out : !empty($out);
}

function apbct_is_varnish_cache_exists()
{
    return isset($_SERVER['HTTP_X_VARNISH']);
}

function apbct_is_advanced_cache_exists()
{
    return apbct_is_cache_plugins_exists() && file_exists(untrailingslashit(WP_CONTENT_DIR) . '/advanced-cache.php');
}

function apbct_is_10web_booster_exists()
{
    return apbct_is_cache_plugins_exists() && apbct_is_plugin_active('tenweb-speed-optimizer/tenweb_speed_optimizer.php');
}

/**
 * Inner function - Current site admin e-mail
 * @return    string Admin e-mail
 */
function ct_get_admin_email()
{
    global $apbct;

    if ( ! is_multisite() ) {
        // Not WPMS
        $admin_email = get_option('admin_email');
    } elseif ( is_main_site() || $apbct->network_settings['multisite__work_mode'] != 3) {
        // WPMS - Main site, common account
        $admin_email = get_site_option('admin_email');
    } else {
        // WPMS - Individual account, individual Access key
        $admin_email = get_blog_option(get_current_blog_id(), 'admin_email');
    }

    if ( $apbct->data['account_email'] ) {
        add_filter('apbct_get_api_key_email', function () {
            global $apbct;
            return $apbct->data['account_email'];
        });
    }

    return $admin_email;
}

/**
 * Inner function - Current CleanTalk working server info
 * @return    array Array of server data
 */
function ct_get_server()
{
    $ct_server = get_option('cleantalk_server');
    if ( ! is_array($ct_server) ) {
        $ct_server = array(
            'ct_work_url'       => null,
            'ct_server_ttl'     => null,
            'ct_server_changed' => null
        );
    }

    $ct_server['ct_work_url'] = Sanitize::sanitizeCleantalkServerUrl(TT::getArrayValueAsString($ct_server, 'ct_work_url'));

    return $ct_server;
}

/**
 * @param $url
 *
 * @return string|null
 */
function sanitize_cleantalk_server_url($url)
{
    if (!is_string($url)) {
        return null;
    }
    return preg_match('/^.*(moderate|api).*\.cleantalk.org(?!\.)[\/\\\\]{0,1}/m', $url)
        ? $url
        : null;
}
/**
 * Inner function - Stores ang returns cleantalk hash of current comment
 *
 * @param string New hash or NULL
 *
 * @return    string New hash or current hash depending on parameter
 */
function ct_hash($new_hash = '')
{
    /**
     * Current hash
     */
    static $hash;

    if ( ! empty($new_hash) ) {
        $hash = $new_hash;
    }

    return $hash;
}

/**
 * Inner function - Write manual moderation results to PHP sessions
 *
 * @param string $hash CleanTalk comment hash
 * @param string $message comment_content
 * @param int $allow flag good comment (1) or bad (0)
 *
 * @return    string comment_content w\o cleantalk resume
 */
function ct_feedback($hash, $allow)
{
    global $apbct;

    $ct_feedback = $hash . ':' . $allow . ';';
    if ( ! $apbct->data['feedback_request'] ) {
        $apbct->data['feedback_request'] = $ct_feedback;
    } else {
        $apbct->data['feedback_request'] .= $ct_feedback;
    }

    $apbct->saveData();

    return $ct_feedback;
}

/**
 * Inner function - Sends the results of moderation
 * Scheduled in 3600 seconds!
 *
 * @param string $feedback_request
 *
 * @return bool
 */
function ct_send_feedback($feedback_request = null)
{
    global $apbct;

    if (
        empty($feedback_request) &&
        isset($apbct->data['feedback_request']) &&
        preg_match("/^[a-z0-9\;\:]+$/", $apbct->data['feedback_request'])
    ) {
        $feedback_request                = $apbct->data['feedback_request'];
        $apbct->data['feedback_request'] = '';
        $apbct->saveData();
    }

    if ( $feedback_request !== null ) {
        $ct_request = new CleantalkRequest(array(
            // General
            'auth_key' => $apbct->api_key,
            // Additional
            'feedback' => $feedback_request,
        ));

        $ct = new Cleantalk();

        // Server URL handling
        $config             = ct_get_server();
        $ct->server_url     = APBCT_MODERATE_URL;
        $ct->work_url       = isset($config['ct_work_url']) && preg_match('/http:\/\/.+/', $config['ct_work_url']) ? $config['ct_work_url'] : null;
        $ct->server_ttl     = isset($config['ct_server_ttl']) ? $config['ct_server_ttl'] : null;
        $ct->server_changed = isset($config['ct_server_changed']) ? $config['ct_server_changed'] : null;

        //method use api3.0 since 6.35(6.36?)
        $ct->api_version = '/api3.0';
        $ct->method_uri = 'send_feedback';

        $ct_result = $ct->sendFeedback($ct_request);

        if ( $ct->server_change ) {
            update_option(
                'cleantalk_server',
                array(
                    'ct_work_url'       => $ct->work_url,
                    'ct_server_ttl'     => $ct->server_ttl,
                    'ct_server_changed' => time(),
                )
            );
            $cron = new Cron();
            $cron->updateTask('rotate_moderate', 'apbct_rotate_moderate', 86400); // Rotate moderate server
        }
        if ( $ct_result ) {
            return true;
        }
    }

    return false;
}

/**
 * Delete old spam comments
 * Scheduled in 3600 seconds!
 * @return null
 */
function ct_delete_spam_comments()
{
    global $apbct;

    if ( $apbct->settings['comments__remove_old_spam'] == 1 ) {
        $last_comments = get_comments(array('status' => 'spam', 'number' => 1000, 'order' => 'ASC'));
        if ( is_array($last_comments) ) {
            foreach ( $last_comments as $c ) {
                if ( is_object($c) ) {
                    $comment_date_gmt = strtotime($c->comment_date_gmt);
                    if ( $comment_date_gmt ) {
                        if ( time() - $comment_date_gmt > 86400 * $apbct->data['spam_store_days'] ) {
                            // Force deletion old spam comments
                            wp_delete_comment(TT::toInt($c->comment_ID), true);
                        }
                    }
                }
            }
        }
    }

    return null;
}

/**
 * Get data from an ARRAY recursively
 *
 * @param array $arr
 * @param string $email
 * @param string|array $nickname
 *
 * @return array
 * @deprecated Use ct_gfa_dto() to work with DTO object
 */
function ct_get_fields_any($arr, $email = '', $nickname = '')
{
    if ( is_array($nickname) ) {
        $nickname_str = '';
        foreach ( $nickname as $value ) {
            $nickname_str .= ($value ? $value . " " : "");
        }
        $nickname = trim($nickname_str);
    }

    return ct_gfa($arr, TT::toString($email), TT::toString($nickname));
}

/**
 * Get data as assoc array from an ARRAY recursively
 *
 * @see getFieldsAnyDTO to understand the structure of the result
 * @param array $input_array maybe raw POST array or other preprocessed POST data.
 * @param string $email email, rewriting result of process $input_array data
 * @param string $nickname nickname, rewriting result of process $input_array data
 * @param array $emails_array additional emails array, rewriting result of process $input_array data
 * @deprecated since 6.48, use ct_gfa_dto() instead
 * @return array
 */
function ct_gfa($input_array, $email = '', $nickname = '', $emails_array = array())
{
    $gfa = new GetFieldsAny($input_array);

    return $gfa->getFields($email, $nickname, $emails_array);
}

/**
 * Get data as GetFieldsAnyDTO object from an ARRAY recursively
 *
 * @see getFieldsAnyDTO to understand the structure of the result
 * @param array $input_array maybe raw POST array or other preprocessed POST data.
 * @param string $email email, rewriting result of process $input_array data
 * @param string $nickname nickname, rewriting result of process $input_array data
 * @param array $emails_array array of additional emails, rewriting result of process $input_array data
 *
 * @return GetFieldsAnyDTO
 */
function ct_gfa_dto($input_array, $email = '', $nickname = '', $emails_array = array())
{
    $gfa = new GetFieldsAny($input_array);

    return $gfa->getFieldsDTO($email, $nickname, $emails_array);
}

/**
 * Function changes CleanTalk result object if an error occurred.
 * @return object
 */
function ct_checks_on_cleantalk_errors(CleantalkRequest $ct_request, CleantalkResponse $ct_result)
{
    global $apbct;

    $post_blocked_via_js_check = false;

    if ( (int)($ct_result->errno) != 0 ) {
        if ( $ct_request->js_on === null || $ct_request->js_on != 1 ) {
            $ct_result->allow   = 0;
            $ct_result->spam    = '1';
            $ct_result->comment = sprintf(
                'We\'ve got an issue: %s. Forbidden. Please, enable Javascript. %s.',
                isset($ct_result->comment) ? $ct_result->comment : '',
                $apbct->plugin_name
            );
            $post_blocked_via_js_check = true;
        } else {
            $ct_result->allow   = 1;
            $ct_result->comment = 'Allow';
        }
    }

    // Add a connection report
    $apbct->getConnectionReports()->handleRequest($ct_request, $ct_result, $post_blocked_via_js_check);

    return $ct_result;
}

/**
 * Does ey has correct symbols? Checks against regexp ^[a-z\d]{3,30}$
 *
 * @param string api_key
 *
 * @return bool
 */
function apbct_api_key__is_correct($api_key = null)
{
    global $apbct;
    $api_key = $api_key !== null ? $api_key : $apbct->api_key;

    return $api_key && preg_match('/^[a-z\d]{3,30}$/', $api_key) ? true : false;
}

function apbct__is_hosting_license()
{
    global $apbct;

    return $apbct->data['moderate_ip'] && $apbct->data['ip_license'];
}

function apbct_add_async_attribute($tag, $handle)
{
    global $apbct;

    $scripts_handles_names = array(
        'ct_public',
        'ct_public_functions',
        'ct_public_admin_js',
        'ct_internal',
        'ct_external',
        'ct_nocache',
        'ct_collect_details',
        'cleantalk-modal',
    );

    if ( in_array($handle, $scripts_handles_names, true) ) {
        if ( $apbct->settings['misc__async_js'] ) {
            $tag = str_replace(' src', ' async="async" src', $tag);
        }

        // Prevent script deferred loading by various CDN
        $tag = str_replace(' src', ' data-pagespeed-no-defer src', $tag);

        if ( class_exists('Cookiebot_WP') ) {
            $tag = str_replace(' src', ' data-cookieconsent="ignore" src', $tag);
        }
    }

    return $tag;
}

function apbct_add_admin_ip_to_swf_whitelist($user)
{
    global $apbct;

    $user = ! $user instanceof WP_User ? apbct_wp_get_current_user() : $user;
    $ip   = Helper::ipGet('real', true);

    if (
        $apbct->settings['sfw__enabled'] && // Break if the SpamFireWall is inactive
        Server::isGet() &&
        ! apbct_wp_doing_cron() &&
        is_object($user) &&
        in_array('administrator', (array)$user->roles, true) &&
        Cookie::get('ct_sfw_ip_wl') !== md5($ip . $apbct->api_key) &&
        SFW::updateWriteToDbExclusions(DB::getInstance(), APBCT_TBL_FIREWALL_DATA_PERSONAL, array($ip)) &&
        apbct_private_list_add($ip) &&
        ! headers_sent()
    ) {
        Cookie::set(
            'ct_sfw_ip_wl',
            md5($ip . $apbct->api_key),
            time() + 86400 * 30,
            '/',
            '',
            null,
            true,
            'Lax'
        );
    }
}

function apbct_private_list_add($ip)
{
    global $apbct;

    if ( Helper::ipValidate($ip) ) {
        $result = API::methodPrivateListAddSfwWl($apbct->data['user_token'], $ip, $apbct->data['service_id']);

        return empty($result['error']);
    }

    return false;
}

/**
 * Hide website field from standard comments form
 */
add_filter('comment_form_default_fields', 'apbct__change_type_website_field', 999, 1);
function apbct__change_type_website_field($fields)
{
    global $apbct;

    if ( isset($apbct->settings['comments__hide_website_field']) && $apbct->settings['comments__hide_website_field'] ) {
        if ( isset($fields['url']) && $fields['url'] ) {
            $fields['url'] = '<input id="honeypot-field-url" style="display: none;" autocomplete="off" name="url" type="text" value="" size="30" maxlength="200" />';
        }
        $theme = wp_get_theme();
        if ( isset($theme->template) && $theme->template === 'dt-the7' ) {
            $fields['url'] = '<input id="honeypot-field-url" autocomplete="off" name="url" type="text" value="" size="30" maxlength="200" /></div>';
        }
    }

    return $fields;
}

/**
 * The function determines whether it is necessary
 * to conduct a general check of the post request
 *
 * @return boolean
 */
function apbct_need_to_process_unknown_post_request()
{
    global $apbct;

    /** Exclude Ajax requests */
    if ( apbct_is_ajax() ) {
        return false;
    }

    /** Bitrix24 contact form */
    if ( $apbct->settings['forms__general_contact_forms_test'] == 1 &&
         ! empty(Post::get('your-phone')) &&
         ! empty(Post::get('your-email')) &&
         ! empty(Post::get('your-message'))
    ) {
        return true;
    }

    /** VFB_Pro integration */
    if (
        ! empty($_POST) &&
        $apbct->settings['forms__contact_forms_test'] == 1 &&
        empty(Post::get('ct_checkjs_cf7')) &&
        apbct_is_plugin_active('vfb-pro/vfb-pro.php') &&
        ! empty(Post::get('_vfb-form-id'))
    ) {
        return true;
    }

    /** Integration with custom forms */
    if (
        ! empty($_POST) &&
        apbct_custom_forms_trappings()
    ) {
        return true;
    }

    if (
        $apbct->settings['forms__general_contact_forms_test'] == 1 &&
        empty(Post::get('ct_checkjs_cf7')) &&
        ! apbct_is_direct_trackback()
    ) {
        return true;
    }

    if ( apbct_is_user_enable() ) {
        if (
            $apbct->settings['forms__general_contact_forms_test'] == 1 &&
            ! Post::get('comment_post_ID') &&
            ! Get::get('for') &&
            ! apbct_is_direct_trackback()
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Recursive. Check all post data for ct_no_cookie_hidden_field data.
 *
 * @param array $data
 * @param int $level Current recursion level
 * @param array $array_mapping
 *
 * @return array|false
 */
function apbct_check_post_for_no_cookie_data($data = array(), $level = 0, $array_mapping = array())
{
    //top level check
    if ( Post::get('ct_no_cookie_hidden_field') ) {
        return array('data' => Post::get('ct_no_cookie_hidden_field'), 'mapping' => array('ct_no_cookie_hidden_field'));
    }

    $array = empty($data) ? $_POST : $data;

    //recursion limit
    if ( $level > 5 ) {
        return false;
    }
    foreach ( $array as $key => $value ) {
        if ( is_array($value) ) {
            $array_mapping[] = $key;
            $level++;
            return apbct_check_post_for_no_cookie_data($value, $level, $array_mapping);
        }

        if ( strpos((string)$key, 'ct_no_cookie_hidden_field') !== false || strpos($value, '_ct_no_cookie_data_') !== false ) {
            $array_mapping[] = $key;
            return array('data' => $value, 'mapping' => $array_mapping);
        }
    }
    return array('data' => false, 'mapping' => null);
}

/**
 * Filter POST for no_cookie_data for cases if POST mapping detected in apbct_check_post_for_no_cookie_data
 * @param array $map a POST mapping where no cookie data has been found, mapping level limit is 5 (important)
 * @psalm-suppress PossiblyUndefinedIntArrayOffset
 */
function apbct_filter_post_no_cookie_data($map)
{
    //todo if we need to increase recursion limit , there is no other way except of eval constructor usage
    $cleared_post = $_POST;

    if ( !is_array($map) || empty($map) || count($map) > 5 ) {
        return;
    }

    try {
        switch ( count($map) ) {
            case 1:
                $query_cleared = apbct_clear_query_from_service_fields(
                    $cleared_post [$map[0]],
                    'ct_no_cookie_hidden_field'
                );
                if (false === $query_cleared) {
                    unset($cleared_post [$map[0]]);
                } else {
                    $cleared_post [$map[0]] = $query_cleared;
                }
                break;
            case 2:
                $query_cleared = apbct_clear_query_from_service_fields(
                    $cleared_post [$map[0]] [$map[1]],
                    'ct_no_cookie_hidden_field'
                );
                if (false === $query_cleared) {
                    unset($cleared_post [$map[0]] [$map[1]]);
                } else {
                    $cleared_post [$map[0]] [$map[1]] = $query_cleared;
                }
                break;
            case 3:
                $query_cleared = apbct_clear_query_from_service_fields(
                    $cleared_post [$map[0]] [$map[1]] [$map[2]],
                    'ct_no_cookie_hidden_field'
                );
                if (false === $query_cleared) {
                    unset($cleared_post [$map[0]] [$map[1]] [$map[2]]);
                } else {
                    $cleared_post [$map[0]] [$map[1]] [$map[2]] = $query_cleared;
                }
                break;
            case 4:
                $query_cleared = apbct_clear_query_from_service_fields(
                    $cleared_post [$map[0]] [$map[1]] [$map[2]] [$map[3]],
                    'ct_no_cookie_hidden_field'
                );
                if (false === $query_cleared) {
                    unset($cleared_post [$map[0]] [$map[1]] [$map[2]] [$map[3]]);
                } else {
                    $cleared_post [$map[0]] [$map[1]] [$map[2]] [$map[3]] = $query_cleared;
                }
                break;
            case 5:
                $query_cleared = apbct_clear_query_from_service_fields(
                    $cleared_post [$map[0]] [$map[1]] [$map[2]] [$map[3]] [$map[4]],
                    'ct_no_cookie_hidden_field'
                );
                if (false === $query_cleared) {
                    unset($cleared_post [$map[0]] [$map[1]] [$map[2]] [$map[3]] [$map[4]]);
                } else {
                    $cleared_post [$map[0]] [$map[1]] [$map[2]] [$map[3]] [$map[4]] = $query_cleared;
                }
                break;
        }
        $_POST = $cleared_post;
    } catch ( Exception $e ) {
        return;
    }
}

/**
 * Try to clear POST key from service records if they are persist in query encoded string.
 * <p>For example, function will return <b>"some_field=some_value"</b> if $query_string = <b>"some_field=some_value&ct_no_cookie_hidden_field=some_value"</b>
 * @param $query_string
 * @param $service_field_name string Service record name. If empty, any known records types will be cleared:
 * <ul>
 * <li>ct_bot_detector_event_token</li>
 * <li>apbct_visible_fields</li>
 * <li>ct_no_cookie_hidden_field</li>
 * </ul>
 * @return false|string
 */
function apbct_clear_query_from_service_fields($query_string, $service_field_name = '')
{
    $pattern = empty($service_field_name)
        ? '/(&?ct_bot_detector_event_token=|&?apbct_visible_fields=|&?ct_no_cookie_hidden_field=)/'
        : '/&?' . $service_field_name . '=/';
    if (preg_match($pattern, $query_string)) {
        parse_str($query_string, $query);
        if ( empty($service_field_name) ) {
            //clear all known service fields
            unset($query['ct_bot_detector_event_token']);
            unset($query['apbct_visible_fields']);
            unset($query['ct_no_cookie_hidden_field']);
        } else {
            unset($query[$service_field_name]);
        }
        return http_build_query($query);
    }
    return false;
}

/**
 * Main entry function to collect no cookie data.
 */
function apbct_form__get_no_cookie_data($preprocessed_data = null, $need_filter = true)
{
    global $apbct;
    $flag = null;
    $no_cookie_data = apbct_check_post_for_no_cookie_data($preprocessed_data);

    if ( $need_filter && !empty($no_cookie_data['mapping']) ) {
        apbct_filter_post_no_cookie_data($no_cookie_data['mapping']);
    }
    if ( $no_cookie_data && !empty($no_cookie_data['data']) && $apbct->data['cookies_type'] === 'none' ) {
        $flag = \Cleantalk\ApbctWP\Variables\NoCookie::setDataFromHiddenField($no_cookie_data['data']);
    }
    //set a flag of success
    $apbct->stats['no_cookie_data_taken'] = $flag;
    //set a source if available
    if ( !empty($no_cookie_data['mapping']) && is_array($no_cookie_data['mapping']) ) {
        $apbct->stats['no_cookie_data_post_source'] = '[' . implode('][', $no_cookie_data['mapping']) . ']';
    }
    $apbct->save('stats');
}

/**
 * API method "service_template_get" response validator.
 * @param string $template_id
 * @param array $template_get_result
 * @return array template_name - name from response, options_site - site options from response
 */
function apbct_validate_api_response__service_template_get($template_id, $template_get_result)
{
    $services_templates_get_error = '';
    $options_site = null;
    $template_name = '';

    if ( empty($template_get_result) || !is_array($template_get_result) ) {
        throw new InvalidArgumentException('Parse services_templates_get API error: wrong services_templates_get response');
    }

    if ( array_key_exists('error', $template_get_result) ) {
        throw new InvalidArgumentException('Parse services_templates_get API error: ' . $template_get_result['error']);
    }

    foreach ( $template_get_result as $_key => $template ) {
        if ( empty($template['template_id']) ) {
            $services_templates_get_error = 'Parse services_templates_get API error: template_id is empty';
            break;
        }
        if ( $template['template_id'] === (int)$template_id ) {
            if ( empty($template['options_site']) ) {
                $services_templates_get_error = 'Parse services_templates_get API error: options_site is empty';
                break;
            }
            if ( !is_string($template['options_site']) ) {
                $services_templates_get_error = 'Parse services_templates_get API error: options_site is not a string';
                break;
            }
            $options_site = json_decode($template['options_site'], true);
            $template_name = !empty($template['name']) ? htmlspecialchars($template['name']) : 'N\A';
            if ( $options_site === false || !is_array($options_site)) {
                $services_templates_get_error = 'Parse services_templates_get API error: options_site JSON decode error';
                break;
            }
        }
    }

    if ( !empty($services_templates_get_error) ) {
        throw new InvalidArgumentException($services_templates_get_error);
    }

    if ( empty($options_site) ) {
        throw new InvalidArgumentException('Parse services_templates_get API error: no such template_id found in APi response ' . $template_id);
    }

    return array(
        'template_name' => $template_name,
        'options_site' => $options_site
    );
}

/**
 * Set new settings template called by remote call.
 * @param string $template_id - template id that setting up
 * @param array $options_template_data - validated plugin options from cloud
 * @param string $api_key - current site api key
 * @return string - JSON string of result
 */
function apbct_rc__service_template_set($template_id, array $options_template_data, $api_key)
{
    $templates_object = new CleantalkSettingsTemplates($api_key);
    $settings_set_result = $templates_object->setPluginOptions(
        $template_id,
        isset($options_template_data['template_name']) ? $options_template_data['template_name'] : '',
        isset($options_template_data['options_site']) ? $options_template_data['options_site'] : ''
    );

    $result = $settings_set_result
        ? json_encode(array('OK' => 'Settings updated'))
        : json_encode(array('ERROR' => 'Internal settings updating error'));

    return $result !== false ? $result : '{"ERROR":"Internal JSON encoding error"}';
}

/**
 * Remove CleanTalk service data from super global variables.
 * Attention! This function should be called after(!) CleanTalk request processing.
 * @param array $superglobal $_POST | $_REQUEST
 * @param string $type post|request
 * @return array cleared array of super global
 */
function apbct_clear_superglobal_service_data($superglobal, $type)
{
    $fields_to_clear = array(
        'apbct_visible_fields',
    );

    $cleared_superglobal = $superglobal;

    switch ($type) {
        case 'post':
            //Magnesium Quiz special $_request clearance
            if (
                (
                apbct_is_plugin_active('magnesium-quiz/magnesium-quiz.php')
                )
            ) {
                $fields_to_clear[] = 'ct_bot_detector_event_token';
                $fields_to_clear[] = 'ct_no_cookie_hidden_field';
                $fields_to_clear[] = 'apbct_event_id';
                $fields_to_clear[] = 'apbct__email_id';
            }
            // It is a service field. Need to be deleted before the processing.
            break;
        case 'request':
            //Optima Express special $_request clearance
            if (
                (
                    apbct_is_plugin_active('optima-express/iHomefinder.php')
                )
            ) {
                $fields_to_clear[] = 'ct_no_cookie_hidden_field';
            }
            break;
    }
    $cleared_superglobal = apbct_clear_array_fields_recursive($cleared_superglobal, $fields_to_clear);
    return $cleared_superglobal;
}

/**
 * Clear array from fields by preset
 * @param array $array
 * @param string[] $preset_of_fields_to_clear - array of fields to clear, look for strpos in the key of array
 *
 * @return array
 */
function apbct_clear_array_fields_recursive($array, $preset_of_fields_to_clear = array())
{
    $cleared = is_array($array) ? $array : array();
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $cleared[$key] = apbct_clear_array_fields_recursive($value, $preset_of_fields_to_clear);
        } else if (is_string($key) ) {
            foreach ($preset_of_fields_to_clear as $field) {
                if (strpos($key, $field) !== false) {
                    unset($cleared[$key]);
                }
            }
        }
    }
    return $cleared;
}

/**
 * Check whether the request is AMP
 *
 * @return bool
 */
function apbct_is_amp_request()
{
    $result = false;

    $amp_options = get_option('amp-options');
    if (apbct_is_plugin_active('amp/amp.php') &&
        $amp_options &&
        isset($amp_options['paired_url_structure'])
    ) {
        $amp_paired_type = $amp_options['paired_url_structure'];

        switch ($amp_paired_type) {
            case 'query_var':
                $result = apbct_is_in_uri('?amp=1') ? true : false;
                break;
            case 'path_suffix':
                $result = apbct_is_in_uri('/amp/') ? true : false;
                break;
            case 'legacy_transitional':
                $result = apbct_is_in_uri('?amp') ? true : false;
                break;
            case 'legacy_reader':
                $result = apbct_is_in_uri('/amp/') || apbct_is_in_uri('?amp') ? true : false;
        }
    }

    return $result;
}

/**
 * Try to get event token from params->post->alt.cookies
 *
 * @return string
 */
function apbct_get_event_token($params)
{
    $event_token_from_request = ! empty(Post::get('ct_bot_detector_event_token'))
        ? Post::get('ct_bot_detector_event_token')
        : Cookie::get('ct_bot_detector_event_token');
    $event_token_from_params = ! empty($params['event_token'])
        ? $params['event_token']
        : '';

    return  $event_token_from_params
        ? TT::toString($event_token_from_params)
        : TT::toString($event_token_from_request);
}

/**
 * Do prepare exclusions for skippping bot-detector event token field.
 * @return string JSOn
 */
function apbct__bot_detector_get_prepared_exclusion()
{
    global $apbct;
    $bot_detector_exclusions = array();

    //start exclusion there

    //todo if do need to add a built-ib exclusion, use $exlusion_format
    //set regexp to chek within attributes
    //    $exlusion_format = array(
    //        'exclusion_id' => '',
    //        'signs_to_check' => array(
    //            'form_attributes'               => '',
    //            'form_children_attributes'      => '',
    //            'form_parent_attributes'        => ''
    //        )
    //    );
    if ($apbct->settings['exclusions__bot_detector']) {
        $bot_detector_exclusions = array_merge(
            $bot_detector_exclusions,
            apbct__bot_detector_get_custom_exclusion_from_settings()
        );
    }

    //start validate
    $bot_detector_exclusions_valid = array();
    foreach ($bot_detector_exclusions as $exclusion) {
        if (
            empty($exclusion['exclusion_id']) ||
            (
                empty($exclusion['signs_to_check']['form_attributes']) &&
                empty($exclusion['signs_to_check']['form_children_attributes']) &&
                empty($exclusion['signs_to_check']['form_parent_attributes'])
            )
        ) {
            continue;
        }
        $bot_detector_exclusions_valid[] = $exclusion;
    }

    //prepare for early localize
    $bot_detector_exclusions_valid = json_encode($bot_detector_exclusions_valid);
    return $bot_detector_exclusions_valid !== false ? $bot_detector_exclusions_valid : '{}';
}

function apbct__bot_detector_get_fired_exclusions()
{
    return Cookie::get('ct_bot_detector_form_exclusion');
}

/**
 * Return bot detector frontend data log from Alt Sessions if data found.
 * Format: JSON.
 *
 * @return string JSON encoded bot detector frontend data log.
 */
function apbct__bot_detector_get_fd_log()
{
    global $apbct;
    $result = array(
        'plugin_status' => 'OK',
        'error_msg' => '',
        'frontend_data_log' => ''
    );
    // Initialize result array with default values

    if (defined('APBCT_DO_NOT_COLLECT_FRONTEND_DATA_LOGS')) {
        $result['plugin_status'] = 'OK';
        $result['error_msg'] = 'bot detector logs collection is disabled via constant definition';
        return json_encode($result);
    }

    try {
        if ( TT::toString($apbct->settings['data__bot_detector_enabled']) === '0') {
            throw new \Exception('bot detector library usage is disabled');
        }
        // Retrieve bot detector frontend data log from Alt Sessions
        $alt_sessions_fd_log = AltSessions::get('ct_bot_detector_frontend_data_log');
        // Check if the retrieved data is a string
        if ( !is_string($alt_sessions_fd_log) || '' === $alt_sessions_fd_log ) {
            throw new \Exception('no log found in alt sessions');
        }
        // Encode the retrieved data to JSON format
        $param_bot_detector_fd_log = json_decode($alt_sessions_fd_log, true);
        // Check if the JSON encoding was successful
        if ( empty($param_bot_detector_fd_log) ) {
            throw new \Exception('can not decode data from alt sessions');
        }
    } catch (Exception $e) {
        $result['plugin_status'] = 'ERROR';
        $result['error_msg'] = $e->getMessage();
        return json_encode($result);
    }
    $result['frontend_data_log'] = $param_bot_detector_fd_log;
    // Return the result as a JSON encoded string
    return json_encode($result);
}

function apbct__bot_detector_get_custom_exclusion_from_settings()
{
    global $apbct;

    $exlusion_format = array(
        'exclusion_id' => '',
        'signs_to_check' => array(
            'form_attributes'               => '',
            'form_children_attributes'      => '',
            'form_parent_attributes'        => ''
        )
    );

    $exclusions = array();
    if (!$apbct->settings['exclusions__bot_detector']) {
        return $exclusions;
    }

    foreach ($exlusion_format['signs_to_check'] as $sign => $_val) {
        $setting_name = 'exclusions__bot_detector__' . $sign;
        if (!empty($apbct->settings[$setting_name])) {
            $regexps = explode(',', $apbct->settings[$setting_name]);
            for ( $i = 0; $i < count($regexps); $i++ ) {
                $form_exclusion = $exlusion_format;
                $form_exclusion['exclusion_id'] = 'exclusion_' . $i;
                $form_exclusion['signs_to_check'][$sign] = $regexps[$i];
                $exclusions[] = $form_exclusion;
            }
        }
    }
    return $exclusions;
}
