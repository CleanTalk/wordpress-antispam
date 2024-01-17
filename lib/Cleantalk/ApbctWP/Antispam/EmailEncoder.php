<?php

namespace Cleantalk\ApbctWP\Antispam;

use Cleantalk\ApbctWP\API;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\Variables\Post;
use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\Antispam\Cleantalk;

class EmailEncoder extends \Cleantalk\Antispam\EmailEncoder
{
    /**
     * @var null|string Comment from API response
     */
    private $comment;

    /**
     * Check if the decoding is allowed
     *
     * Set properties
     *      this->api_response
     *      this->comment
     *
     * @return bool
     */
    protected function checkRequest()
    {
        global $apbct;

        $browser_sign          = hash('sha256', Post::get('browser_signature_params'));
        $event_javascript_data = Helper::isJson(Post::get('event_javascript_data'))
            ? Post::get('event_javascript_data')
            : stripslashes(Post::get('event_javascript_data'));

        $params = array(
            'auth_key'              => $apbct->api_key,        // Access key
            'agent'                 => APBCT_AGENT,
            'event_token'           => null,                   // Unique event ID
            'event_javascript_data' => $event_javascript_data, // JSON-string params to analysis
            'browser_sign'          => $browser_sign,          // Browser ID
            'sender_ip'             => Helper::ipGet('real', false),        // IP address
            'event_type'            => 'CONTACT_DECODING',     // 'GENERAL_BOT_CHECK' || 'CONTACT_DECODING'
            'message_to_log'        => json_encode(array_values($this->decoded_emails_array), JSON_FORCE_OBJECT),   // Custom message
            'page_url'              => Post::get('post_url'),
            'sender_info'           => array(
                'site_referrer'         => Post::get('referrer'),
            ),
        );

        $ct_request = new CleantalkRequest($params);

        $ct = new Cleantalk();
        $this->has_connection_error = false;

        // Options store url without scheme because of DB error with ''://'
        $config             = ct_get_server();
        $ct->server_url     = APBCT_MODERATE_URL;
        $ct->work_url       = preg_match('/https:\/\/.+/', $config['ct_work_url']) ? $config['ct_work_url'] : null;
        $ct->server_ttl     = $config['ct_server_ttl'];
        $ct->server_changed = $config['ct_server_changed'];
        $api_response = $ct->checkBot($ct_request);

        // Allow to see to the decoded contact if error occurred
        // Send error as comment in this case
        if ( ! empty($api_response->errstr)) {
            $this->comment = $api_response->errstr;
            $this->has_connection_error = true;
            return true;
        }

        $stub_comment = $api_response->allow
            ? esc_html__('Allowed', 'cleantalk-spam-protect')
            : esc_html__('Blocked', 'cleantalk-spam-protect');

        $this->comment = ! empty($api_response->comment) ? $api_response->comment : $stub_comment;

        return $api_response->allow === 1;
    }

    /**
     * Compile the response to pass it further
     *
     * @param $decoded_emails_array
     * @param $is_allowed
     *
     * @return array
     */
    protected function compileResponse($decoded_emails_array, $is_allowed)
    {
        $result = array();
        foreach ( $decoded_emails_array as $encoded_email => $decoded_email ) {
            $result[] = array(
                'is_allowed' => $is_allowed,
                'show_comment' => !$is_allowed,
                'comment' => $this->comment,
                'encoded_email' => strip_tags($encoded_email, '<a>'),
                'decoded_email' => $is_allowed ? strip_tags($decoded_email, '<a>') : '',
            );
        }
        return $result;
    }
}
