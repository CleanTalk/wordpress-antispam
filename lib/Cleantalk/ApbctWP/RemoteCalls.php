<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Variables\Request;

class RemoteCalls
{
    const COOLDOWN = 10;

    /**
     * Checking if the current request is the Remote Call
     *
     * @return bool
     */
    public static function check()
    {
        return
            Request::get('spbc_remote_call_token') &&
            Request::get('spbc_remote_call_action') &&
            in_array(Request::get('plugin_name'), array('antispam', 'anti-spam', 'apbct'));
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

        $action = strtolower(Request::get('spbc_remote_call_action'));
        $token  = strtolower(Request::get('spbc_remote_call_token'));

        if (isset($apbct->remote_calls[$action])) {
            $cooldown = isset($apbct->remote_calls[$action]['cooldown']) ? $apbct->remote_calls[$action]['cooldown'] : self::COOLDOWN;

            // Return OK for test remote calls
            if (Request::get('test')) {
                die('OK');
            }

            if (
                time() - $apbct->remote_calls[$action]['last_call'] >= $cooldown ||
                ( $action === 'sfw_update' && Request::get('file_urls') )
            ) {
                $apbct->remote_calls[$action]['last_call'] = time();
                $apbct->save('remote_calls');

                // Check Access key
                if ($token == strtolower(md5($apbct->api_key))) {
                    // Flag to let plugin know that Remote Call is running.
                    $apbct->rc_running = true;

                    $action = 'action__' . $action;

                    if (method_exists(__CLASS__, $action)) {
                        // Delay before perform action;
                        if (Request::get('delay')) {
                            sleep((int)Request::get('delay'));
                            $params = $_REQUEST;
                            unset($params['delay']);

                            return Helper::httpRequestRcToHost(
                                Request::get('spbc_remote_action'),
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

        if ($out) {
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

        if ( ! empty($result['error'])) {
            apbct_sfw_update__cleanData();

            die('FAIL ' . json_encode(array('error' => $result['error'])));
        }

        die('OK');
    }

    /**
     * SFW send logs
     *
     * @return string
     */
    public static function action__sfw_send_logs() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return ct_sfw_send_logs();
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
     * Insert Access key
     */
    public static function action__insert_auth_key() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return apbct_rc__insert_auth_key(Request::get('auth_key'), Request::get('plugin'));
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

        $out['sfw_data_base_size'] = $wpdb->get_var('SELECT COUNT(*) FROM ' . APBCT_TBL_FIREWALL_DATA);
        $out['stats']              = $apbct->stats;
        $out['settings']           = $apbct->settings;
        $out['fw_stats']           = $apbct->fw_stats;
        $out['data']               = $apbct->data;
        $out['cron']               = $apbct->cron;
        $out['errors']             = $apbct->errors;
        $out['queue']              = get_option('cleantalk_sfw_update_queue');
        $out['servers_connection'] = apbct_test_connection();

        if (APBCT_WPMS) {
            $out['network_settings'] = $apbct->network_settings;
            $out['network_data']     = $apbct->network_data;
        }

        if (Request::equal('out', 'json')) {
            die(json_encode($out));
        }
        array_walk($out, function (&$val, $_key) {
            $val = (array)$val;
        });

        array_walk_recursive($out, function (&$val, $_key) {
            if (is_int($val) && preg_match('@^\d{9,11}$@', (string)$val)) {
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
            $cron = new Cron();
            $update_result = $cron->updateTask(
                Request::get('task'),
                Request::get('handler'),
                (int)Request::get('period'),
                (int)Request::get('first_call')
            );
        }

        die($update_result ? 'OK' : 'FAIL');
    }
}
