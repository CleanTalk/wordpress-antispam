<?php

namespace Cleantalk\Antispam\EmailEncoder;

use Cleantalk\Antispam\EmailEncoder\Shortcodes\ShortCodesService;
use Cleantalk\ApbctWP\AJAXService;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Templates\Singleton;

/**
 * Email Encoder class.
 */
class EmailEncoder
{
    use Singleton;

    /**
     * @var Encoder
     */
    public $encoder;
    /**
     * @var ExclusionsService
     */
    /**
     * @var string[]
     */
    public $decoded_emails_array;
    /**
     * @var string[]
     */
    public $encoded_emails_array;
    /**
     * @var bool
     */
    public $has_connection_error;
    /**
     * @var ExclusionsService
     */
    private $exclusions;
    /**
     * @var EmailEncoderHelper
     */
    private $helper;
    /**
     * @var ShortCodesService
     */
    private $shortcodes;
    /**
     * @var string
     */
    private $response;
    /**
     * Temporary content to use in regexp callback
     * @var string
     */
    private $temp_content;
    /**
     * @var bool
     */
    private $privacy_policy_hook_handled = false;
    /**
     * @var string
     */
    private $aria_regex = '/aria-label.?=.?[\'"].+?[\'"]/';
    /**
     * @var array
     */
    private $aria_matches = array();
    /**
     * Attributes with possible email-like content to drop from the content to avoid unnecessary encoding.
     * Key is a tag we want to find, value is an attribute with email to drop.
     * @var array
     */
    private static $attributes_to_drop = array(
        'a' => 'title',
    );
    /**
     * @var string
     */
    private $global_obfuscation_mode;
    /**
     * @var string
     */
    private $global_replacing_text;


    /**
     * @inheritDoc
     */
    protected function init()
    {
        global $apbct;

        $this->exclusions = new ExclusionsService();
        $this->shortcodes = new ShortCodesService();
        $this->helper = new EmailEncoderHelper();

        $this->encoder = new Encoder(md5($apbct->api_key));

        if (apbct_is_user_logged_in()) {
            $this->ignoreOpenSSLMode();
        }

        if ( $this->exclusions->doSkipBeforeAnything() ) {
            return;
        }

        $this->shortcodes->registerAll();
        $this->shortcodes->addActionsAfterModify('the_content', 11);
        $this->shortcodes->addActionsAfterModify('the_title', 11);

        $this->registerHookHandler();

        if ( $this->exclusions->doSkipBeforeModifyingHooksAdded() ) {
            return;
        }

        $hooks_to_encode = array(
            'the_title',
            'the_content',
            'the_excerpt',
            'get_footer',
            'get_header',
            'get_the_excerpt',
            'comment_text',
            'comment_excerpt',
            'comment_url',
            'get_comment_author_url',
            'get_comment_author_url_link',
            'widget_title',
            'widget_text',
            'widget_content',
            'widget_output',
            'widget_block_content',
            'render_block',
        );

        // Search data to buffer
        if ($apbct->settings['data__email_decoder_buffer'] && !apbct_is_ajax() && !apbct_is_rest() && !apbct_is_post() && !is_admin()) {
            add_action('wp', 'apbct_buffer__start');
            add_action('shutdown', 'apbct_buffer__end', 0);
            add_action('shutdown', array($this, 'bufferOutput'), 2);
            $this->shortcodes->addActionsAfterModify('shutdown', 3);
        } else {
            foreach ( $hooks_to_encode as $hook ) {
                $this->shortcodes->addActionsBeforeModify($hook, 9);
                add_filter($hook, array($this, 'modifyContent'), 10);
            }
        }

        // integration with Business Directory Plugin
        add_filter('wpbdp_form_field_display', array($this, 'modifyFormFieldDisplay'), 10, 4);
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
     *
     * @return string
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function modifyContent($content)
    {
        global $apbct;

        $this->global_obfuscation_mode = $apbct->settings['data__email_decoder_obfuscation_mode'];
        $this->global_replacing_text   = $apbct->settings['data__email_decoder_obfuscation_custom_text'];
        $do_encode_emails   = (bool)$apbct->settings['data__email_decoder_encode_email_addresses'];
        $do_encode_phones  = (bool)$apbct->settings['data__email_decoder_encode_phone_numbers'];

        if ( $this->exclusions->doReturnContentBeforeModify($content) ) {
            return $content;
        }

        // modify content to prevent aria-label replaces by hiding it
        $content = $this->handleAriaLabelContent($content);

        // will use this in regexp callback
        $this->temp_content = $content;

        $content = self::dropAttributesContainEmail($content, self::$attributes_to_drop);

        // Main logic

        $do_encode_emails && $content = $this->modifyGlobalEmails($content);

        $do_encode_phones && $content = $this->modifyGlobalPhoneNumbers($content);

        return $content;
    }

    /**
     * @param string $content
     * @return string
     * @psalm-suppress PossiblyUnusedReturnValue
     * @phpcs:disable PHPCompatibility.FunctionUse.NewFunctionParameters.preg_replace_callback_flagsFound
     */
    public function modifyGlobalEmails($content)
    {
        $pattern = '/(mailto\:\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}\b)|(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+(\.[A-Za-z]{2,}\b))/';
        $replacing_result = '';

        if ( version_compare(phpversion(), '7.4.0', '>=') ) {
            $replacing_result = preg_replace_callback($pattern, function ($matches) use ($content) {
                if ( isset($matches[3][0], $matches[0][0]) && in_array(strtolower($matches[3][0]), ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp']) ) {
                    return $matches[0][0];
                }

                //chek if email is placed in excluded attributes and return unchanged if so
                if ( isset($matches[0][0]) && $this->helper->hasAttributeExclusions($matches[0][0], $this->temp_content) ) {
                    return $matches[0][0];
                }

                // skip encoding if the content in script tag
                if ( isset($matches[0][0]) && $this->helper->isInsideScriptTag($matches[0][0], $content) ) {
                    return $matches[0][0];
                }

                if ( isset($matches[0][0]) && $this->helper->isMailto($matches[0][0]) ) {
                    return $this->encodeMailtoLinkV2($matches[0], $content);
                }

                if (
                    isset($matches[0]) &&
                    is_array($matches[0]) &&
                    $this->helper->isMailtoAdditionalCopy($matches[0], $content)
                ) {
                    return '';
                }

                if (
                    isset($matches[0], $matches[0][0]) &&
                    is_array($matches[0]) &&
                    $this->helper->isEmailInLink($matches[0], $content)
                ) {
                    return $matches[0][0];
                }

                $this->handlePrivacyPolicyHook();

                if ( isset($matches[0][0]) ) {
                    return $this->encodePlainEmail($matches[0][0]);
                }

                return '';
            }, $content, -1, $count, PREG_OFFSET_CAPTURE);
        }

        if ( version_compare(phpversion(), '7.4.0', '<') ) {
            $replacing_result = preg_replace_callback($pattern, function ($matches) {
                if ( isset($matches[3]) && in_array(strtolower($matches[3]), ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp']) && isset($matches[0]) ) {
                    return $matches[0];
                }

                //chek if email is placed in excluded attributes and return unchanged if so
                if ( isset($matches[0]) && $this->helper->hasAttributeExclusions($matches[0], $this->temp_content) ) {
                    return $matches[0];
                }

                if ( isset($matches[0]) &&  $this->helper->isMailto($matches[0]) ) {
                    return $this->encodeMailtoLink($matches[0]);
                }

                $this->handlePrivacyPolicyHook();

                if ( isset($matches[0]) ) {
                    return $this->encodePlainEmail($matches[0]);
                }

                return '';
            }, $content);
        }

        // modify content to turn back aria-label
        $replacing_result = $this->handleAriaLabelContent($replacing_result, true);

        //please keep this var (do not simplify the code) for further debug
        return $replacing_result;
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

    /**
     * Wrapper. Encode any string.
     * @param $string
     *
     * @return string
     */
    public function modifyAny($string)
    {
        $encoded_string = $this->encodeAny($string);

        //please keep this var (do not simplify the code) for further debug
        return $encoded_string;
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
     * Method to process plain email
     *
     * @param string $email_str
     *
     * @return string
     */
    public function encodePlainEmail($email_str)
    {
        $obfuscated_string = $email_str;
        $chunks_data = false;

        if ( $this->global_obfuscation_mode !== 'replace') {
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
        $encoded_string = $this->encoder->encodeString($email_str);

        return $this->constructEncodedSpan($encoded_string, $handled_string);
    }

    /**
     * @param string $string of any data
     * @param string $mode
     * @param string $replacing_text
     *
     * @return string
     */
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
        $encoded_string = $this->encoder->encodeString($string);

        return $this->constructEncodedSpan($encoded_string, $string_with_effect);
    }

    /**
     * Method to process mailto: links. For PHP < 7.4
     *
     * @param string $mailto_link_str
     *
     * @return string
     */
    private function encodeMailtoLink($mailto_link_str)
    {
        // Get inner tag text and place it in $matches[1]
        preg_match('/mailto\:(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,})/', $mailto_link_str, $matches);
        if ( isset($matches[1]) ) {
            $mailto_inner_text = preg_replace_callback('/\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}/', function ($matches) {
                if (isset($matches[0])) {
                    return $this->getObfuscatedEmailString($matches[0]);
                }
            }, $matches[1]);
        }
        $mailto_link_str = str_replace('mailto:', '', $mailto_link_str);
        $encoded = $this->encoder->encodeString($mailto_link_str);

        $text = isset($mailto_inner_text) ? $mailto_inner_text : $mailto_link_str;

        return 'mailto:' . $text . '" data-original-string="' . $encoded . '" title="' . esc_attr($this->getTooltip());
    }

    /**
     * Method to process mailto: links. Use this only for PHP 7.4+
     *
     * @param $match array
     * @param $content string
     *
     * @return string
     */
    private function encodeMailtoLinkV2($match, $content)
    {
        $position = $match[1];
        $q_position = $position + strcspn($content, '\'"', $position);
        $mailto_link_str = substr($content, $position, $q_position - $position);
        // Get inner tag text and place it in $matches[1]
        preg_match('/mailto\:(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,})/', $mailto_link_str, $matches);
        if ( isset($matches[1]) ) {
            $mailto_inner_text = preg_replace_callback('/\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}/', function ($matches) {
                if ( isset($matches[0]) ) {
                    return $this->getObfuscatedEmailString($matches[0]);
                }

                return '';
            }, $matches[1]);
        }

        $mailto_link_str = str_replace('mailto:', '', $mailto_link_str);
        $encoded = $this->encoder->encodeString($mailto_link_str);

        $text = isset($mailto_inner_text) ? $mailto_inner_text : $mailto_link_str;

        return 'mailto:' . $text . '" data-original-string="' . $encoded . '" title="' . esc_attr($this->getTooltip());
    }

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
        $encoded      = $this->encoder->encodeString($tel_link_str);

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
    private function encodeTelLinkV2($match, $content)
    {
        $position = !empty($match[1]) ? (int)$match[1] : null;
        if (null === $position) {
            return $content;
        }
        $q_position = $position + strcspn($content, '\'"', $position);
        $tel_link_string = substr($content, $position, $q_position - $position);
        // Get inner tag text and place it in $matches[1]
        preg_match('/tel:(\+\d{8,12})/', $tel_link_string, $matches);
        if ( isset($matches[1]) ) {
            $tel_inner_text = preg_replace_callback('/\+\d{8,12}/', function ($matches) {
                if ( isset($matches[0]) ) {
                    $obfuscator = new Obfuscator();
                    return $obfuscator->processPhone($matches[0]);
                }
                return '';
            }, $matches[1]);
        }

        $tel_link_string = str_replace('tel:', '', $tel_link_string);
        $encoded = $this->encoder->encodeString($tel_link_string);

        $text = isset($tel_inner_text) ? $tel_inner_text : $tel_link_string;

        return 'tel:' . $text . '" data-original-string="' . $encoded . '" title="' . esc_attr($this->getTooltip());
    }

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
     * @param bool $is_email
     * @param string $obfuscated_string
     * @param string $mode
     * @param string $replacing_text
     * @param ObfuscatorEmailData|false $email_chunks_data
     *
     * @return string
     */
    private function applyEffectsOnMode($is_email, $obfuscated_string, $mode, $replacing_text = null, $email_chunks_data = null)
    {
        switch ($mode) {
            case 'blur':
                $handled_string = $is_email && $email_chunks_data
                    ? $this->addMagicBlurEmail($obfuscated_string)
                    : $this->addMagicBlurToString($obfuscated_string);
                break;
            case 'obfuscate':
                $handled_string = $is_email
                    ? $this->getObfuscatedEmailString($obfuscated_string)
                    : $obfuscated_string;
                break;
            case 'replace':
                $handled_string = !empty($replacing_text) ? $replacing_text : static::getDefaultReplacingText();
                $handled_string = '<span style="text-decoration: underline">' .  $handled_string . '</span>';
                break;
            default:
                return $obfuscated_string;
        }
        return $handled_string;
    }

    /**
     * @param $encoded_string
     * @param $obfuscated_string
     *
     * @return string
     */
    private function constructEncodedSpan($encoded_string, $obfuscated_string)
    {
        return "<span 
                data-original-string='" . $encoded_string . "'
                class='apbct-email-encoder'
                title='" . esc_attr($this->getTooltip()) . "'>" . $obfuscated_string . "</span>";
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
    public function decodeEmailFromPost()
    {
        $encoded_emails_array = Post::get('encodedEmails') ? Post::get('encodedEmails') : false;
        if ( $encoded_emails_array ) {
            $encoded_emails_array = str_replace('\\', '', $encoded_emails_array);
            $this->encoded_emails_array = json_decode($encoded_emails_array, true);
        }

        foreach ( $this->encoded_emails_array as $_key => $encoded_email) {
            $this->decoded_emails_array[$encoded_email] = $this->encoder->decodeString($encoded_email);
        }

        return $this->decoded_emails_array;
    }

    /**
     * Ajax handler for the apbct_decode_email action
     *
     * @return bool returns json string to the JS
     */
    protected function checkRequest()
    {
        return true;
    }

    /** @psalm-suppress PossiblyUnusedParam */
    protected function compileResponse($decoded_emails_array, $is_allowed)
    {
        $result = array();

        if ( empty($decoded_emails_array) ) {
            return false;
        }

        foreach ( $decoded_emails_array as $_encoded_email => $decoded_email ) {
            $result[] = strip_tags($decoded_email, '<a>');
        }
        return $result;
    }


    /*
     * =============== SERVICE ===============
     */


    /**
     * Register AJAX routes to run decoding
     * @return void
     */
    public function registerAjaxRoute()
    {
        add_action('wp_ajax_apbct_decode_email', array($this, 'ajaxDecodeEmailHandler'));
        add_action('wp_ajax_nopriv_apbct_decode_email', array($this, 'ajaxDecodeEmailHandler'));
    }

    /**
     * @return void
     */
    private function registerHookHandler()
    {
        add_filter('apbct_encode_data', [$this, 'modifyAny']);
        add_filter('apbct_encode_email_data', [$this, 'modifyContent']);
    }

    /**
     * Fluid. Ignore SSL mode for encoding/decoding on the instance.
     * @return $this
     */
    public function ignoreOpenSSLMode()
    {
        $this->encoder->useSSL(false);
        return $this;
    }

    /**
     * @return string
     */
    protected static function getDefaultReplacingText()
    {
        return 'Click to show email!';
    }

    /**
     * Drop attributes contains email from tag in the content to avoid unnecessary encoding.
     *
     * Example: <code><a title="example1@mail.com" href="mailto:example2@mail.com">Email</a></code>
     * Will be turned to <code><a href="mailto:example2@mail.com">Email</a></code>
     *
     * @param string $content The content to process.
     * @return string The content with attributes removed.
     */
    private static function dropAttributesContainEmail($content, $tags)
    {
        $attribute_content_chunk = '[\s]{0,}=[\s]{0,}[\"\']\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\..*\b[\"\']';
        foreach ($tags as $tag => $attribute) {
            // Regular expression to match the attribute without the tag
            $regexp_chunk_without_tag = "/{$attribute}{$attribute_content_chunk}/";
            // Regular expression to match the attribute with the tag
            $regexp_chunk_with_tag = "/<{$tag}.*{$attribute}{$attribute_content_chunk}/";
            // Find all matches of the attribute with the tag in the content
            preg_match_all($regexp_chunk_with_tag, $content, $matches);
            if (!empty($matches[0])) {
                // Remove the attribute without the tag from the content
                $content = preg_replace($regexp_chunk_without_tag, '', $content, count($matches[0]));
            }
        }
        return $content;
    }

    /**
     * Modify content to skip aria-label cases correctly.
     * @param string $content
     * @param bool $reverse
     *
     * @return string
     */
    private function handleAriaLabelContent($content, $reverse = false)
    {
        if ( !$reverse ) {
            $this->aria_matches = array();
            //save match
            preg_match($this->aria_regex, $content, $this->aria_matches);
            if (empty($this->aria_matches)) {
                return $content;
            }
            //replace with temp
            return preg_replace($this->aria_regex, 'ct_temp_aria', $content);
        }
        if ( !empty($this->aria_matches[0]) ) {
            //replace temp with match
            return preg_replace('/ct_temp_aria/', $this->aria_matches[0], $content);
        }
        return $content;
    }

    /**
     * @return void
     */
    private function handlePrivacyPolicyHook()
    {
        if ( !$this->privacy_policy_hook_handled && current_action() === 'the_title' ) {
            add_filter('the_privacy_policy_link', function ($link) {
                return wp_specialchars_decode($link);
            });
            $this->privacy_policy_hook_handled = true;
        }
    }
}
