<?php

namespace Cleantalk\ApbctWP\Antispam;

use Cleantalk\Antispam\Cleantalk;
use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\Common\TT;
use Cleantalk\Variables\Post;

class EmailEncoder extends \Cleantalk\Antispam\EmailEncoder\EmailEncoder
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

    public static function getEncoderOptionDescription()
    {
        $common_description = __(
            'Option encodes emails/phones on public pages of the site. This prevents robots from collecting and including your emails/phones in lists to spam.',
            'cleantalk-spam-protect'
        );
        return $common_description;
    }

    public static function getExampleOfEncodedEmail($example_email)
    {
        $example_encoded = '';
        if ( !empty($example_email) && is_string($example_email)) {
            $example_encoded = sprintf(
                '%s <span style="margin-left: 5px">%s.</span>',
                __('Here is a sample of encoded email, click the email to decode it and see the effect', 'cleantalk-spam-protect'),
                TT::toString($example_email)
            );
        }
        return $example_encoded;
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

    public static function getPhonesEncodingDescription()
    {
        return __('Encode phone numbers', 'cleantalk-spam-protect');
    }

    public static function getEmailsEncodingDescription()
    {
        return __('Encode email addresses', 'cleantalk-spam-protect');
    }

    public static function getPhonesEncodingLongDescription()
    {
        $tmp = '
        <p>%s</p>
        <p>%s</p>
            <p class="apbct-icon-right-dir" style="padding-left: 10px">%s</p>
            <p class="apbct-icon-right-dir" style="padding-left: 10px">%s</p>
            <p class="apbct-icon-right-dir" style="padding-left: 10px">%s</p>
            <p class="apbct-icon-right-dir" style="padding-left: 10px">%s</p>
        <p>%s</p>
            <p class="apbct-icon-ok" style="padding-left: 10px">%s</p>
            <p class="apbct-icon-ok" style="padding-left: 10px">%s</p>
            <p class="apbct-icon-ok" style="padding-left: 10px">%s</p>
        <p>%s</p>
            <p>%s</p>
        ';
        $tmp = sprintf(
            $tmp,
            __('Enable this option to encode contact phone numbers', 'cleantalk-spam-protect'),
            __('There are a few requirements to the number format:', 'cleantalk-spam-protect'),
            __('Should starting with "+" symbol', 'cleantalk-spam-protect'),
            __('At least 8 digit numbers', 'cleantalk-spam-protect'),
            __('Less than 13 digit numbers', 'cleantalk-spam-protect'),
            __('Spaces, braces and dashes between digits are allowed', 'cleantalk-spam-protect'),
            __('Examples of format', 'cleantalk-spam-protect'),
            esc_html('+1 (234) 567-8901'),
            esc_html('+12345678901'),
            esc_html('+12 34 5678901'),
            __('Complied numbers in the "a" tag with "tel" property will be also encoded', 'cleantalk-spam-protect'),
            esc_html('<a href="tel:+11234567890">Call  +1 (123) 456-7890</a>')
        );
        return $tmp;
    }

    public static function getEmailEncoderCommonLongDescription()
    {
        $tmp = '
        <p>%s</p>
        <p>%s</p>
        <p>%s</p>
        <p>%s</p>
            <p class="apbct-icon-mail-alt" style="padding-left: 10px">%s</p>
            <p class="apbct-icon-mobile" style="padding-left: 10px">%s</p>
        <p>%s</p>
        <p><a href="#apbct_setting_group__contact_data_encoding" onclick="handleAnchorDetection(\'apbct_setting_group__contact_data_encoding\')">%s</a> %s</p>
        ';
        $tmp = sprintf(
            $tmp,
            __('This option encodes email addresses and phone numbers on your website to make them unreadable to spambots that scan public pages for contact information.', 'cleantalk-spam-protect'),
            __('By hiding emails and phone numbers, it prevents your data from being harvested and added to spam mailing lists or used for unwanted calls.', 'cleantalk-spam-protect'),
            __('Visitors will still be able to see and use your contact details, but bots will see only encoded or partially hidden versions.', 'cleantalk-spam-protect'),
            __('Examples:', 'cleantalk-spam-protect'),
            __('contact@example.com → co*****@********.com', 'cleantalk-spam-protect'),
            __('+1 (234) 567-8901 → +1 (***) ***-****', 'cleantalk-spam-protect'),
            __('You can also customize how emails are displayed before they are decoded by users — blurred, replaced with stars, or shown as a custom message. ', 'cleantalk-spam-protect'),
            __('Click', 'cleantalk-spam-protect'),
            __('to proceed to encoder settings', 'cleantalk-spam-protect')
        );
        return $tmp;
    }

    /**
     * @return string
     */
    public static function getObfuscationModesLongDescription()
    {
        $tmp = '
        <p>%s</p>
        <p>%s yourmail@yourmaildomain.com, %s +1 234 5678910</p>
            <span>1. %s</span>
                <div style="margin: 0 0 20px 10px">
                    <p>
                        %s
                        <p class="apbct-icon-eye"><span class="apbct-email-encoder">yo<span class="apbct-blur">******</span>@<span class="apbct-blur">************</span>in.com</span></p>
                        <p class="apbct-icon-eye"><span class="apbct-email-encoder">+1 23<span class="apbct-blur">********</span>10</span></p>
                    </p>
                </div>
            <span>2. %s</span>
                <div style="margin: 0 0 20px 10px">
                    <p>
                        %s
                        <p class="apbct-icon-eye">yo******@************in.com</p>
                        <p class="apbct-icon-eye">+1 23*******01</p>
                    </p>
                </div>
            <span>3. %s</span>
                <div style="margin: 0 0 20px 10px">
                <p>%s</p>
                </div>
        ';
        $tmp = sprintf(
            $tmp,
            __('This option sets up how the hidden email/phone is visible on the site before decoding.', 'cleantalk-spam-protect'),
            __('Example original email is', 'cleantalk-spam-protect'),
            __('phone is', 'cleantalk-spam-protect'),
            __('Blur effect', 'cleantalk-spam-protect'),
            __('The contact will be partially replaced with blur effect:', 'cleantalk-spam-protect'),
            __('Replace with "*"', 'cleantalk-spam-protect'),
            __('The contact will be partially replaced with * symbols:', 'cleantalk-spam-protect'),
            __('Replace with the custom text', 'cleantalk-spam-protect'),
            __('The contact will be totally replaced with the custom text from the appropriate setting field.', 'cleantalk-spam-protect')
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
            __('The magic is on the way!', 'cleantalk-spam-protect')
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
            __('The complete one is', 'cleantalk-spam-protect')
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
            __('Please wait a few seconds while we decode the contact data.', 'cleantalk-spam-protect')
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


    /**
     * Return status table for settings hat.
     * @param array $data array of required data to construct
     * @return string
     */
    public static function getEncoderStatusForSettingsHat($data)
    {
        $template = '
            <div id="apbct_encoder_status__title_wrapper">
                <span class="apbct-icon-eye-off"></span>
                <span style="margin: 0 3px">%s</span>
                <i setting="%s" class="apbct_settings-long_description---show apbct-icon-help-circled"></i>
            </div>
            <div class="apcbt_contact_data_encoder__line">
                <span>%s&nbsp</span>
                <span>%s</span>
            </div>
            <div class="apcbt_contact_data_encoder__line">
                <span>%s&nbsp</span>
                <span>%s&nbsp</span><span><b>%s,&nbsp</b></span>
                <span>%s&nbsp</span><span><b>%s,&nbsp</b></span>
                <span>%s&nbsp</span><span><b>%s.&nbsp</b></span>
                <span>%s&nbsp<a class="apbct_color--gray" href="#apbct_setting_group__contact_data_encoding" onclick="handleAnchorDetection(\'apbct_setting_group__contact_data_encoding\')">%s </a>%s</span>
            </div>
        ';
        $ancor_to_section = 'Advanced settings';

        $description = __(
            'This feature prevents robots from collecting and including your emails/phones in lists to spam.',
            'cleantalk-spam-protect'
        );

        if (!empty($data['phones_on']) && !empty($data['encoder_enabled_global'])) {
            $phone_status = __('On', 'cleantalk_spam_protect');
        } else {
            $phone_status = __('Off', 'cleantalk_spam_protect');
        }

        if (!empty($data['emails_on']) && !empty($data['encoder_enabled_global'])) {
            $email_status = __('On', 'cleantalk_spam_protect');
        } else {
            $email_status = __('Off', 'cleantalk_spam_protect');
        }

        if (!empty($data['obfuscation_mode']) && !empty($data['encoder_enabled_global'])) {
            if ($data['obfuscation_mode'] === 'blur') {
                $obfuscation_mode = __('Blur', 'cleantalk_spam_protect');
            }
            if ($data['obfuscation_mode'] === 'obfuscate') {
                $obfuscation_mode = __('Replace with * ', 'cleantalk_spam_protect');
            }
            if ($data['obfuscation_mode'] === 'replace') {
                $obfuscation_mode = __('Replace with text', 'cleantalk_spam_protect');
            }
            $obfuscation_mode = empty($obfuscation_mode) ? 'n/a' : '' . $obfuscation_mode . '';
        } else {
            $obfuscation_mode = __('Disabled', 'cleantalk_spam_protect');
        }

        $email = isset($data['current_user_email']) && is_string($data['current_user_email'])
            ? $data['current_user_email']
            : 'example@mail.com';

        return sprintf(
            $template,
            __('Encoding contact data', 'cleantalk_spam_protect'),
            'data__email_decoder',
            $description,
            static::getExampleOfEncodedEmail($email),
            __('By now,', 'cleantalk_spam_protect'),
            __('Phones encoding are', 'cleantalk_spam_protect'),
            $phone_status,
            __('Emails encoding are', 'cleantalk_spam_protect'),
            $email_status,
            __('Current Obfuscation mode are', 'cleantalk_spam_protect'),
            $obfuscation_mode,
            __('Use', 'cleantalk_spam_protect'),
            $ancor_to_section,
            __('to tune the encoding.', 'cleantalk_spam_protect')
        );
    }
}
