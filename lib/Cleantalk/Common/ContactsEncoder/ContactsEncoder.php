<?php

namespace Cleantalk\Common\ContactsEncoder;

use Cleantalk\Common\Variables\Post;

/**
 * Email Encoder class.
 */
class ContactsEncoder
{
    /** @var Encoder */
    public $encoder;
    /** @var string[] */
    public $decoded_emails_array = array();
    /** @var string[] */
    public $encoded_emails_array;
    /** @var bool */
    public $has_connection_error;
    /** @var ExclusionsService */
    private $exclusions;
    /** @var ContactsEncoderHelper */
    private $helper;
    /** @var string */
    private $response;
    /** @var string */
    private $temp_content;
    /** @var string */
    private $aria_regex = '/aria-label.?=.?[\'"].+?[\'"]/';
    /** @var array */
    private $aria_matches = array();
    /** @var array */
    private static $attributes_to_drop = array(
        'a' => 'title',
    );
    /** @var string */
    private $global_obfuscation_mode;
    /** @var string */
    private $global_replacing_text;
    /** @var string */
    protected $content = '';

    public function __construct($encoder)
    {
        $this->encoder = $encoder;
        $this->exclusions = new ExclusionsService();
        $this->helper = new ContactsEncoderHelper();
    }

    public function modifyContent($content, $obfuscation_mode = 'blur', $replacing_text = null, $encode_emails = true, $encode_phones = true)
    {
        try {
            $this->global_obfuscation_mode = $obfuscation_mode;
            $this->global_replacing_text = $replacing_text;

            if ($this->exclusions->doReturnContentBeforeModify($content)) {
                return $content;
            }

            $content = $this->handleAriaLabelContent($content);
            $this->temp_content = $content;
            $content = self::dropAttributesContainEmail($content, self::$attributes_to_drop);

            if ($encode_emails) {
                $content = $this->modifyGlobalEmails($content);
            }
            if ($encode_phones) {
                $content = $this->modifyGlobalPhoneNumbers($content);
            }
            return $content;
        } catch (\Throwable $e) {
            // @todo logging error on WP-layer
            return $content;
        }
    }

    public function modifyAny($string, $mode = 'blur', $replacing_text = null)
    {
        try {
            return $this->encodeAny($string, $mode, $replacing_text);
        } catch (\Throwable $e) {
            return $string;
        }
    }

    public function encodePlainEmail($email_str)
    {
        try {
            $obfuscated_string = $email_str;
            $chunks_data = false;
            if ($this->global_obfuscation_mode !== 'replace') {
                $obfuscator = new Obfuscator();
                $chunks = $obfuscator->getEmailData($email_str);
                $chunks_data = $obfuscator->obfuscate_success ? $chunks : false;
            }
            $handled_string = $this->applyEffectsOnMode(
                true,
                $obfuscated_string,
                $this->global_obfuscation_mode,
                $this->global_replacing_text,
                $chunks_data
            );
            $encode_result = $this->encoder->encodeString($email_str);
            if (is_array($encode_result)) {
                if (!$encode_result['success'] && !empty($encode_result['error_message'])) {
                    // @todo logging error $encode_result['error_message']
                }
                $encoded_string = $encode_result['value'];
            } else {
                $encoded_string = $encode_result['value'];;
            }
            return $this->constructEncodedSpan($encoded_string, $handled_string);
        } catch (\Throwable $e) {
            return $email_str;
        }
    }

    public function decodeEmailFromPost($encoded_emails_array)
    {
        try {
            if ($encoded_emails_array) {
                $encoded_emails_array = str_replace('\\', '', $encoded_emails_array);
                $this->encoded_emails_array = json_decode($encoded_emails_array, true);
            }
            foreach ((array)$this->encoded_emails_array as $_key => $encoded_email) {
                $this->decoded_emails_array[$encoded_email] = $this->encoder->decodeString($encoded_email);
            }
            return $this->decoded_emails_array;
        } catch (\Throwable $e) {
            return array();
        }
    }

    // --- Вспомогательные методы ---
    private function handleAriaLabelContent($content, $reverse = false)
    {
        if (!$reverse) {
            $this->aria_matches = array();
            preg_match($this->aria_regex, $content, $this->aria_matches);
            if (empty($this->aria_matches)) {
                return $content;
            }
            return preg_replace($this->aria_regex, 'ct_temp_aria', $content);
        }
        if (!empty($this->aria_matches[0])) {
            return preg_replace('/ct_temp_aria/', $this->aria_matches[0], $content);
        }
        return $content;
    }

    private static function dropAttributesContainEmail($content, $tags)
    {
        $attribute_content_chunk = '[\s]{0,}=[\s]{0,}[\"\']\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\..*\b[\"\']';
        foreach ($tags as $tag => $attribute) {
            $regexp_chunk_without_tag = "/{$attribute}{$attribute_content_chunk}/";
            $regexp_chunk_with_tag = "/<{$tag}.*{$attribute}{$attribute_content_chunk}/";
            preg_match_all($regexp_chunk_with_tag, $content, $matches);
            if (!empty($matches[0])) {
                $content = preg_replace($regexp_chunk_without_tag, '', $content, count($matches[0]));
            }
        }
        return $content;
    }

    private function modifyGlobalEmails($content)
    {
        $pattern = '/(mailto\:\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}\b)|(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+(\.[A-Za-z]{2,}\b))/';
        $replacing_result = '';
        if (version_compare(phpversion(), '7.4.0', '>=')) {
            $replacing_result = preg_replace_callback($pattern, function ($matches) use ($content) {
                if (isset($matches[3][0], $matches[0][0]) && in_array(strtolower($matches[3][0]), ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp'])) {
                    return $matches[0][0];
                }
                if (isset($matches[0][0]) && $this->helper->hasAttributeExclusions($matches[0][0], $this->temp_content)) {
                    return $matches[0][0];
                }
                if (isset($matches[0][0]) && $this->helper->isInsideScriptTag($matches[0][0], $content)) {
                    return $matches[0][0];
                }
                if (isset($matches[0][0]) && $this->helper->isMailto($matches[0][0])) {
                    return $this->encodePlainEmail($matches[0][0]);
                }
                if (isset($matches[0]) && is_array($matches[0]) && $this->helper->isMailtoAdditionalCopy($matches[0], $content)) {
                    return '';
                }
                if (isset($matches[0], $matches[0][0]) && is_array($matches[0]) && $this->helper->isEmailInLink($matches[0], $content)) {
                    return '';
                }
                if (isset($matches[0][0])) {
                    return $this->encodePlainEmail($matches[0][0]);
                }
                return '';
            }, $content, -1, $count, PREG_OFFSET_CAPTURE);
        } else {
            $replacing_result = preg_replace_callback($pattern, function ($matches) {
                if (isset($matches[3]) && in_array(strtolower($matches[3]), ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp']) && isset($matches[0])) {
                    return $matches[0];
                }
                if (isset($matches[0]) && $this->helper->hasAttributeExclusions($matches[0], $this->temp_content)) {
                    return $matches[0];
                }
                if (isset($matches[0]) && $this->helper->isMailto($matches[0])) {
                    return $this->encodePlainEmail($matches[0]);
                }
                if (isset($matches[0])) {
                    return $this->encodePlainEmail($matches[0]);
                }
                return '';
            }, $content);
        }
        $replacing_result = $this->handleAriaLabelContent($replacing_result, true);
        return $replacing_result;
    }

    private function encodeAny($string, $mode = 'blur', $replacing_text = null, $is_phone_number = false)
    {
        $obfuscated_string = $string;
        if ($mode !== 'replace') {
            $obfuscator = new Obfuscator();
            $obfuscated_string = $is_phone_number
                ? $obfuscator->processPhone($string)
                : $obfuscator->processString($string);
        }
        $string_with_effect = $this->applyEffectsOnMode(
            false,
            $obfuscated_string,
            $mode,
            $replacing_text
        );
        $encode_result = $this->encoder->encodeString($string);
        if (is_array($encode_result)) {
            if (!$encode_result['success'] && !empty($encode_result['error_message'])) {
                // @todo logging error $encode_result['error_message']
            }
            $encoded_string = $encode_result['value'];
        } else {
            $encoded_string = $encode_result['value'];
        }
        return $this->constructEncodedSpan($encoded_string, $string_with_effect);
    }

    private function applyEffectsOnMode($is_email, $obfuscated_string, $mode, $replacing_text = null, $email_chunks_data = null)
    {
        switch ($mode) {
            case 'blur':
                $handled_string = $is_email && $email_chunks_data
                    ? $this->addMagicBlurViaChunksData($email_chunks_data)
                    : $this->addMagicBlurToString($obfuscated_string);
                break;
            case 'obfuscate':
                $handled_string = $is_email
                    ? $obfuscated_string
                    : $obfuscated_string;
                break;
            case 'replace':
                $handled_string = !empty($replacing_text) ? $replacing_text : static::getDefaultReplacingText();
                $handled_string = '<span style=\'text-decoration: underline;\'>' . $handled_string . '</span>';
                break;
            default:
                return $obfuscated_string;
        }
        return $handled_string;
    }

    private function constructEncodedSpan($encoded_string, $obfuscated_string)
    {
        return '<span data-original-string="' . $encoded_string . '" class="apbct-email-encoder" title="' . esc_attr($this->getTooltip()) . '">' . $obfuscated_string . '</span>';
    }
    /*
     * =============== MODIFYING ===============
     */

    /**
     * @param string $html
     * @param object $field
     * @param string $display_context
     * @param int $post_id
     * @return string
     * @psalm-suppress PossiblyUnusedParam, PossiblyUnusedReturnValue
     */
    public function modifyFormFieldDisplay($html, $field, $display_context, $post_id)
    {
        if (mb_strpos($html, 'mailto:') !== false) {
            $html = html_entity_decode($html);
            return $this->modifyContent($html);
        }

        return $html;
    }

    /**
     * @param string $content
     * @return string
     * @psalm-suppress PossiblyUnusedReturnValue
     * @phpcs:disable PHPCompatibility.FunctionUse.NewFunctionParameters.preg_replace_callback_flagsFound
     */
    public function modifyGlobalPhoneNumbers($content)
    {
        $pattern = '/(tel:\+\d{8,12})|([\+][\s-]?\(?\d[\d\s\-()]{7,}\d)/';
        $replacing_result = '';

        if ( version_compare(phpversion(), '7.4.0', '>=') ) {
            $replacing_result = preg_replace_callback($pattern, function ($matches) use ($content) {

                if ( isset($matches[0][0]) && is_array($matches[0])) {
                    if ($this->helper->isTelTag($matches[0][0])) {
                        return $this->encodeTelLinkV2($matches[0], $content);
                    }
                    $item_length = strlen(str_replace([' ', '(', ')', '-', '+'], '', $matches[0][0]));
                    if ($item_length > 12 || $item_length < 8) {
                        return $matches[0][0];
                    }
                    if ($this->helper->hasAttributeExclusions($matches[0][0], $this->temp_content)) {
                        return $matches[0][0];
                    }
                    if ($this->helper->isInsideScriptTag($matches[0][0], $content)) {
                        return $matches[0][0];
                    }
                }

                $this->handlePrivacyPolicyHook();

                if ( isset($matches[0][0]) ) {
                    return $this->encodeAny(
                        $matches[0][0],
                        $this->global_obfuscation_mode,
                        $this->global_replacing_text,
                        true
                    );
                }

                return '';
            }, $content, -1, $count, PREG_OFFSET_CAPTURE);
        }

        if ( version_compare(phpversion(), '7.4.0', '<') ) {
            $replacing_result = preg_replace_callback($pattern, function ($matches) {
                if ( isset($matches[0]) ) {
                    if ($this->helper->isTelTag($matches[0]) ) {
                        return $this->encodeTelLink($matches[0]);
                    }

                    $item_length = strlen(str_replace([' ', '(', ')', '-', '+'], '', $matches[0]));
                    if ($item_length > 12 || $item_length < 8) {
                        return $matches[0];
                    }

                    if ($this->helper->hasAttributeExclusions($matches[0][0], $this->temp_content)) {
                        return $matches[0];
                    }
                }

                $this->handlePrivacyPolicyHook();

                if ( isset($matches[0]) ) {
                    return $this->encodeAny(
                        $matches[0],
                        $this->global_obfuscation_mode,
                        $this->global_replacing_text,
                        true
                    );
                }

                return '';
            }, $content);
        }

        // modify content to turn back aria-label
        $replacing_result = $this->handleAriaLabelContent($replacing_result, true);

        //please keep this var (do not simplify the code) for further debug
        return $replacing_result;
    }

    public function bufferOutput()
    {
        global $apbct;
        echo $this->modifyContent($apbct->buffer);
    }

    /*
     * =============== ENCODE ENTITIES ===============
     */

    /**
     * Method to process mailto: links. For PHP < 7.4
     *
     * @param string $mailto_link_str
     *
     * @return string
     */

    /**
     * Method to process mailto: links. Use this only for PHP 7.4+
     *
     * @param $match array
     * @param $content string
     *
    * @return string
    */

    /**
     * Method to process tel: links. For PHP < 7.4
     *
     * @param string $tel_link_str
     *
     * @return string
     */
    private function encodeTelLink($tel_link_str)
    {
        // Get inner tag text and place it in $matches[1]
        preg_match('/tel:(\+\d{8,12})/', $tel_link_str, $matches);
        if ( isset($matches[1]) ) {
            $mailto_inner_text = preg_replace_callback('/\+\d{8,12}/', function ($matches) {
                if (isset($matches[0])) {
                    $obfuscator = new Obfuscator();
                    return $obfuscator->processPhone($matches[0]);
                }
            }, $matches[1]);
        }
        $tel_link_str = str_replace('tel:', '', $tel_link_str);
        $encode_result = $this->encoder->encodeString($tel_link_str);
        if (is_array($encode_result)) {
            if (!$encode_result['success'] && !empty($encode_result['error_message'])) {
                // @todo logging error $encode_result['error_message']
            }
            $encoded = $encode_result['value'];
        } else {
            $encoded = $encode_result['value'];
        }

        $text = isset($mailto_inner_text) ? $mailto_inner_text : $tel_link_str;

        return 'tel:' . $text . '" data-original-string="' . $encoded . '" title="' . esc_attr($this->getTooltip());
    }

    /**
     * Method to process tel: links. Use this only for PHP 7.4+
     *
     * @param array $match
     * @param string $content
     *
     * @return string
     */
    // Removed encodeTelLinkV2 method as it is WP-dependent and not universal

    /**
     * @param string $email_str
     *
     * @return string
     */
    public function getObfuscatedEmailString($email_str)
    {
        $obfuscator = new Obfuscator();
        $chunks = $obfuscator->getEmailData($email_str);
        return $obfuscator->obfuscate_success ? $chunks->getFinalString() : $email_str;
    }

    /*
     * =============== VISUALS ===============
     */

    /**
     * @param $email_str
     *
     * @return string
     */
    private function addMagicBlurEmail($email_str)
    {
        $obfuscator = new Obfuscator();
        $chunks = $obfuscator->getEmailData($email_str);
        $chunks_data = $obfuscator->obfuscate_success ? $chunks : false;

        return false !== $chunks_data
            ? $this->addMagicBlurViaChunksData($chunks_data)
            : $this->addMagicBlurToString($email_str)
            ;
    }


    /**
     * @param ObfuscatorEmailData $email_chunks
     *
     * @return string
     */
    private function addMagicBlurViaChunksData($email_chunks)
    {
        return $email_chunks->chunk_raw_left
               . '<span class="apbct-blur">' . $email_chunks->chunk_obfuscated_left . '</span>'
               . $email_chunks->chunk_raw_center
               . '<span class="apbct-blur">' . $email_chunks->chunk_obfuscated_right . '</span>'
               . $email_chunks->chunk_raw_right
               . $email_chunks->domain;
    }

    /**
     * @param string $obfuscated_string with ** symbols
     *
     * @return string
     */
    private function addMagicBlurToString($obfuscated_string)
    {
        //preparing data to blur
        $regex = '/^([^*]+)(\*+)([^*]+)$/';
        preg_match_all($regex, $obfuscated_string, $matches);
        if (isset($matches[1][0], $matches[2][0], $matches[3][0])) {
            $first = $matches[1][0];
            $middle = $matches[2][0];
            $end = $matches[3][0];
        } else {
            return $obfuscated_string;
        }
        return $first . '<span class="apbct-blur">' . $middle . '</span>' . $end;
    }

    /**
     * Get text for the title attribute
     *
     * @return string
     */
    private function getTooltip()
    {
        global $apbct;
        return sprintf(
            esc_html__('This contact has been encoded by %s. Click to decode. To finish the decoding make sure that JavaScript is enabled in your browser.', 'cleantalk-spam-protect'),
            esc_html__($apbct->data['wl_brandname'])
        );
    }


    /*
    * =============== DECODING ===============
    */


    /**
     * Ajax handler for the apbct_decode_email action
     *
     * @return void returns json string to the JS
     */
    public function ajaxDecodeEmailHandler()
    {
        if (! defined('REST_REQUEST') && !apbct_is_user_logged_in()) {
            AJAXService::checkPublicNonce();
        }

        // use non ssl mode for logged in user on settings page
        if ( apbct_is_user_logged_in() ) {
            $this->decoded_emails_array = $this->decodeEmailFromPost();
            $this->response = $this->compileResponse($this->decoded_emails_array, true);
            wp_send_json_success($this->response);
        }

        $this->decoded_emails_array = $this->decodeEmailFromPost();

        if ( $this->checkRequest() ) {
            //has error response from cloud
            if ( $this->has_connection_error ) {
                $this->response = $this->compileResponse($this->decoded_emails_array, false);
                wp_send_json_error($this->response);
            }
            //decoding is allowed by cloud
            $this->response = $this->compileResponse($this->decoded_emails_array, true);
            wp_send_json_success($this->response);
        }
        //decoding is not allowed by cloud
        $this->response = $this->compileResponse($this->decoded_emails_array, false);
        //important - frontend waits success true to handle response
        wp_send_json_success($this->response);
    }

    /**
     * Main logic of the decoding the encoded data.
     *
     * @return string[] array of decoded email
     */
}
