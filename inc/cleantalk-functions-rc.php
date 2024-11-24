<?php

/**
 * Install plugin from WordPress catalog
 *
 * @param null|WP $_wp
 * @param null|string|array $plugin
 *
 * @psalm-suppress UndefinedClass
 */
function apbct_rc__install_plugin($_wp = null, $plugin = null)
{
    global $wp_version;

    if ( is_null($plugin) ) {
        $plugin = Get::get('plugin') ? Get::get('plugin') : '';
    }

    if ( !empty($plugin) ) {
        $plugin = TT::toString($plugin);
        if ( preg_match('/[a-zA-Z-\d]+[\/\\][a-zA-Z-\d]+\.php/', $plugin) ) {
            $plugin_slug = preg_replace('@([a-zA-Z-\d]+)[\\\/].*@', '$1', $plugin);

            if ( $plugin_slug ) {
                require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
                $result = plugins_api(
                    'plugin_information',
                    array(
                        'slug'   => $plugin_slug,
                        'fields' => array('version' => true, 'download_link' => true,),
                    )
                );

                if ( ! is_wp_error($result) ) {
                    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
                    include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
                    include_once(ABSPATH . 'wp-admin/includes/file.php');
                    include_once(ABSPATH . 'wp-admin/includes/misc.php');

                    if ( version_compare(PHP_VERSION, '5.6.0') >= 0 && version_compare($wp_version, '5.3') >= 0 ) {
                        $installer = new CleantalkUpgrader(new CleantalkUpgraderSkin());
                    } else {
                        $installer = new CleantalkUpgrader(new CleantalkUpgraderSkinDeprecated());
                    }

                    $download_link = is_object($result) ? $result->download_link : false;

                    if ($download_link) {
                        $installer->install($download_link);
                    }

                    if ( $download_link && $installer->apbct_result === 'OK' ) {
                        die('OK');
                    } else {
                        die('FAIL ' . json_encode(array('error' => $installer->apbct_result)));
                    }
                } else {
                    die(
                        'FAIL ' . json_encode(array(
                            'error'   => 'FAIL_TO_GET_LATEST_VERSION',
                            'details' => $result instanceof WP_Error ? $result->get_error_message() : '',
                        ))
                    );
                }
            } else {
                die('FAIL ' . json_encode(array('error' => 'PLUGIN_SLUG_INCORRECT')));
            }
        } else {
            die('FAIL ' . json_encode(array('error' => 'PLUGIN_NAME_IS_INCORRECT')));
        }
    } else {
        die('FAIL ' . json_encode(array('error' => 'PLUGIN_NAME_IS_UNSET')));
    }
}

function apbct_rc__activate_plugin($plugin)
{
    if ( ! $plugin ) {
        $plugin = Get::get('plugin') ? TT::toString(Get::get('plugin')) : null;
    }

    if ( $plugin ) {
        if ( preg_match('@[a-zA-Z-\d]+[\\\/][a-zA-Z-\d]+\.php@', $plugin) ) {
            require_once(ABSPATH . '/wp-admin/includes/plugin.php');

            $result = activate_plugins($plugin);

            $result_array = array('success' => true);
            $error_msg = '';

            if (!$result || is_wp_error($result)) {
                if ($result instanceof WP_Error) {
                    $error_msg = ' ' . $result->get_error_message();
                }
                $result_array = array(
                    'error'   => 'FAIL_TO_ACTIVATE',
                    'details' => $error_msg
                );
            }
            return $result_array;
        } else {
            return array('error' => 'PLUGIN_NAME_IS_INCORRECT');
        }
    } else {
        return array('error' => 'PLUGIN_NAME_IS_UNSET');
    }
}

/**
 * Uninstall plugin from WordPress catalog
 *
 * @param null $plugin
 */
function apbct_rc__deactivate_plugin($plugin = null)
{
    global $apbct;

    if ( is_null($plugin) ) {
        $plugin = Get::get('plugin') ? TT::toString(Get::get('plugin')) : null;
    }

    if ( $plugin ) {
        // Switching complete deactivation for security
        if ( $plugin === 'security-malware-firewall/security-malware-firewall.php' && ! empty(Get::get('misc__complete_deactivation')) ) {
            $spbc_settings                                = TT::toArray(get_option('spbc_settings'));
            $spbc_settings['misc__complete_deactivation'] = TT::toInt(Get::get('misc__complete_deactivation'));
            update_option('spbc_settings', $spbc_settings);
        }

        require_once(ABSPATH . '/wp-admin/includes/plugin.php');

        if ( is_plugin_active($plugin) ) {
            // Hook to set flag if the plugin is deactivated
            add_action('deactivate_' . $plugin, 'apbct_rc__uninstall_plugin__check_deactivate');
            deactivate_plugins($plugin, false, is_multisite());
        } else {
            $apbct->plugin_deactivated = true;
        }

        // Hook to set flag if the plugin is deactivated
        add_action('deactivate_' . $plugin, 'apbct_rc__uninstall_plugin__check_deactivate');
        deactivate_plugins($plugin, false, is_multisite());

        if ( $apbct->plugin_deactivated ) {
            die('OK');
        } else {
            die('FAIL ' . json_encode(array('error' => 'PLUGIN_STILL_ACTIVE')));
        }
    } else {
        die('FAIL ' . json_encode(array('error' => 'PLUGIN_NAME_IS_UNSET')));
    }
}


/**
 * Uninstall plugin from WordPress. Delete files.
 *
 * @param null $plugin
 */
function apbct_rc__uninstall_plugin($plugin = null)
{
    global $apbct;

    if ( is_null($plugin) ) {
        $plugin = Get::get('plugin') ? TT::toString(Get::get('plugin')) : null;
    }

    if ( $plugin ) {
        // Switching complete deactivation for security
        if ( $plugin === 'security-malware-firewall/security-malware-firewall.php' && ! empty(Get::get('misc__complete_deactivation')) ) {
            $spbc_settings                                = TT::toArray(get_option('spbc_settings'));
            $spbc_settings['misc__complete_deactivation'] = TT::toInt(Get::get('misc__complete_deactivation'));
            update_option('spbc_settings', $spbc_settings);
        }

        require_once(ABSPATH . '/wp-admin/includes/plugin.php');

        if ( is_plugin_active($plugin) ) {
            // Hook to set flag if the plugin is deactivated
            add_action('deactivate_' . $plugin, 'apbct_rc__uninstall_plugin__check_deactivate');
            deactivate_plugins($plugin, false, is_multisite());
        } else {
            $apbct->plugin_deactivated = true;
        }

        if ( $apbct->plugin_deactivated ) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');

            $result = delete_plugins(array($plugin));
            $die_string = 'OK';
            $error_msg = '';

            if (!$result || is_wp_error($result)) {
                if ($result instanceof WP_Error) {
                    $error_msg = ' ' . $result->get_error_message();
                }
                $die_string = 'FAIL ' . json_encode(array(
                        'error'   => 'PLUGIN_STILL_EXISTS',
                        'details' => $error_msg
                    ));
            }
            die($die_string);
        } else {
            die('FAIL ' . json_encode(array('error' => 'PLUGIN_STILL_ACTIVE')));
        }
    } else {
        die('FAIL ' . json_encode(array('error' => 'PLUGIN_NAME_IS_UNSET')));
    }
}

function apbct_rc__uninstall_plugin__check_deactivate()
{
    global $apbct;
    $apbct->plugin_deactivated = true;
}

/**
 * @param $source
 *
 * @return bool
 */
function apbct_rc__update_settings($source)
{
    global $apbct;

    foreach ( $apbct->default_settings as $setting => $def_value ) {
        if ( array_key_exists($setting, $source) ) {
            $var  = $source[$setting];
            $type = gettype($def_value);
            settype($var, $type);
            if ( $type === 'string' ) {
                $var = preg_replace(array('/=/', '/`/'), '', $var);
            }
            $apbct->settings[$setting] = $var;
        }
    }

    $apbct->save('settings');

    return true;
}

/**
 * @param string $key
 * @param string $plugin
 *
 * @return array|string
 */
function apbct_rc__insert_auth_key($key, $plugin)
{
    if ( $plugin === 'security-malware-firewall/security-malware-firewall.php' ) {
        require_once(ABSPATH . '/wp-admin/includes/plugin.php');

        if ( is_plugin_active($plugin) ) {
            $key = trim($key);

            if ( $key && preg_match('/^[a-z\d]{3,30}$/', $key) ) {
                $result = API::methodNoticePaidTill(
                    $key,
                    preg_replace('/http[s]?:\/\//', '', get_option('home'), 1), // Site URL
                    'security'
                );

                if ( empty($result['error']) ) {
                    if ( TT::getArrayValueAsInt($result, 'valid') === 1 ) {
                        // Set account params
                        $data                     = get_option('spbc_data', array());
                        $data['user_token']       = TT::getArrayValueAsInt($result, 'user_token');
                        $data['notice_show']      = TT::getArrayValueAsInt($result, 'show_notice');
                        $data['notice_renew']     = TT::getArrayValueAsInt($result, 'renew');
                        $data['notice_trial']     = TT::getArrayValueAsInt($result, 'trial');
                        $data['auto_update_app']  = TT::getArrayValueAsInt($result, 'show_auto_update_notice');
                        $data['service_id']       = TT::getArrayValueAsInt($result, 'service_id');
                        $data['user_id']          = TT::getArrayValueAsInt($result, 'user_id');
                        $data['moderate']         = TT::getArrayValueAsInt($result, 'moderate');
                        $data['auto_update_app '] = TT::getArrayValueAsInt($result, 'auto_update_app');
                        $data['license_trial']    = TT::getArrayValueAsInt($result, 'license_trial');
                        $data['account_name_ob']  = TT::getArrayValueAsString($result, 'account_name_ob');
                        $data['key_is_ok']        = true;
                        update_option('spbc_data', $data);

                        // Set Access key
                        $settings             = TT::toArray(get_option('spbc_settings', array()));
                        $settings['spbc_key'] = $key;
                        update_option('spbc_settings', $settings);

                        return 'OK';
                    } else {
                        return array('error' => 'KEY_IS_NOT_VALID');
                    }
                } else {
                    return array('error' => $result);
                }
            } else {
                return array('error' => 'KEY_IS_NOT_CORRECT');
            }
        } else {
            return array('error' => 'PLUGIN_IS_NOT_ACTIVE_OR_NOT_INSTALLED');
        }
    } else {
        return array('error' => 'PLUGIN_SLUG_INCORRECT');
    }
}
