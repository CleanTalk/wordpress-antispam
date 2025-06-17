<?php

use Cleantalk\ApbctWP\CleantalkSettingsTemplates;

if (!defined('WP_CLI')) {
    return;
}

require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-settings.php');

WP_CLI::add_command('cleantalk', ApbctCli::class, []);

/**
 * CleanTalk management via WP-CLI.
 *
 * More detailed:
 * - How to use WP-CLI with the Anti-Spam plugin: https://cleantalk.org/help/wp-cli
 * - Support: https://wordpress.org/support/plugin/cleantalk-spam-protect/
 *
 * @psalm-suppress UnusedClass
 */
class ApbctCli extends WP_CLI_Command // phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
{
    public $method = 'POST';
    public $url = 'https://api.cleantalk.org';

    /**
     * Add service
     *
     * @param mixed $args legacy support
     * @param array $params CLI params
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function create($args, $params)
    {
        global $apbct;

        $this->prompt(__('Service creation start..', 'cleantalk-spam-protect'));
        $this->prompt(__('Params:', 'cleantalk-spam-protect'));
        $this->prompt($params, true);

        delete_option('ct_plugin_do_activation_redirect');

        $data = [];

        if (!isset($params['token'])) {
            $this->prompt(__('Token required! Exit..', 'cleantalk-spam-protect'));
            return;
        }
        $data['user_token'] = $params['token'];

        if (!isset($params['email'])) {
            $admin_email = ct_get_admin_email();
            /**
             * Filters the email to get Access key
             *
             * @param string email to get Access key
             */
            $data['email'] = apply_filters('apbct_get_api_key_email', $admin_email);
            $this->prompt(__('The email is not specified, the administrator\'s email will be used: ', 'cleantalk-spam-protect') . $admin_email);
        } else {
            $data['email'] = $params['email'];
        }

        if (!isset($params['domain'])) {
            $data['website'] = parse_url(get_option('home'), PHP_URL_HOST) . parse_url(get_option('home'), PHP_URL_PATH);
            $this->prompt(__('The domain is not specified, the current domain will be used: ', 'cleantalk-spam-protect') . $data['website']);
        } else {
            $data['website'] = $params['domain'];
        }

        $data['platform'] = 'wordpress';
        $data['product_name'] = 'antispam';
        $data['method_name'] = 'get_api_key';
        $data['timezone'] = (string)get_option('gmt_offset');

        $this->prompt(__('Trying get api key via CleaTalk HTTP request library..', 'cleantalk-spam-protect'));
        $apbct->settings['wp__use_builtin_http_api'] = 0;
        $apbct->saveSettings();


        $result = WP_CLI\Utils\http_request($this->method, $this->url, $data, [], ['insecure' => true]);
        if (!isset($result->body)) {
            $this->prompt(__("HTTP error occurred, trying to get key via built-in Wordpress HTTP API..", 'cleantalk-spam-protect'));
            $apbct->settings['wp__use_builtin_http_api'] = 1;
            $apbct->saveSettings();
            $result = WP_CLI\Utils\http_request($this->method, $this->url, $data, [], ['insecure' => true]);
            if (!isset($result->body)) {
                $this->prompt(__("HTTP error occurred, exit..", 'cleantalk-spam-protect'));
                return;
            }
        }

        $result = json_decode($result->body, true);
        if (!empty($result['error']) || !empty($result['error_message'])) {
            $this->prompt(__("API error:", 'cleantalk-spam-protect'));
            $error = isset($result['error_message']) ? esc_html($result['error_message']) : esc_html($result['error']);
            $this->prompt($error, true);
            return;
        } elseif (!isset($result['data'])) {
            $this->prompt(__("Error. Probably, automatic key getting is disabled in the CleanTalk dashboard settings. Please, get the Access Key from CleanTalk Control Panel. Exit..", 'cleantalk-spam-protect'));
            return;
        }

        if (isset($result['data']) && isset($result['data']['user_token'])) {
            $apbct->data['user_token'] = $result['data']['user_token'];
            $this->prompt(__('User token installed.', 'cleantalk-spam-protect'));
        }

        if (isset($result['data']) && !empty($result['data']['auth_key']) && apbct_api_key__is_correct($result['data']['auth_key'])) {
            $apbct->data['key_changed'] = trim($result['data']['auth_key']) !== $apbct->settings['apikey'];
            $apbct->settings['apikey'] = trim($result['data']['auth_key']);
            $apbct->api_key = $apbct->settings['apikey'];
            $this->prompt(__('Api key installed: ', 'cleantalk-spam-protect') . $apbct->settings['apikey']);
        }

        $apbct->saveSettings();
        $apbct->saveData();

        $this->prompt(__('Running synchronization process and SFW update init..', 'cleantalk-spam-protect'));

        apbct_settings__sync(true);
        if ( $apbct->isHaveErrors() ) {
            $this->prompt(__("Error occurred while syncing: ", 'cleantalk-spam-protect'));
            $this->prompt($apbct->errors, true);
        } else {
            $this->prompt(__("Synchronization success.\n", 'cleantalk-spam-protect'));
        }
        $this->prompt(__('Service created successful.', 'cleantalk-spam-protect'));
    }

    /**
     * Set template
     *
     * @param array $args [list|set|reset]
     * @param $params
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function template($args, $params)
    {
        global $apbct;

        $this->prompt(__('Template service start..', 'cleantalk-spam-protect'));
        $this->prompt(__('Trying to get templates list..', 'cleantalk-spam-protect'));

        $data = [];
        $key = $apbct->settings['apikey'];

        if (!$key) {
            $this->prompt(__('Error. No api key found. Set up api_key first. Exit..', 'cleantalk-spam-protect'));
            return;
        }

        $data['auth_key'] = $key;
        $data['method_name'] = 'services_templates_get';
        $data['search[product_id]'] = 1;

        $result = WP_CLI\Utils\http_request($this->method, $this->url, $data, [], ['insecure' => true]);
        if (!isset($result->body)) {
            $this->prompt($result, true);
            $this->prompt(__('HTTP error occurred. Exit..', 'cleantalk-spam-protect'));
            return;
        }

        $result = json_decode($result->body, true);
        if (!isset($result['data'])) {
            $this->prompt(json_last_error(), true);
            $this->prompt(json_last_error_msg(), true);
            $this->prompt(__('JSON parse error occurred. Exit..', 'cleantalk-spam-protect'));
            return;
        }

        if (isset($result['error'])) {
            $this->prompt(__('API error:', 'cleantalk-spam-protect'));
            $error = isset($result['error_message']) ? esc_html($result['error_message']) : esc_html($result['error']);
            $this->prompt($error, true);
            return;
        }

        if (in_array('list', $args)) {
            $this->prompt(__('Listing mode..', 'cleantalk-spam-protect'));
            if (empty($result['data'])) {
                $this->prompt(__('Error. No templates found. Exit..', 'cleantalk-spam-protect'));
                return;
            }
            $this->prompt(__('Success! Available templates, format is ID -> NAME:', 'cleantalk-spam-protect'));
            foreach ($result['data'] as $template) {
                $id = isset($template['template_id']) ? $template['template_id'] : 'N/A';
                $name = isset($template['name']) ? $template['name'] : 'N/A';
                $this->prompt($id . ' -> ' . $name);
            }
            return;
        }

        if (in_array('set', $args)) {
            if (in_array('reset', $args)) {
                $this->prompt(__('Reset mode..', 'cleantalk-spam-protect'));
                $settings_template_service = new CleantalkSettingsTemplates($key);
                $res = $settings_template_service->resetPluginOptions();
                if (!$res) {
                    $this->prompt(__('Can\'t reset settings to default. Exit..', 'cleantalk-spam-protect'));
                }
                $this->prompt(__('Success! Template was reset to default.', 'cleantalk-spam-protect'));
                return;
            }
            $this->prompt(__('Set up mode..', 'cleantalk-spam-protect'));

            if (!isset($params['id'])) {
                $this->prompt(__('Error. Please add \<id\> param to choose template. Exit..', 'cleantalk-spam-protect'));
                return;
            }

            $id = null;
            $name = '';
            $set = [];
            foreach ($result['data'] as $key => $template) {
                if (
                    isset($template['template_id'], $template['name'], $template['options_site']) &&
                    $template['template_id'] == $params['id']
                ) {
                    $id = $template['template_id'];
                    $name = $template['name'];
                    $set = json_decode($template['options_site'], true);
                }
            }
            if (is_null($id)) {
                $this->prompt(__('Error. Selected ID does not exist. Exit..', 'cleantalk-spam-protect'));
                return;
            }

            require_once('cleantalk-settings.php');
            $settings_template_service = new CleantalkSettingsTemplates($key);
            $res = $settings_template_service->setPluginOptions($id, $name, $set);
            if (!$res) {
                $this->prompt(__('Error occurred during setting a template. Exit..', 'cleantalk-spam-protect'));
            }
            $this->prompt(__('Success! Template installed: ', 'cleantalk-spam-protect') . $name);
            return;
        }

        $this->prompt(__('No available params found. Nothing to do. Exit..', 'cleantalk-spam-protect'));
    }

    /**
     * Set settings
     *
     * @param mixed $args legacy support
     * @param array $input_params CLI params
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function settings($args, $input_params)
    {
        global $apbct;

        function list_cleantalk_settings($apbct, $available_params)
        {
            $out = [];
            $available_params_flip = array_flip($available_params);
            foreach ($apbct->settings as $key => $value) {
                if (in_array($key, array_keys($available_params_flip))) {
                    $value = $value ? 'on' : 'off';
                    $out[$available_params_flip[$key]] = $value;
                }
            }
            return $out;
        }

        $this->prompt(__('Settings update start..', 'cleantalk-spam-protect'));

        if ( empty($input_params)) {
            $this->prompt(__('No available params found - nothing to do. Exit..', 'cleantalk-spam-protect'));
        }

        $available_params = [
            'spamfirewall' => 'sfw__enabled',
            'registrationsform' => 'forms__registrations_test',
            'commentsform' => 'forms__comments_test',
            'contactsform' => 'forms__contact_forms_test',
            'searchform' => 'forms__search_test',
            'checkexternal' => 'forms__check_external',
            'checkinternal' => 'forms__check_internal',
        ];

        if (isset($input_params['list'])) {
            $this->prompt(__('Current settings:', 'cleantalk-spam-protect'));
            $this->prompt(list_cleantalk_settings($apbct, $available_params), true);
            return;
        }

        foreach ( $input_params as $key => $value) {
            if (!in_array($key, array_keys($available_params))) {
                $this->prompt(__('Error. Unknown param: ', 'cleantalk-spam-protect') . $key);
                unset($input_params[$key]);
            }
            $input_params[$key] = trim($value, ' \'\"');
        }

        $this->prompt(__('Found valid params:', 'cleantalk-spam-protect'));
        $this->prompt($input_params, true);

        foreach ($available_params as $avail_param => $setting_key) {
            if ( isset($input_params[$avail_param]) ) {
                if ( $input_params[$avail_param] == 'on' ) {
                    $this->prompt(__('Set ', 'cleantalk-spam-protect') . $avail_param . __(' to ON', 'cleantalk-spam-protect'));
                    $apbct->settings[$setting_key] = 1;
                } else if ($input_params[$avail_param] == 'off') {
                    $this->prompt(__('Set ', 'cleantalk-spam-protect') . $avail_param . __(' to OFF', 'cleantalk-spam-protect'));
                    $apbct->settings[$setting_key] = 0;
                } else {
                    $this->prompt(__('Error. Unknown value for setting: ', 'cleantalk-spam-protect') . $avail_param . '->' . $input_params[$avail_param]);
                }
            }
        }

        $apbct->saveSettings();
        $this->prompt(__('Success! Updated settings state:', 'cleantalk-spam-protect'));
        $this->prompt(list_cleantalk_settings($apbct, $available_params), true);
    }

    /**
     * Echo a message. If the message is not string or $pretty flag is set to true, it will be printed as a print_r.
     * @param mixed $msg Value to print
     * @param bool $pretty Flag to force print as a print_r
     *
     * @return void
     */
    private function prompt($msg, $pretty = false)
    {
        if ($pretty || !is_string($msg)) {
            print_r($msg);
            echo "\n";
            return;
        }
        echo $msg . "\n";
    }
}
