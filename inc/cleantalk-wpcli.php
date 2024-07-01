<?php

use Cleantalk\ApbctWP\CleantalkSettingsTemplates;

if (!defined('WP_CLI')) {
    return;
}

WP_CLI::add_command('cleantalk', ApbctCli::class, []);

/**
 * @psalm-suppress UnusedClass
 */
class ApbctCli extends WP_CLI_Command // phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
{
    public $method = 'POST';
    public $url = 'https://api.cleantalk.org';

    /**
     * Add service
     *
     * @param $args
     * @param $params
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function create($args, $params)
    {
        if (!isset($params['token'])) {
            echo __("token required \n", 'cleantalk-spam-protect');
            return;
        }
        $data['user_token'] = $params['token'];

        if (!isset($params['email'])) {
            echo __("the email is not specified, the administrator's mail will be used \n", 'cleantalk-spam-protect');
            $admin_email = ct_get_admin_email();

            /**
             * Filters the email to get Access key
             *
             * @param string email to get Access key
             */
            $data['email'] = apply_filters('apbct_get_api_key_email', $admin_email);
        } else {
            $data['email'] = $params['email'];
        }

        if (!isset($params['domain'])) {
            echo __("the domain is not specified, the current domain will be used \n", 'cleantalk-spam-protect');
            $data['website'] = parse_url(get_option('home'), PHP_URL_HOST) . parse_url(get_option('home'), PHP_URL_PATH);
        } else {
            $data['website'] = $params['domain'];
        }

        $data['platform'] = 'wordpress';
        $data['product_name'] = 'antispam';
        $data['method_name'] = 'get_api_key';
        $data['timezone'] = (string)get_option('gmt_offset');

        $result = WP_CLI\Utils\http_request($this->method, $this->url, $data, [], ['insecure' => true]);
        if (!isset($result->body)) {
            echo __("error \n, not expected result", 'cleantalk-spam-protect');
            return;
        }

        $result = json_decode($result->body, true);
        if (!empty($result['error']) || !empty($result['error_message'])) {
            echo __("error \n", 'cleantalk-spam-protect');
            $error = isset($result['error_message']) ? esc_html($result['error_message']) : esc_html($result['error']);
            echo $error . "\n";
            return;
        } elseif (!isset($result['data'])) {
            echo __("Please, get the Access Key from CleanTalk Control Panel \n", 'cleantalk-spam-protect');
            return;
        }

        global $apbct;

        if (isset($result['data']) && isset($result['data']['user_token'])) {
            $apbct->data['user_token'] = $result['user_token'];
        }

        if (isset($result['data']) && !empty($result['data']['auth_key']) && apbct_api_key__is_correct($result['data']['auth_key'])) {
            $apbct->data['key_changed'] = trim($result['data']['auth_key']) !== $apbct->settings['apikey'];
            $apbct->settings['apikey'] = trim($result['data']['auth_key']);
        }

        $apbct->saveSettings();
        $apbct->saveData();

        echo __("Service created and auth key installed. \n", 'cleantalk-spam-protect');
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

        $key = $apbct->settings['apikey'];

        if (!$key) {
            echo __("error - set api_key first \n", 'cleantalk-spam-protect');
            return;
        }

        $data['auth_key'] = $key;
        $data['method_name'] = 'services_templates_get';
        $data['search[product_id]'] = 1;

        $result = WP_CLI\Utils\http_request($this->method, $this->url, $data, [], ['insecure' => true]);
        if (!isset($result->body)) {
            echo __("error \n, not expected result", 'cleantalk-spam-protect');
            return;
        }

        $result = json_decode($result->body, true);
        if (!isset($result['data'])) {
            echo __("error \n", 'cleantalk-spam-protect');
            echo json_last_error();
            echo json_last_error_msg();
            return;
        }

        if (isset($result['error'])) {
            echo __("error \n", 'cleantalk-spam-protect');
            $error = isset($result['error_message']) ? esc_html($result['error_message']) : esc_html($result['error']);
            echo $error . "\n";
            return;
        }

        if (in_array('list', $args)) {
            echo "ID - NAME \n";
            foreach ($result['data'] as $template) {
                echo isset($template['template_id']) ? $template['template_id'] . ' - ' : null;
                echo isset($template['name']) ? $template['name'] : null;
                echo "\n";
            }
            return;
        }

        if (in_array('set', $args)) {
            if (in_array('reset', $args)) {
                require_once('cleantalk-settings.php');
                $settings = new CleantalkSettingsTemplates($key);
                $res = $settings->resetPluginOptions();
                if (!$res) {
                    echo __("error \nCan't reset settings to default\n", 'cleantalk-spam-protect');
                }
                echo __("Success \nTemplate was reset to default \n", 'cleantalk-spam-protect');
                return;
            }

            if (!isset($params['id'])) {
                echo __("error \nplease add <id> param to choose template \n", 'cleantalk-spam-protect');
                return;
            }

            $id = null;
            foreach ($result['data'] as $key => $template) {
                if (isset($template['template_id']) && $template['template_id'] == $params['id']) {
                    $id = $template['template_id'];
                    $name = $template['name'];
                    $set = json_decode($template['options_site'], true);
                }
            }
            if (is_null($id)) {
                echo __("error \nSelected <id> not exist \n", 'cleantalk-spam-protect');
                return;
            }

            require_once('cleantalk-settings.php');
            $settings = new CleantalkSettingsTemplates($key);
            $res = $settings->setPluginOptions($id, $name, $set);
            if (!$res) {
                echo __("error \nCan't set template \n", 'cleantalk-spam-protect');
            }
            echo __("Success \nTemplate '$name' installed \n", 'cleantalk-spam-protect');
            return;
        }
    }

    /**
     * Set settings
     *
     * @param $args
     * @param $params
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function settings($args, $params)
    {
        global $apbct;

        if (isset($params['spamfirewall'])) {
            $apbct->settings['sfw__enabled'] = $params['spamfirewall'] == 'on' ? 1 : 0;
        }

        if (isset($params['registrationsform'])) {
            $apbct->settings['forms__registrations_test'] = $params['registrationsform'] == 'on' ? 1 : 0;
        }

        if (isset($params['commentsform'])) {
            $apbct->settings['forms__comments_test'] = $params['commentsform'] == 'on' ? 1 : 0;
        }

        if (isset($params['contactsform'])) {
            $apbct->settings['forms__contact_forms_test'] = $params['contactsform'] == 'on' ? 1 : 0;
        }

        if (isset($params['searchform'])) {
            $apbct->settings['forms__search_test'] = $params['searchform'] == 'on' ? 1 : 0;
        }

        if (isset($params['checkexternal'])) {
            $apbct->settings['forms__check_external'] = $params['checkexternal'] == 'on' ? 1 : 0;
        }

        if (isset($params['checkinternal'])) {
            $apbct->settings['forms__check_internal'] = $params['checkinternal'] == 'on' ? 1 : 0;
        }

        $apbct->saveSettings();
    }
}
