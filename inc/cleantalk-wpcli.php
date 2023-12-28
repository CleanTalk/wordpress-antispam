<?php

if (!defined('WP_CLI')) {
	return;
}

WP_CLI::add_command('cleantalk', ApbctCli::class, []);

class ApbctCli extends WP_CLI_Command {

    public $method = 'POST';
    public $url = 'https://api.cleantalk.org';

	public function __construct(){}

	/**
	 * Add service
	 *
	 * @param $args
	 * @param $params
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

        $result = WP_CLI\Utils\http_request($this->method, $this->url, $data, [], ['insecure' => true]);

        if (!empty($result['error'])) {
            echo __("error \n", 'cleantalk-spam-protect');
            $error = isset($result['error_message']) ? esc_html($result['error_message']) : esc_html($result['error']);
            echo $error . "\n";
            return;
        } elseif (!isset($result['auth_key'])) {
            echo __("Please, get the Access Key from CleanTalk Control Panel \n", 'cleantalk-spam-protect');
            return;
        } else {
            if (isset($params['domain'])) {
                global $apbct;

                if (isset($result['user_token'])) {
                    $apbct->data['user_token'] = $result['user_token'];
                }

                if (!empty($result['auth_key']) && apbct_api_key__is_correct($result['auth_key'])) {
                    $apbct->data['key_changed'] = trim($result['auth_key']) !== $apbct->settings['apikey'];
                    $apbct->settings['apikey'] = trim($result['auth_key']);
                }

                $apbct->saveSettings();
                $apbct->saveData();
            }
        }
	}

	/**
	 * Set template
	 *
	 * @param $args
	 * @param $params
	 */
	public function template($args, $params)
    {
	}
}
