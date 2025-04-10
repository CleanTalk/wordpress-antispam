<?php

namespace Cleantalk\ApbctWP\Antispam;

use Cleantalk\ApbctWP\Helper;
use Cleantalk\Common\TT;
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
        $post_browser_sign = TT::toString(Post::get('browser_signature_params'));
        $post_event_javascript_data = TT::toString(Post::get('event_javascript_data'));
        $browser_sign          = hash('sha256', $post_browser_sign);
        $event_javascript_data = Helper::isJson($post_event_javascript_data)
            ? $post_event_javascript_data
            : stripslashes($post_event_javascript_data);

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
        $config_work_url    = TT::getArrayValueAsString($config, 'ct_work_url');
        $ct->work_url       = preg_match('/https:\/\/.+/', $config_work_url)
            ? $config_work_url
            : '';
        $ct->server_ttl     = TT::getArrayValueAsInt($config, 'ct_server_ttl');
        $ct->server_changed = TT::getArrayValueAsInt($config, 'ct_server_changed');
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

    public static function getEncoderOptionDescription($example_email = '')
    {
        $common_description = __(
            'Option encodes emails on public pages of the site. This prevents robots from collecting and including your emails in lists to spam.',
            'cleantalk-spam-protect'
        );
        $example_encoded = '';
        if ( !empty($example_email) && is_string($example_email)) {
            $example_encoded = sprintf(
                '%s: %s',
                __('Here is a sample of encoded email', 'cleantalk-spam-protect'),
                TT::toString($example_email)
            );
        }

        $template = '%s&nbsp;%s';

        return sprintf(
            $template,
            $common_description,
            empty($example_encoded) ? '&nbsp;' : '<span class="apbct-email-encoder--settings_example_encoded">' . $example_encoded . '</span>'
        );
    }

    /**
     * @return string|null
     */
    public static function getBufferUsageOptionDescription()
    {
        return __('Use this option only if no encoding occurs when the "Encode contact data" option is enabled.', 'cleantalk-spam-protect');
    }

    /**
     * @return string|null
     */
    public static function getObfuscationModesDescription()
    {
        return __('This options manage the visual effect for encoded email.', 'cleantalk-spam-protect');
    }

    /**
     * @return array[]
     */
    public static function getObfuscationModesOptionsArray()
    {
        return array(
            array('val' => 'blur', 'label' => __('Blur effect', 'cleantalk-spam-protect'),),
            array('val' => 'obfuscate', 'label' => __('Replace with * symbol', 'cleantalk-spam-protect'),),
            array('val' => 'replace', 'label' => __('Replace with the custom text', 'cleantalk-spam-protect'),),
        );
    }

    /**
     * @return string
     */
    public static function getObfuscationModesLongDescription()
    {
        $tmp = '
        <p>%s</p>
        <p>%s yourmail@yourmaildomain.com</p>
            <span>1. %s</span>
                <div style="margin: 0 0 20px 10px">
                    <p>
                        %s
                        <p class="apbct-icon-eye"><span class="apbct-email-encoder">yo<span class="apbct-blur">******</span>@<span class="apbct-blur">************</span>in.com</span></p>
                    </p>
                </div>
            <span>2. %s</span>
                <div style="margin: 0 0 20px 10px">
                    <p>
                        %s
                        <p class="apbct-icon-eye">yo******@************in.com</p>
                    </p>
                </div>
            
            <span>3. %s</span>
                <div style="margin: 0 0 20px 10px">
                <p>%s</p>
            </div>
        ';
        $tmp = sprintf(
            $tmp,
            __('This option sets up how the hidden email is visible on the site before decoding.', 'cleantalk-spam-protect'),
            __('Example original email is', 'cleantalk-spam-protect'),
            __('Blur effect', 'cleantalk-spam-protect'),
            __('The email will be partially replaced with blur effect:', 'cleantalk-spam-protect'),
            __('Replace with "*"', 'cleantalk-spam-protect'),
            __('The email will be partially replaced with * symbols:', 'cleantalk-spam-protect'),
            __('Replace with the custom text', 'cleantalk-spam-protect'),
            __('The email will be totally replaced with the custom text from the appropriate setting field.', 'cleantalk-spam-protect')
        );
        return $tmp;
    }

    public static function getDefaultReplacingText()
    {
        return __('Click to show encoded email', 'cleantalk-spam-protect');
    }

    public static function getLocalizationText()
    {
        /**
         * Filter for the text of the decoding process
         *
         * @param string $decoding_text
         *
         * @return string
         * @apbct_filter
         */
        $decoding_text = apply_filters(
            'apbct__ee_wait_for_decoding_text',
            __('The magic is on the way, please wait for a few seconds!', 'cleantalk-spam-protect')
        );

        /**
         * Filter for the text of the original email
         *
         * @param string $original_contact_text
         *
         * @return string
         * @apbct_filter
         */
        $original_contact_text = apply_filters(
            'apbct__ee_original_email_text',
            __('The original one is', 'cleantalk-spam-protect')
        );

        /**
         * Filter for the text of the decoding process
         *
         * @param string $decoding_process_text
         *
         * @return string
         * @apbct_filter
         */
        $decoding_process_text = apply_filters(
            'apbct__ee_decoding_process_text',
            __('Decoding the contact data, let us a few seconds to finish.', 'cleantalk-spam-protect')
        );

        return array(
            'text__ee_click_to_select'             => __('Click to select the whole data', 'cleantalk-spam-protect'),
            'text__ee_original_email'              => $original_contact_text,
            'text__ee_got_it'                      => __('Got it', 'cleantalk-spam-protect'),
            'text__ee_blocked'                     => __('Blocked', 'cleantalk-spam-protect'),
            'text__ee_cannot_connect'              => __('Cannot connect', 'cleantalk-spam-protect'),
            'text__ee_cannot_decode'               => __('Can not decode email. Unknown reason', 'cleantalk-spam-protect'),
            'text__ee_email_decoder'               => __('CleanTalk email decoder', 'cleantalk-spam-protect'),
            'text__ee_wait_for_decoding'           => $decoding_text,
            'text__ee_decoding_process'            => $decoding_process_text,
        );
    }
}
