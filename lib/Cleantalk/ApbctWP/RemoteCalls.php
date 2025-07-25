<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Firewall\SFWUpdateHelper;
use Cleantalk\ApbctWP\UpdatePlugin\DbAnalyzer;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Request;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\Common\TT;

class RemoteCalls
{
    const COOLDOWN = 10;

    private static $allowedActionsWithoutToken = [
        'get_fresh_wpnonce',
        'post_api_key',
    ];

    /**
     * Checking if the current request is the Remote Call
     *
     * @return bool
     */
    public static function check()
    {
        return Request::get('spbc_remote_call_token')
            ? self::checkWithToken()
            : self::checkWithoutToken();
    }

    public static function checkWithToken()
    {
        return Request::get('spbc_remote_call_token') &&
               Request::get('spbc_remote_call_action') &&
               in_array(Request::get('plugin_name'), array('antispam', 'anti-spam', 'apbct'));
    }

    private static function isAllowedWithoutToken($rc)
    {
        return in_array($rc, self::$allowedActionsWithoutToken, true);
    }

    public static function checkWithoutToken()
    {
        global $apbct;

        $rc_servers = [
            'netserv3.cleantalk.org',
            'netserv4.cleantalk.org',
        ];

        $is_noc_request = ! $apbct->key_is_ok &&
            Request::get('spbc_remote_call_action') &&
            in_array(Request::get('plugin_name'), array('antispam', 'anti-spam', 'apbct')) &&
            in_array(Helper::ipResolve(Helper::ipGet('remote_addr')), $rc_servers, true);

        // no token needs for this action, at least for now
        // todo Probably we still need to validate this, consult with analytics team
        $is_wp_nonce_request = $apbct->key_is_ok && Request::get('spbc_remote_call_action') === 'get_fresh_wpnonce';

        return $is_wp_nonce_request || $is_noc_request;
    }

    /**
     * Execute corresponding method of RemoteCalls if exists
     *
     * @return bool|string|string[]|null
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public static function perform()
    {
        global $apbct;

        $action = strtolower(Request::getString('spbc_remote_call_action'));
        $token  = strtolower(Request::getString('spbc_remote_call_token'));

        if ( isset($apbct->remote_calls[$action]) ) {
            $cooldown = isset($apbct->remote_calls[$action]['cooldown']) ? $apbct->remote_calls[$action]['cooldown'] : self::COOLDOWN;

            // Return OK for test remote calls
            if ( Request::get('test') ) {
                die('OK');
            }

            if (
                time() - $apbct->remote_calls[$action]['last_call'] >= $cooldown ||
                ($action === 'sfw_update' && Request::get('file_urls'))
            ) {
                $apbct->remote_calls[$action]['last_call'] = time();
                $apbct->save('remote_calls');

                if ( ! self::isRcAllowed() ) {
                    die('FAIL ' . json_encode(array('error' => 'FORBIDDEN')));
                }

                // Check Access key
                if (
                    (self::checkToken($token)) ||
                    (self::checkWithoutToken() && self::isAllowedWithoutToken($action))
                ) {
                    // Flag to let plugin know that Remote Call is running.
                    $apbct->rc_running = true;

                    $action = 'action__' . $action;

                    if ( method_exists(__CLASS__, $action) ) {
                        // Delay before perform action;
                        if ( Request::get('delay') ) {
                            $delay = Request::getInt('delay');
                            $delay = max($delay, 0);
                            sleep($delay);
                            $params = $_REQUEST;
                            unset($params['delay']);

                            return Helper::httpRequestRcToHost(
                                TT::toString(Request::get('spbc_remote_action')),
                                $params,
                                array('async'),
                                false
                            );
                        }
                        $out = self::$action();
                    } else {
                        $out = 'FAIL ' . json_encode(array('error' => 'UNKNOWN_ACTION_METHOD'));
                    }
                } else {
                    $out = 'FAIL ' . json_encode(array('error' => 'WRONG_TOKEN'));
                }
            } else {
                $out = 'FAIL ' . json_encode(array('error' => 'TOO_MANY_ATTEMPTS'));
            }
        } else {
            $out = 'FAIL ' . json_encode(array('error' => 'UNKNOWN_ACTION'));
        }

        if ( $out ) {
            die($out);
        }
    }

    /**
     * Close renew banner
     *
     * @return string
     */
    public static function action__close_renew_banner() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        global $apbct;

        $apbct->data['notice_trial'] = 0;
        $apbct->data['notice_renew'] = 0;
        $apbct->saveData();
        $cron = new Cron();
        $cron->updateTask('check_account_status', 'ct_account_status_check', 86400);

        return 'OK';
    }

    /**
     * SFW update
     *
     * @return void
     *
     * @psalm-suppress UnusedVariable
     */
    public static function action__sfw_update() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        global $apbct;
        $result = apbct_sfw_update__init();
        $apbct->errorToggle(! empty($result['error']), 'sfw_update', $result);
        die(empty($result['error']) ? 'OK' : 'FAIL ' . json_encode(array('error' => $result['error'])));
    }

    /**
     * SFW update
     *
     * @return string
     *
     * @psalm-suppress UnusedVariable
     */
    public static function action__sfw_update__worker() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $result = apbct_sfw_update__worker();

        if ( ! empty($result['error']) ) {
            SFWUpdateHelper::cleanData();

            die('FAIL ' . json_encode(array('error' => $result['error'])));
        }

        die('OK');
    }

    /**
     * SFW send logs
     *
     * @return array|bool|int[]|string[]
     */
    public static function action__sfw_send_logs() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return ct_sfw_send_logs();
    }

    public static function action__private_record_add() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return apbct_sfw_private_records_handler('add');
    }

    public static function action__private_record_delete() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return apbct_sfw_private_records_handler('delete');
    }

    /**
     * Handle remote call action "run_service_template_get".
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function action__run_service_template_get() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {

        global $apbct;
        $error_hat = 'apbct_run_service_template_get: ';

        if ( empty($apbct->api_key) ) {
            throw new \InvalidArgumentException($error_hat . 'api key not found');
        }
        /**
         * $template_id validation
         */
        $template_id = Request::getString('template_id');

        if ( empty($template_id) ) {
            throw new \InvalidArgumentException($error_hat . 'bad param template_id');
        }

        /**
         * Run and validate API method service_template_get
         */
        $template_from_api = API::methodServicesTemplatesGet($apbct->api_key);

        if (!is_array($template_from_api)) {
            throw new \InvalidArgumentException($error_hat . 'bad response from API');
        }

        $options_template_data = apbct_validate_api_response__service_template_get(
            $template_id,
            $template_from_api
        );

        return apbct_rc__service_template_set($template_id, $options_template_data, $apbct->api_key);
    }

    /**
     * Install plugin
     */
    public static function action__install_plugin() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        add_action('wp', 'apbct_rc__install_plugin', 1);
    }

    /**
     * Activate plugin
     */
    public static function action__activate_plugin() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return apbct_rc__activate_plugin(Request::get('plugin'));
    }

    /**
     * Update settins
     */
    public static function action__update_settings() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return apbct_rc__update_settings($_REQUEST);
    }

    /**
     * Deactivate plugin
     */
    public static function action__deactivate_plugin() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        add_action('plugins_loaded', 'apbct_rc__deactivate_plugin', 1);
    }

    /**
     * Uninstall plugin
     */
    public static function action__uninstall_plugin() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        add_action('plugins_loaded', 'apbct_rc__uninstall_plugin', 1);
    }

    public static function action__debug() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        global $apbct, $wpdb;

        $out = array();

        if (Get::get('run_send_feedback') === '1') {
            $out['send_feedback'] = array('result' => ct_send_feedback() ? 'true' : 'false');
        }

        $sfw_table_name = !empty($apbct->data['sfw_common_table_name']) ? $apbct->data['sfw_common_table_name'] : APBCT_TBL_FIREWALL_DATA;

        $out['sfw_data_base_size'] = $wpdb->get_var('SELECT COUNT(*) FROM ' . $sfw_table_name);
        $out['stats']              = $apbct->stats;
        $out['settings']           = self::getSettings($apbct->settings);
        $out['fw_stats']           = $apbct->fw_stats;
        $out['data']               = $apbct->data;
        $out['cron']               = $apbct->cron;
        $out['sessions']           = [
            'sessions count' => $wpdb->get_var('SELECT COUNT(*) FROM ' . APBCT_TBL_SESSIONS),
            'sessions clear log' => get_option('cleantalk_sessions_clear_log', 'empty yet'),
        ];
        $out['errors']             = $apbct->errors;
        $out['queue']              = get_option('cleantalk_sfw_update_queue');
        $out['connection_reports'] = $apbct->getConnectionReports()->remoteCallOutput();
        $out['cache_plugins_detected'] = apbct_is_cache_plugins_exists(true);
        if ($apbct->settings['data__set_cookies'] == 3 && $apbct->data['cookies_type'] === 'alternative') {
            $out['alt_sessions_auto_state_reason'] = $apbct->isAltSessionsRequired(true);
        }
        $out['active_service_constants'] = $apbct->service_constants->getDefinitionsActive();

        if ( APBCT_WPMS ) {
            $out['network_settings'] = $apbct->network_settings;
            $out['network_data']     = $apbct->network_data;
        }

        // Output only one option
        $show_only = Get::get('show_only');
        if ( $show_only && isset($out[$show_only]) ) {
            /**
             * @psalm-suppress InvalidArrayOffset
             */
            $out = [$show_only => $out[$show_only]];
        }

        if ( Request::equal('out', 'json') ) {
            die(json_encode($out));
        }
        array_walk($out, function (&$val, $_key) {
            $val = (array)$val;
        });

        array_walk_recursive($out, function (&$val, $_key) {
            if ( is_int($val) && preg_match('@^\d{9,11}$@', (string)$val) ) {
                $val = date('Y-m-d H:i:s', $val);
            }
        });


        $out = print_r($out, true);
        $out = str_replace("\n", "<br>", $out);
        $out = preg_replace("/[^\S]{4}/", "&nbsp;&nbsp;&nbsp;&nbsp;", $out);

        die($out);
    }

    /**
     * The 'Cron::updateTask' remote call handler
     */
    public static function action__cron_update_task() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $update_result = false;

        if (
            Request::get('task') &&
            Request::get('handler') &&
            Request::get('period') &&
            Request::get('first_call')
        ) {
            $cron          = new Cron();
            $update_result = $cron->updateTask(
                Request::getString('task'),
                Request::getString('handler'),
                Request::getInt('period'),
                Request::getInt('first_call')
            );
        }

        die($update_result ? 'OK' : 'FAIL');
    }

    public static function action__post_api_key() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        global $apbct;

        if ( ! headers_sent() ) {
            header("Content-Type: application/json");
        }

        $key = trim(Request::getString('api_key'));
        if ( ! apbct_api_key__is_correct($key) ) {
            die(json_encode(['FAIL' => ['error' => 'Api key is incorrect']]));
        }

        require_once APBCT_DIR_PATH . 'inc/cleantalk-settings.php';

        $template_id = TT::toString(Request::get('apply_template_id'));
        if ( ! empty($template_id) ) {
            $templates = CleantalkSettingsTemplates::getOptionsTemplate($key);
            if ( ! empty($templates) ) {
                foreach ( $templates as $template ) {
                    if ( $template['template_id'] == $template_id && ! empty($template['options_site']) ) {
                        $template_name = $template['template_id'];
                        $settings      = $template['options_site'];
                        $settings      = array_replace((array)$apbct->settings, json_decode($settings, true));

                        $settings = \apbct_settings__validate($settings);

                        $apbct->settings = $settings;
                        $apbct->save('settings');
                        $apbct->data['current_settings_template_id']   = $template_id;
                        $apbct->data['current_settings_template_name'] = $template_name;
                        $apbct->save('data');
                        break;
                    }
                }
            }
        }

        $apbct->storage['settings']['apikey'] = $key;
        $apbct->api_key                       = $key;
        $apbct->key_is_ok                     = true;
        $apbct->save('settings');

        \apbct_settings__sync(true);

        die(json_encode(['OK' => ['template_id' => $template_id]]));
    }

    public static function action__rest_check() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $nonce = Post::getString('_rest_nonce');
        if ( empty($nonce) ) {
            throw new \Exception('The nonce is not provided');
        }
        $request = new \WP_REST_Request('POST', '/cleantalk-antispam/v1/apbct_rest_check');
        $request->set_header('x_wp_nonce', $nonce);
        $response = rest_do_request($request);
        $server = rest_get_server();
        $data = $server->response_to_data($response, false);
        return wp_json_encode($data);
    }

    private static function getSettings($settings)
    {
        $titles = array(
            'apikey' => 'Access key',
            'forms__registrations_test' => 'Registration Forms',
            'forms__comments_test' => 'Comments form',
            'forms__contact_forms_test' => 'Contact Forms',
            'forms__flamingo_save_spam' => 'Save Flamingo spam entries',
            'forms__general_contact_forms_test' => 'Custom contact forms',
            'forms__search_test' => 'Test default WordPress search form for spam',
            'forms__check_external' => 'Protect external forms',
            'forms__check_external__capture_buffer' => 'Capture buffer',
            'forms__check_internal' => 'Protect internal forms',
            'forms__wc_checkout_test' => 'WooCommerce checkout form',
            'forms__wc_register_from_order' => 'Spam test for registration during checkout',
            'forms__wc_add_to_cart' => 'Check anonymous users when they add new items to the cart',
            'data__wc_store_blocked_orders' => 'Store blocked orders',
            'comments__disable_comments__all' => 'Disable all comments',
            'comments__disable_comments__posts' => 'Disable comments for all posts',
            'comments__disable_comments__pages' => 'Disable comments for all pages',
            'comments__disable_comments__media' => 'Disable comments for all media',
            'comments__bp_private_messages' => 'BuddyPress Private Messages',
            'comments__remove_old_spam' => 'Automatically delete spam comments',
            'comments__remove_comments_links' => 'Remove links from approved comments',
            'comments__show_check_links' => 'Show links to check Emails, IPs for spam',
            'comments__manage_comments_on_public_page' => 'Manage comments on public pages',
            'data__protect_logged_in' => 'Protect logged in Users',
            'comments__check_comments_number' => "Don't check trusted user's comments",
            'data__use_ajax' => 'Use AJAX for JavaScript check',
            'data__use_static_js_key' => 'Use static keys for JavaScript check',
            'data__general_postdata_test' => 'Check all post data',
            'data__set_cookies' => 'Set cookies',
            'data__bot_detector_enabled' => 'Use JavaScript library',
            'exclusions__bot_detector' => 'JavaScript Library Exclusions',
            'exclusions__bot_detector__form_attributes' => 'Exclude any forms that has attribute matches',
            'exclusions__bot_detector__form_children_attributes' => 'Exclude any forms that includes a child element with attribute matches',
            'exclusions__bot_detector__form_parent_attributes' => 'Exclude any forms that includes a parent element with attribute matches',
            'wp__use_builtin_http_api' => 'Use WordPress HTTP API',
            'data__pixel' => 'Add a Pixel to improve IP-detection',
            'data__email_check_before_post' => 'Check email before POST request',
            'data__email_check_exist_post' => 'Check email before POST request',
            'data__honeypot_field' => 'Add a honeypot field',
            'data__email_decoder' => 'Encode contact data',
            'data__email_decoder_encode_phone_numbers' => 'Encode phones',
            'data__email_decoder_encode_email_addresses' => 'Encode emails',
            'data__email_decoder_buffer' => 'Use the output buffer',
            'exclusions__log_excluded_requests' => 'Log excluded requests',
            'exclusions__urls' => 'URL exclusions',
            'exclusions__urls__use_regexp' => 'Use Regular Expression in URL Exclusions',
            'exclusions__fields' => 'Field Name Exclusions',
            'exclusions__fields__use_regexp' => 'Use Regular Expression in Field Exclusions',
            'exclusions__form_signs' => 'Form Signs Exclusions',
            'exclusions__roles' => 'Roles Exclusions',
            'admin_bar__show' => 'Show statistics in admin bar',
            'admin_bar__all_time_counter' => 'Show All-time counter',
            'admin_bar__daily_counter' => 'Show 24 hours counter',
            'admin_bar__sfw_counter' => 'SpamFireWall counter',
            'sfw__random_get' => 'Uniq GET option',
            'sfw__custom_logo' => 'Custom logo on SpamFireWall blocking pages',
            'sfw__anti_crawler' => 'Anti-Crawler',
            'sfw__anti_flood' => 'Anti-Flood',
            'sfw__anti_flood__view_limit' => 'Anti-Flood Page Views Limit',
            'misc__send_connection_reports' => 'Send connection reports',
            'misc__async_js' => 'Async JavaScript loading',
            'misc__store_urls' => 'Store visited URLs',
            'wp__comment_notify' => 'Plugin stores last 5 visited URLs',
            'wp__comment_notify__roles' => 'wp__comment_notify__roles',
            'wp__dashboard_widget__show' => 'Show Dashboard Widget',
            'misc__complete_deactivation' => 'Complete deactivation',
            'trusted_and_affiliate__shortcode' => 'Shortcode',
            'trusted_and_affiliate__shortcode_tag' => 'Copy this text and place shortcode wherever you need',
            'trusted_and_affiliate__footer' => 'Add to the footer',
            'trusted_and_affiliate__under_forms' => 'Add under forms',
            'trusted_and_affiliate__add_id' => 'Append your affiliate ID',
            'multisite__work_mode' => 'WordPress Multisite Work Mode',
            'multisite__hoster_api_key' => 'Hoster Access key',
            'multisite__white_label' => 'Enable White Label Mode',
            'multisite__white_label__plugin_name' => 'Plugin name',
            'multisite__allow_custom_settings' => 'Allow users to manage plugin settings',
            'multisite__use_settings_template' => 'Use settings template',
            'multisite__use_settings_template_apply_for_new' => 'Apply for newly added sites',
            'multisite__use_settings_template_apply_for_current' => 'Apply for current sites',
            'multisite__use_settings_template_apply_for_current_list_sites' => 'Sites to apply settings',
        );

        $out = [];

        foreach ($settings as $key => $value) {
            if (isset($titles[$key])) {
                $out[$key . ' - ' . $titles[$key]] = $value;
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * Returns the fresh WP nonce depending on the AJAX type (rest/admin_ajax).
     * @return string
     */
    public static function action__get_fresh_wpnonce() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ( ! isset($_POST['nonce_prev']) ) {
            return json_encode(array('error' => 'No nonce provided'));
        }

        $nonce_prev = Post::getString('nonce_prev');
        $nonce_name = apbct_settings__get_ajax_type() === 'rest'
            ? 'wp_rest'
            : AJAXService::$public_nonce_id;

        // Check $nonce_prev by regexp '^[a-f0-9]{10}$'
        if ( ! preg_match('/^[a-f0-9]{10}$/', $nonce_prev) ) {
            return json_encode(array('error' => 'Wrong nonce provided'));
        }

        // set response type 'json'
        header('Content-Type: application/json');
        return TT::toString(
            json_encode(
                array(
                    'wpnonce' => TT::toString(wp_create_nonce($nonce_name))
                )
            )
        );
    }

    private static function isRcAllowed()
    {
        global $apbct;
        return $apbct->api_key || apbct__is_hosting_license();
    }

    private static function checkToken($token)
    {
        global $apbct;
        $value_for_token = '';
        if ( $apbct->api_key ) {
            $value_for_token = $apbct->api_key;
        } elseif ( apbct__is_hosting_license() ) {
            $value_for_token = $apbct->api_key . $apbct->data['salt'];
        }

        return
            $value_for_token &&
            (
                $token === strtolower(md5($value_for_token)) ||
                $token === strtolower(hash('sha256', $value_for_token))
            );
    }
}
