<?php

use Cleantalk\Antispam\Cleantalk;
use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\Antispam\CleantalkResponse;
use Cleantalk\ApbctWP\API;
use Cleantalk\ApbctWP\CleantalkSettingsTemplates;
use Cleantalk\ApbctWP\Cron;
use Cleantalk\ApbctWP\Firewall\SFW;
use Cleantalk\ApbctWP\GetFieldsAny;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\Common\DB;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;

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


add_action('wp_login', 'apbct_add_admin_ip_to_swf_whitelist', 10, 2);

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

    if ( isset($params['post_info']['comment_type']) && $params['post_info']['comment_type'] === 'site_search_wordpress' ) {
        Cookie::$force_alt_cookies_global = true;
    }

    /* Exclusions */
    if ( $cleantalk_executed ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);

        return array('ct_result' => new CleantalkResponse());
    }

    // URL, IP, Role exclusions
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

    $sender_info = ! empty($params['sender_info'])
        ? \Cleantalk\ApbctWP\Helper::arrayMergeSaveNumericKeysRecursive(
            apbct_get_sender_info(),
            (array)$params['sender_info']
        )
        : apbct_get_sender_info();

    $default_params = array(

        // IPs
        'sender_ip'       => defined('CT_TEST_IP')
            ? CT_TEST_IP
            : \Cleantalk\ApbctWP\Helper::ipGet('remote_addr', false),
        'x_forwarded_for' => \Cleantalk\ApbctWP\Helper::ipGet('x_forwarded_for', false),
        'x_real_ip'       => \Cleantalk\ApbctWP\Helper::ipGet('x_real_ip', false),

        // Misc
        'auth_key'        => $apbct->api_key,
        'js_on'           => apbct_js_test(Cookie::get('ct_checkjs'), true) ? 1 : apbct_js_test(Post::get('ct_checkjs')),

        'agent'       => APBCT_AGENT,
        'sender_info' => $sender_info,
        'submit_time' => apbct_get_submit_time()
    );

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
        $params['exception_action'] = 1;
    }
    /**
     * Skip checking excepted requests if the "Log excluded requests" option is disabled.
     */
    if ( isset($params['exception_action']) && $params['exception_action'] == 1 && ! $apbct->settings['exclusions__log_excluded_requests'] ) {
        do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__, $_POST);
        return array('ct_result' => new CleantalkResponse());
    }

    /**
     * Add honeypot_field if exists in params
     */
    if ( isset($params['honeypot_field']) ) {
        $default_params['honeypot_field'] = $params['honeypot_field'];
    }
    /**
     * Add honeypot_field to $base_call_data if forms__wc_honeypot on
     */
    if ( $apbct->settings['data__honeypot_field'] && !isset($params['honeypot_field']) ) {
        $honeypot_filled_fields = apbct_get_honeypot_filled_fields();
        $params['honeypot_field'] = $honeypot_filled_fields === false ? null : 1;

        if ( !empty($honeypot_filled_fields) && $honeypot_filled_fields !== false ) {
            $params['sender_info']['honeypot_field_value'] = $honeypot_filled_fields['field_value'];
            $params['sender_info']['honeypot_field_source'] = $honeypot_filled_fields['field_source'];
            $params['honeypot_field'] = 0;
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
    $ct->work_url       = preg_match('/https:\/\/.+/', $config['ct_work_url']) ? $config['ct_work_url'] : null;
    $ct->server_ttl     = $config['ct_server_ttl'];
    $ct->server_changed = $config['ct_server_changed'];

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
    $ct_result = ct_checks_on_cleantalk_errors($ct_request, $ct_result);

    // Restart submit form counter for failed requests
    if ( $ct_result->allow == 0 ) {
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
        apbct_exclusions_check__ip() ||
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

        // Fix for AJAX and WP REST API forms
        $haystack =
            (
                Server::get('REQUEST_URI') === '/wp-admin/admin-ajax.php' ||
                stripos(Server::get('REQUEST_URI'), '/wp-json/') === 0
            ) &&
            Server::get('HTTP_REFERER')
            ? str_ireplace(
                array('http://', 'https://', strval(Server::get('HTTP_HOST'))),
                '',
                Server::get('HTTP_REFERER')
            )
            : Server::get('REQUEST_URI');

        if ( $apbct->data['check_exclusion_as_url'] ) {
            $protocol = ! in_array(Server::get('HTTPS'), ['off', '']) || Server::get('SERVER_PORT') == 443 ? 'https://' : 'http://';
            $haystack = $protocol . Server::get('SERVER_NAME') . $haystack;
        }

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
 * @return bool
 * @deprecated since 18.09.2019
 * Checks if sender_ip is in exclusion list
 *
 * @deprecated 5.128 Using IP white-lists instead
 */
function apbct_exclusions_check__ip()
{
    global $cleantalk_ip_exclusions;

    if ( Server::get('REMOTE_ADDR') ) {
        if ( \Cleantalk\ApbctWP\Helper::ipIsCleantalks(Server::get('REMOTE_ADDR')) ) {
            return true;
        }

        if ( ! empty($cleantalk_ip_exclusions) && is_array($cleantalk_ip_exclusions) ) {
            foreach ( $cleantalk_ip_exclusions as $exclusion ) {
                if ( stripos(Server::get('REMOTE_ADDR'), $exclusion) !== false ) {
                    return true;
                }
            }
        }
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

    // AMP check
    $amp_detected =
        Server::get('HTTP_REFERER')
        ? (
            strpos(Server::get('HTTP_REFERER'), '/amp/') !== false ||
            strpos(Server::get('HTTP_REFERER'), '?amp=1') !== false ||
            strpos(Server::get('HTTP_REFERER'), '&amp=1') !== false
            ? 1
            : 0
        )
        : null;

    // Visible fields processing
    $visible_fields_collection = Cookie::getVisibleFields();
    if ( !$visible_fields_collection || (is_array($visible_fields_collection) && !$visible_fields_collection[0]) ) {
        $visible_fields_collection = base64_decode(Post::get('apbct_visible_fields'));
    }

    $visible_fields = apbct_visible_fields__process($visible_fields_collection);

    // preparation of some parameters when cookies are disabled and data is received from localStorage
    $param_email_check = Cookie::get('ct_checked_emails') ? json_encode(
        Cookie::get('ct_checked_emails')
    ) : null;
    $param_mouse_cursor_positions = Cookie::get('ct_pointer_data');
    $param_pixel_url = Cookie::get('apbct_pixel_url');
    $param_screen_info = Cookie::get('ct_screen_info')
    ? json_encode(Cookie::get('ct_screen_info'))
    : null;

    if ($apbct->data['cookies_type'] === 'none') {
        $param_email_check = Cookie::get('ct_checked_emails') ? urldecode(
            Cookie::get('ct_checked_emails')
        ) : null;
        $param_mouse_cursor_positions = urldecode(Cookie::get('ct_pointer_data'));
        $param_pixel_url = Cookie::get('apbct_pixel_url');
        $param_pixel_url = urldecode(is_string($param_pixel_url) ? $param_pixel_url : null);
        $param_screen_info = Cookie::get('ct_screen_info')
            ? urldecode(Cookie::get('ct_screen_info'))
            : null;
    }

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
        'REFFERRER'                 => Server::get('HTTP_REFERER'),
        'REFFERRER_PREVIOUS'        => Cookie::get('apbct_prev_referer') && $cookie_is_ok
            ? Cookie::get('apbct_prev_referer')
            : null,
        'site_landing_ts'           => Cookie::get('apbct_site_landing_ts') && $cookie_is_ok
            ? Cookie::get('apbct_site_landing_ts')
            : null,
        'page_hits'                 => Cookie::get('apbct_page_hits') ?: null,
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
        'site_referer'              => Cookie::get('apbct_site_referer') ?: null,
        'source_url'                => Cookie::get('apbct_urls')
            ? json_encode(json_decode(Cookie::get('apbct_urls'), true))
            : null,
        'pixel_url'                 => $param_pixel_url,
        'pixel_setting'             => $apbct->settings['data__pixel'],
        // Debug stuff
        'amp_detected'              => $amp_detected,
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
        return Server::get('HTTP_REFERER');
    }
    return  Server::get('SERVER_NAME') . Server::get('REQUEST_URI');
}

/**
 * Process visible fields for specific form to match the fields from request
 *
 * @param string|array $visible_fields JSON string
 *
 * @return array
 */
function apbct_visible_fields__process($visible_fields)
{
    $visible_fields = is_array($visible_fields)
        ? json_encode($visible_fields, JSON_FORCE_OBJECT)
        : $visible_fields;

    // Do not decode if it's already decoded
    $fields_collection = json_decode($visible_fields, true);

    if ( ! empty($fields_collection) ) {
        // These fields belong this request
        $fields_to_check = apbct_get_fields_to_check();
        $fields_to_check = apply_filters('apbct_preprocess_post_to_vf_check', $fields_to_check);

        foreach ( $fields_collection as $current_fields ) {
            if ( isset($current_fields['visible_fields'], $current_fields['visible_fields_count']) ) {
                $fields = explode(' ', $current_fields['visible_fields']);

                if ( count(array_intersect(array_keys($fields_to_check), $fields)) > 0 ) {
                    // WP Forms visible fields formatting
                    if ( strpos($current_fields['visible_fields'], 'wpforms') !== false ) {
                        $current_fields = preg_replace(
                            array('/\[/', '/\]/'),
                            '',
                            str_replace(
                                '][',
                                '_',
                                str_replace(
                                    'wpforms[fields]',
                                    '',
                                    $visible_fields
                                )
                            )
                        );
                    }

                    return $current_fields;
                }
            }
        }
    }

    return array();
}

/**
 * Get fields from POST to checking on visible fields.
 *
 * @return array
 */
function apbct_get_fields_to_check()
{
    //Formidable fields
    if ( isset($_POST['item_meta']) && is_array($_POST['item_meta']) ) {
        $fields = array();
        foreach ( $_POST['item_meta'] as $key => $item ) {
            $fields['item_meta[' . $key . ']'] = $item;
        }

        return $fields;
    }

    // @ToDo we have to implement a logic to find form fields (fields names, fields count) in serialized/nested/encoded items. not only $_POST.
    return $_POST;
}

/*
 * Outputs JS key for AJAX-use only. Stops script.
 */
function apbct_js_keys__get__ajax()
{
    die(json_encode(array('js_key' => ct_get_checkjs_value())));
}

function apbct_get_pixel_url__ajax($direct_call = false)
{
    global $apbct;

    $pixel_hash = md5(
        Helper::ipGet()
        . $apbct->api_key
        . Helper::timeGetIntervalStart(3600 * 3) // Unique for every 3 hours
    );

    $server           = get_option('cleantalk_server');
    $server_url       = isset($server['ct_work_url']) ? $apbct->server['ct_work_url'] : APBCT_MODERATE_URL;
    $pixel            = '/pixel/' . $pixel_hash . '.gif';
    $pixel_url = str_replace('http://', 'https://', $server_url) . $pixel;

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
    $email = trim(Post::get('email'));

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
 * Get ct_get_checkjs_value
 *
 * @param bool $random_key
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

function apbct_is_cache_plugins_exists($is_call_on_debug = false)
{
    $out = array();

    $constants_of_cache_plugins = array(
        'WP_ROCKET_VERSION'                          => 'WPRocket',
        'LSCWP_DIR'                                   => 'LiteSpeed Cache',
        'WPFC_WP_CONTENT_BASENAME'                    => 'WP Fastest Cache',
        'W3TC'                                        => 'W3 Total Cache',
        'WPO_VERSION'                                 => 'WP-Optimize – Clean, Compress, Cache',
        'AUTOPTIMIZE_PLUGIN_VERSION'                  => 'Autoptimize',
        'WPCACHEHOME'                                 => 'WP Super Cache',
        'WPHB_VERSION'                                => 'Hummingbird – Speed up, Cache, Optimize Your CSS and JS',
        'CE_FILE'                                     => 'Cache Enabler – WordPress Cache',
        'SiteGround_Optimizer\VERSION'                => 'SG Optimizer',
    );

    $classes_of_cache_plugins = array (
        '\RedisObjectCache' => 'Redis',
        '\WP_Rest_Cache_Plugin\Includes\Plugin' => 'Rest Cache'
    );

    foreach ($constants_of_cache_plugins as $const => $_text) {
        if ( defined($const) ) {
            $out[] = $const;
        }
    }

    foreach ($classes_of_cache_plugins as $class => $_text) {
        /**
         * @psalm-suppress DocblockTypeContradiction
         * @psalm-suppress TypeDoesNotContainType
         */
        if ( class_exists($class) ) {
            $out[] = $class;
        }
    }

    return $is_call_on_debug ? $out : !empty($out);
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

    return $ct_server;
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
        $ct->work_url       = preg_match('/http:\/\/.+/', $config['ct_work_url']) ? $config['ct_work_url'] : null;
        $ct->server_ttl     = $config['ct_server_ttl'];
        $ct->server_changed = $config['ct_server_changed'];

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
        foreach ( $last_comments as $c ) {
            $comment_date_gmt = strtotime($c->comment_date_gmt);
            if ( $comment_date_gmt ) {
                if ( time() - $comment_date_gmt > 86400 * $apbct->data['spam_store_days'] ) {
                    // Force deletion old spam comments
                    wp_delete_comment($c->comment_ID, true);
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
 * @param array $message
 * @param null|string $email
 * @param array $nickname
 * @param null $subject
 * @param bool $contact
 * @param string $prev_name
 *
 * @return array
 * @deprecated Use ct_gfa()
 */
function ct_get_fields_any($arr, $email = null, $nickname = array('nick' => '', 'first' => '', 'last' => ''))
{
    if ( is_array($nickname) ) {
        $nickname_str = '';
        foreach ( $nickname as $value ) {
            $nickname_str .= ($value ? $value . " " : "");
        }
        $nickname = trim($nickname_str);
    }

    return ct_gfa($arr, $email, $nickname);
}

/**
 * Get data from an ARRAY recursively
 *
 * @param array $input_array
 * @param string $email
 * @param string $nickname
 *
 * @return array
 */
function ct_gfa($input_array, $email = '', $nickname = '')
{
    $gfa = new GetFieldsAny($input_array);

    return $gfa->getFields($email, $nickname);
}

//New ct_get_fields_any_postdata
function ct_get_fields_any_postdata($arr, $message = array())
{
    $skip_params = array(
        'ipn_track_id', // PayPal IPN #
        'txn_type', // PayPal transaction type
        'payment_status', // PayPal payment status
    );

    foreach ( $arr as $key => $value ) {
        if ( ! is_array($value) ) {
            if ( $value == '' ) {
                continue;
            }
            if ( ! (in_array($key, $skip_params) || preg_match("/^ct_checkjs/", $key)) && $value != '' ) {
                $message[$key] = $value;
            }
        } else {
            $temp    = ct_get_fields_any_postdata($value);
            $message = (count($temp) == 0 ? $message : array_merge($message, $temp));
        }
    }

    return $message;
}

function cleantalk_debug($key, $value)
{
    if ( Cookie::get('cleantalk_debug')) {
        @header($key . ": " . $value);
    }
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
                $ct_result->comment,
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
        'ct_public_gdpr',
        'ct_debug_js',
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
        in_array('administrator', (array)$user->roles, true) &&
        Cookie::get('ct_sfw_ip_wl') !== md5($ip . $apbct->api_key) &&
        SFW::updateWriteToDbExclusions(DB::getInstance(), APBCT_TBL_FIREWALL_DATA, array($ip)) &&
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
            $fields['url'] = '<input id="honeypot-field-url" autocomplete="off" name="url" type="text" value="" size="30" maxlength="200" />';
        }
    }

    return $fields;
}

/**
 * Woocommerce honeypot
 */
add_filter('woocommerce_checkout_fields', 'apbct__wc_add_honeypot_field');
function apbct__wc_add_honeypot_field($fields)
{
    if (apbct_exclusions_check__url()) {
        return $fields;
    }

    global $apbct;

    if ( $apbct->settings['data__honeypot_field'] ) {
        $fields['billing']['wc_apbct_email_id'] = array(
            'id'            => 'wc_apbct_email_id',
            'type'          => 'text',
            'label'         => '',
            'placeholder'   => '',
            'required'      => false,
            'class'         => array('form-row-wide', 'wc_apbct_email_id'),
            'clear'         => true,
            'autocomplete'  => 'off'
        );
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
 * Handles gained POST and GET data to find filled honeypot fields.
 * @return array|false
 * - array [honeypot_field_value, honeypot_field_source] if we have filled field,
 * - empty array if we have not
 * - false if POST has no honeypot signs
 */
function apbct_get_honeypot_filled_fields()
{
    /**
     * POST forms
     */
    if ( ! empty($_POST) ) {
        //get field suffix for POST forms
        $apbct_event_id = Post::get('apbct_event_id');

        // collect probable sources
        $honeypot_potential_values = array(
            'wc_apbct_email_id' =>                  Post::get('wc_apbct_email_id_' . $apbct_event_id),
            'apbct__email_id__wp_register' =>       Post::get('apbct__email_id__wp_register_' . $apbct_event_id),
            'apbct__email_id__wp_contact_form_7' => Post::get('apbct__email_id__wp_contact_form_7_' . $apbct_event_id),
            'apbct__email_id__wp_wpforms' =>        Post::get('apbct__email_id__wp_wpforms_' . $apbct_event_id),
            'apbct__email_id__search_form' =>       Post::get('apbct__email_id__search_form_' . $apbct_event_id),
            'apbct__email_id__gravity_form' =>      Post::get('apbct__email_id__gravity_form_' . $apbct_event_id)
        );
    } elseif ( ! empty($_GET) ) {
        /**
         * GET forms
         */
        //get field suffix for GET search forms
        $apbct_event_id = false;
        foreach ( $_GET as $key => $value ) {
            if ( strpos((string)$key, 'apbct_submit_id__search_form_') !== false ) {
                $apbct_event_id = str_replace('apbct_submit_id__search_form_', '', (string)$key);
            }
        }

        // collect probable sources
        if ( $apbct_event_id ) {
            $honeypot_potential_values['apbct__email_id__search_form'] = Get::get('apbct__email_id__search_form_' . $apbct_event_id);
        }
    } else {
        $honeypot_potential_values = false;
    }

    /**
     * Handle potential values
     */
    $result = array();
    $post_has_a_honeypot_key = false;
    // if source is filled then pass them to params as additional fields
    if ( !empty($honeypot_potential_values) ) {
        foreach ( $honeypot_potential_values as $source_name => $source_value ) {
            if ( $source_name ) {
                $post_has_a_honeypot_key = true;
            }
            if ( $source_value ) {
                $result['field_value'] = $source_value;
                $result['field_source'] = $source_name;
                break;
            }
        }
    }

    return $post_has_a_honeypot_key ? $result : false;
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
 * @param array $mapping a POST mapping where no cookie data has been found, mapping level limit is 5 (important)
 */
function apbct_filter_post_no_cookie_data($mapping)
{

    //todo if we need to increase recursion limit , there is no other way except of eval constructor usage
    $cleared_post = $_POST;
    if ( !is_array($mapping) || empty($mapping) || count($mapping) > 5 ) {
        return;
    }
    try {
        switch ( count($mapping) ) {
            case 1:
                unset($cleared_post [$mapping[0]]);
                break;
            case 2:
                unset($cleared_post [$mapping[0]] [$mapping[1]]);
                break;
            case 3:
                unset($cleared_post [$mapping[0]] [$mapping[1]] [$mapping[2]]);
                break;
            case 4:
                unset($cleared_post [$mapping[0]] [$mapping[1]] [$mapping[2]] [$mapping[3]]);
                break;
            case 5:
                unset($cleared_post [$mapping[0]] [$mapping[1]] [$mapping[2]] [$mapping[3]] [$mapping[4]]);
                break;
        }
        $_POST = $cleared_post;
    } catch ( Exception $e ) {
        return;
    }
}

/**
 * Main entry function to collect no cookie data.
 */
function apbct_form__get_no_cookie_data($preprocessed_data = null)
{
    global $apbct;
    $flag = null;
    $no_cookie_data = apbct_check_post_for_no_cookie_data($preprocessed_data);
    apbct_filter_post_no_cookie_data($no_cookie_data['mapping']);
    if ( !empty($no_cookie_data['data']) && $apbct->data['cookies_type'] === 'none' ) {
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
        $options_template_data['template_name'],
        $options_template_data['options_site']
    );

    $result = $settings_set_result
        ? json_encode(array('OK' => 'Settings updated'))
        : json_encode(array('ERROR' => 'Internal settings updating error'));

    return $result !== false ? $result : '{"ERROR":"Internal JSON encoding error"}';
}

/**
 * Remove CleanTalk service data from super global variables
 * @param array $superglobal $_POST | $_REQUEST
 * @param string $type post|request
 * @return array cleared array of superglobal
 */
function apbct_clear_superglobal_service_data($superglobal, $type)
{
    switch ($type) {
        case 'post':
            // It is a service field. Need to be deleted before the processing.
            if ( isset($superglobal['apbct_visible_fields']) ) {
                unset($superglobal['apbct_visible_fields']);
            }
            // no break when fall-through is intentional
        case 'request':
            //Optima Express special $_request clearance
            if (
                apbct_is_plugin_active('optima-express/iHomefinder.php') &&
                (
                    isset($superglobal['ct_no_cookie_hidden_field']) ||
                    isset($superglobal['apbct_visible_fields'])
                )
            ) {
                unset($superglobal['ct_no_cookie_hidden_field']);
                unset($superglobal['apbct_visible_fields']);
            }
    }
    return $superglobal;
}
