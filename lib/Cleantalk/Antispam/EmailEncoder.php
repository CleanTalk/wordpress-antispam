<?php

namespace Cleantalk\Antispam;

use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;
use Cleantalk\Templates\Singleton;
use Cleantalk\ApbctWP\Variables\Post;

class EmailEncoder
{
    use Singleton;

    /**
     * @var string
     */
    private $secret_key;
    /**
     * Sign to split encoded data via encoded chunk and initializing vector chunk
     * @var string
     */
    private $encrypted_string_splitter;
    /**
     * Cipher global algorithm
     * @var string
     */
    private $cipher_algo = "AES-128-CBC";

    /**
     * @var bool Show if the encryption functions are avaliable in current surroundings
     */
    private $encryption_is_available;

    /**
     * Keep arrays of exclusion signs in the array
     * @var array
     */
    private $content_exclusions_signs = array(
        //divi contact forms additional emails
        array('_unique_id', 'et_pb_contact_form'),
        //divi builder contact forms
        array('_builder_version', 'custom_contact_email'),
        //ninja form noscript content exclusion
        array('ninja-forms-noscript-message'),
        //enfold theme contact form content exclusion - this fired during buffer interception
        array('av_contact', 'email', 'from_email'),
        // Stylish Cost Calculator
        array('scc-form-field-item'),
        // Exclusion of maps from leaflet
        array('leaflet'),
    );

    /**
     * Attribute names to skip content encoding contains them. Keep arrays of tag=>[attributes].
     * @var array[]
     */
    private $attribute_exclusions_signs = array(
        'input' => array('placeholder', 'value'),
        'img' => array('alt', 'title'),
    );

    /**
     * Attributes with possible email-like content to drop from the content to avoid unnecessary encoding.
     * Key is a tag we want to find, value is an attribute with email to drop.
     * @var array
     */
    private static $attributes_to_drop = array(
        'a' => 'title',
        );

    /**
     * @var string[]
     */
    protected $decoded_emails_array;
    /**
     * @var string[]
     */
    protected $encoded_emails_array;

    /**
     * @var string
     */
    private $response;

    /**
     * Temporary content to use in regexp callback
     * @var string
     */
    private $temp_content;
    protected $has_connection_error;
    protected $privacy_policy_hook_handled = false;
    protected $aria_regex = '/aria-label.?=.?[\'"].+?[\'"]/';
    protected $aria_matches = array();
    /**
     * @var bool
     */
    private $use_ssl = true;

    /**
     * @inheritDoc
     */
    protected function init()
    {
        global $apbct;

        if ( ! apbct_api_key__is_correct() || ! $apbct->key_is_ok ) {
            return;
        }

        $this->registerShortcodeForEncoding();

        $this->registerHookHandler();

        if ( ! $apbct->settings['data__email_decoder'] ) {
            return;
        }

        $this->secret_key = md5($apbct->api_key);
        $this->encrypted_string_splitter = substr($this->secret_key, 0, 3);

        $this->encryption_is_available = function_exists('openssl_encrypt') &&
            function_exists('openssl_decrypt') &&
            function_exists('openssl_cipher_iv_length') &&
            function_exists('openssl_random_pseudo_bytes') &&
            !empty($this->encrypted_string_splitter) && strlen($this->encrypted_string_splitter) === 3;

        // Excluded request
        if ($this->isExcludedRequest()) {
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
        foreach ( $hooks_to_encode as $hook ) {
            add_filter($hook, array($this, 'modifyContent'));
        }

        // Search data to buffer
        if ($apbct->settings['data__email_decoder_buffer'] && !apbct_is_ajax() && !apbct_is_rest() && !apbct_is_post()) {
            add_action('wp', 'apbct_buffer__start');
            add_action('shutdown', 'apbct_buffer__end', 0);
            add_action('shutdown', array($this, 'bufferOutput'), 2);
        }

        // integration with Business Directory Plugin
        add_filter('wpbdp_form_field_display', array($this, 'modifyFormFieldDisplay'), 10, 4);
    }

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
     * @param $content string
     *
     * @return string
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function modifyContent($content)
    {
        if ( apbct_is_user_logged_in() && !apbct_is_in_uri('options-general.php?page=cleantalk') ) {
            return $content;
        }

        //skip empty or invalid content
        if ( empty($content) || !is_string($content) ) {
            return $content;
        }

        // skip encoding if the content is already encoded with hook
        // Extract shortcode content to protect it from email encoding
        $shortcode_pattern = '/\[apbct_encode_data\](.*?)\[\/apbct_encode_data\]/s';
        $shortcode_replacements = [];
        $shortcode_counter = 0;
        $content = preg_replace_callback($shortcode_pattern, function ($matches) use (&$shortcode_replacements, &$shortcode_counter) {
            $placeholder = '%%APBCT_SHORTCODE_' . ($shortcode_counter++) . '%%';
            if (isset($matches[0])) {
                $shortcode_replacements[$placeholder] = $matches[0];
            }
            return $placeholder;
        }, $content);

        if ( static::skipEncodingOnHooks() ) {
            return $content;
        }

        if ( $this->hasContentExclusions($content) ) {
            return $content;
        }

        // modify content to prevent aria-label replaces by hiding it
        $content = $this->modifyAriaLabelContent($content);

        //will use this in regexp callback
        $this->temp_content = $content;

        $content = self::dropAttributesContainEmail($content, self::$attributes_to_drop);

        $content = $this->modifyEmails($content);

        // Restore shortcodes
        foreach ($shortcode_replacements as $placeholder => $original) {
            $content = str_replace($placeholder, $original, $content);
        }

        return $content;
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
        $attribute_content_chunk = '[\s]{0,}=[\s]{0,}[\"\']\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}[\"\']';
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
     * @param string $content
     * @return string
     * @psalm-suppress PossiblyUnusedReturnValue
     * @phpcs:disable PHPCompatibility.FunctionUse.NewFunctionParameters.preg_replace_callback_flagsFound
     */
    public function modifyEmails($content)
    {
        $pattern = '/(mailto\:\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,})|(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+(\.[A-Za-z]{2,}))/';
        $replacing_result = '';

        if ( version_compare(phpversion(), '7.4.0', '>=') ) {
            $replacing_result = preg_replace_callback($pattern, function ($matches) use ($content) {
                if ( isset($matches[3][0], $matches[0][0]) && in_array(strtolower($matches[3][0]), ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp']) ) {
                    return $matches[0][0];
                }

                //chek if email is placed in excluded attributes and return unchanged if so
                if ( isset($matches[0][0]) && $this->hasAttributeExclusions($matches[0][0]) ) {
                    return $matches[0][0];
                }

                if ( isset($matches[0][0]) && $this->isMailto($matches[0][0]) ) {
                    return $this->encodeMailtoLinkV2($matches[0], $content);
                }

                if ( isset($matches[0]) && $this->isMailtoAdditionalCopy($matches[0], $content) ) {
                    return '';
                }

                if ( isset($matches[0], $matches[0][0]) && $this->isEmailInLink($matches[0], $content) ) {
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
                if ( isset($matches[0]) && $this->hasAttributeExclusions($matches[0]) ) {
                    return $matches[0];
                }

                if ( isset($matches[0]) &&  $this->isMailto($matches[0]) ) {
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
        $replacing_result = $this->modifyAriaLabelContent($replacing_result, true);

        //please keep this var (do not simplify the code) for further debug
        return $replacing_result;
    }

    public function modifyAny($string)
    {
        $encoded_string = $this->encodeAny($string);

        //please keep this var (do not simplify the code) for further debug
        return $encoded_string;
    }

    /**
     * Ajax handler for the apbct_decode_email action
     *
     * @return void returns json string to the JS
     */
    public function ajaxDecodeEmailHandler()
    {
        if (! defined('REST_REQUEST') && !apbct_is_user_logged_in()) {
            check_ajax_referer('ct_secret_stuff');
        }

        // use non ssl mode for logged in user on settings page
        if ( apbct_is_user_logged_in() ) {
            $this->decoded_emails_array = $this->ignoreOpenSSLMode()->decodeEmailFromPost();
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
            $this->decoded_emails_array[$encoded_email] = $this->decodeString($encoded_email);
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

    /**
     * Encoding any string
     *
     * @param $plain_string string
     *
     * @return string
     */
    public function encodeString($plain_string)
    {
        if ( $this->use_ssl && $this->encryption_is_available ) {
            $encoded_email = htmlspecialchars($this->openSSLEncrypt($plain_string));
        } else {
            $encoded_email = htmlspecialchars(base64_encode(str_rot13($plain_string)));
        }
        return $encoded_email;
    }

    /**
     * Encrypts a given plain string using the AES-128-CBC cipher algorithm and returns the encoded string.
     *
     * @param string $plain_string The plain text string that needs to be encrypted.
     * @return string The encrypted string, which is a combination of the base64-encoded initialization vector (IV) and the encrypted data, separated by a predefined splitter.
     */
    private function openSSLEncrypt($plain_string)
    {
        global $apbct;
        try {
            if (!is_string($plain_string) || empty($plain_string)) {
                throw new \Exception('Invalid or empty plain string');
            }
            // Determine the length of the IV required for the AES-128-CBC cipher algorithm
            $iv_length = openssl_cipher_iv_length($this->cipher_algo);
            if ($iv_length === false) {
                throw new \Exception('Can\'t generate initializing vector length');
            }
            // Generate a random IV of the required length
            $iv = openssl_random_pseudo_bytes($iv_length);
            if (empty($iv)) {
                throw new \Exception('Can\'t generate initializing vector body');
            }
            // Encrypt the plain string using the specified cipher algorithm, secret key, and IV
            $encoded_string = @openssl_encrypt($plain_string, $this->cipher_algo, $this->secret_key, 0, $iv);
            if (empty($encoded_string)) {
                throw new \Exception('Can\'t encode plain string');
            }
            if (!function_exists('base64_encode')) {
                throw new \Exception('Can\'t run base64_encode');
            }
            // Base64-encode the IV and concatenate it with the encrypted string, separated by a predefined splitter
            $encoded_string = base64_encode($iv) . $this->encrypted_string_splitter . $encoded_string;

            // Return the combined string
            return $encoded_string;
        } catch (\Exception $e) {
            //todo catch errors on higher level
            $get_last_error = error_get_last();
            $get_last_error = isset($get_last_error['message']) ? $get_last_error['message'] : 'no PHP error';
            $apbct->errorAdd('email_encoder', esc_html($e->getMessage()) . ', backtrace: ' . $get_last_error);
            return $plain_string;
        }
    }

    /**
     * Decoding previously encoded string
     *
     * @param $encoded_string string
     *
     * @return string
     */
    public function decodeString($encoded_string)
    {
        if ( $this->use_ssl && $this->encryption_is_available ) {
            $decoded_string = htmlspecialchars_decode($this->openSSLDecrypt($encoded_string));
        } else {
            $decoded_string = htmlspecialchars_decode(base64_decode($encoded_string));
            $decoded_string = str_rot13($decoded_string);
        }
        return $decoded_string;
    }

    /**
     * Decrypts a given encoded string using the AES-128-CBC cipher algorithm and returns the decoded string.
     *
     * @param string $encoded_string The encoded string that needs to be decrypted.
     * @return string The decrypted string.
     */
    private function openSSLDecrypt($encoded_string)
    {
        global $apbct;
        try {
            if (!is_string($encoded_string) || empty($encoded_string)) {
                throw new \Exception('Invalid or empty encoded string');
            }
            // Find the position of the splitter in the encoded string
            $splitter_position = strpos($encoded_string, $this->encrypted_string_splitter);

            if (empty($splitter_position)) {
                throw new \Exception('Can\'t split string');
            }

            // Extract the IV chunk from the encoded string
            $iv_chunk = substr($encoded_string, 0, $splitter_position);

            if (empty($iv_chunk)) {
                throw new \Exception('Can\'t get initializing vector string');
            }

            // Extract the encoded data chunk from the encoded string
            $encoded_data_chunk = substr($encoded_string, $splitter_position + strlen($this->encrypted_string_splitter));

            if (empty($encoded_data_chunk)) {
                throw new \Exception('Can\'t get encoded data');
            }

            if (!function_exists('base64_decode')) {
                throw new \Exception('Can\'t run base64_decode');
            }

            // Decode the IV chunk from base64
            $iv_chunk_decoded = base64_decode($iv_chunk);

            if (empty($iv_chunk_decoded)) {
                throw new \Exception('Can\'t decode initializing vector string');
            }

            // Decrypt the encoded data chunk using the specified cipher algorithm, secret key, and IV
            $decoded_string = @openssl_decrypt($encoded_data_chunk, $this->cipher_algo, $this->secret_key, 0, $iv_chunk_decoded);

            if (empty($decoded_string)) {
                throw new \Exception('Can\'t finish SSL decryption');
            }

            // Return the decrypted string
            return $decoded_string;
        } catch (\Exception $e) {
            //todo catch errors on higher level
            $get_last_error = error_get_last();
            $get_last_error = isset($get_last_error['message']) ? $get_last_error['message'] : 'no PHP error';
            $apbct->errorAdd('email_encoder', esc_html($e->getMessage()) . ', backtrace: ' . $get_last_error);
            return '';
        }
    }

    /**
     * Obfuscate an email to the s****@**.com view
     *
     * @param $email string
     *
     * @return string
     */
    private function obfuscateEmail($email)
    {
        /** @psalm-suppress PossiblyFalseOperand, PossiblyFalseArgument */
        $first_part = strpos($email, '@') > 2
            ? substr($email, 0, 2) . str_pad('', strpos($email, '@') - 2, '*')
            : str_pad('', strpos($email, '@'), '*');
        /** @psalm-suppress PossiblyFalseOperand, PossiblyFalseArgument */
        $second_part = substr($email, strpos($email, '@') + 1, 2)
                    . str_pad('', strpos($email, '.', strpos($email, '@')) - 3 - strpos($email, '@'), '*');
        /** @psalm-suppress PossiblyFalseOperand, PossiblyFalseArgument */
        $last_part = substr($email, (int) strrpos($email, '.', -1) - strlen($email));

        return $first_part . '@' . $second_part . $last_part;
    }

    private function obfuscateString($string)
    {
        $length = strlen($string);
        $first_part = substr($string, 0, 2);
        $last_part = substr($string, $length - 2, 2);
        $middle_part = str_pad('', $length - 4, '*');
        return $first_part . $middle_part . $last_part;
    }

    /**
     * Method to process plain email
     *
     * @param $email_str string
     *
     * @return string
     */
    private function encodePlainEmail($email_str)
    {
        $obfuscated = $this->obfuscateEmail($email_str);

        $encoded = $this->encodeString($email_str);

        return '<span 
                data-original-string="' . $encoded . '"
                class="apbct-email-encoder"
                title="' . esc_attr($this->getTooltip()) . '">' . $this->addMagicBlur($obfuscated) . '</span>';
    }

    private function encodeAny($string)
    {
        $obfuscated = $this->obfuscateString($string);

        $encoded = $this->encodeString($string);

        return "<span 
                data-original-string='" . $encoded . "'
                class='apbct-email-encoder'
                title='" . esc_attr($this->getTooltip()) . "'>" . $this->addMagicBlur($obfuscated) . "</span>";
    }

    private function addMagicBlur($obfuscated)
    {
        $first_two = substr($obfuscated, 0, 2);
        $last_two = substr($obfuscated, -2);
        return $first_two .
               '<span class="apbct-blur">' . substr($obfuscated, 2, -2) . '</span>' .
               $last_two;
    }

    /**
     * Checking if the string contains mailto: link
     *
     * @param $string string
     *
     * @return bool
     */
    private function isMailto($string)
    {
        return strpos($string, 'mailto:') !== false;
    }

    /**
     * Checking if the string contains mailto: link
     *
     * @param $match array
     * @param $content string
     *
     * @return bool
     */
    private function isMailtoAdditionalCopy($match, $content)
    {
        $position = $match[1];

        $cc_position = strrpos(substr($content, 0, $position), 'cc=');
        if ( $cc_position !== false && $cc_position + 3 == $position ) {
            return true;
        }

        $bcc_position = strrpos(substr($content, 0, $position), 'bcc=');
        if ( $bcc_position !== false && $bcc_position + 4 == $position ) {
            return true;
        }

        return false;
    }

    /**
     * Checking if email in link
     *
     * @param $match array
     * @param $content string
     *
     * @return bool
     */
    private function isEmailInLink($match, $content)
    {
        $email = $match[0];
        $position = $match[1];

        $href_position = strrpos(substr($content, 0, $position), 'href=');

        if ( $href_position !== false && $href_position + 6 == $position ) {
            return true;
        }

        return strpos($email, 'mailto:') !== false;
    }

    /**
     * Method to process mailto: links
     *
     * @param $mailto_link_str string
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
                    return $this->obfuscateEmail($matches[0]);
                }
            }, $matches[1]);
        }
        $mailto_link_str = str_replace('mailto:', '', $mailto_link_str);
        $encoded = $this->encodeString($mailto_link_str);

        $text = isset($mailto_inner_text) ? $mailto_inner_text : $mailto_link_str;

        return 'mailto:' . $text . '" data-original-string="' . $encoded . '" title="' . esc_attr($this->getTooltip());
    }

    /**
     * Method to process mailto: links
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
                    return $this->obfuscateEmail($matches[0]);
                }

                return '';
            }, $matches[1]);
        }

        $mailto_link_str = str_replace('mailto:', '', $mailto_link_str);
        $encoded = $this->encodeString($mailto_link_str);

        $text = isset($mailto_inner_text) ? $mailto_inner_text : $mailto_link_str;

        return 'mailto:' . $text . '" data-original-string="' . $encoded . '" title="' . esc_attr($this->getTooltip());
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

    /**
     * Check content if it contains exclusions from exclusion list
     * @param $content - content to check
     * @return bool - true if exclusions found, else - false
     */
    private function hasContentExclusions($content)
    {
        if ( is_array($this->content_exclusions_signs) ) {
            foreach ( array_values($this->content_exclusions_signs) as $_signs_array => $signs ) {
                //process each of subarrays of signs
                $signs_found_count = 0;
                if ( isset($signs) && is_array($signs) ) {
                    //chek all the signs in the sub-array
                    foreach ( $signs as $sign ) {
                        if ( is_string($sign) ) {
                            if ( strpos($content, $sign) === false ) {
                                continue;
                            } else {
                                $signs_found_count++;
                            }
                        }
                    }
                    //if each of signs in the sub-array are found return true
                    if ( $signs_found_count === count($signs) ) {
                        if (in_array('et_pb_contact_form', $signs) && !is_admin()) {
                            return false;
                        }
                        return true;
                    }
                }
            }
        }
        //no signs found
        return false;
    }

    /**
     * Excluded requests
     */
    private function isExcludedRequest()
    {
        // Excluded request by alt cookie
        $apbct_email_encoder_passed = Cookie::get('apbct_email_encoder_passed');
        if ( $apbct_email_encoder_passed === apbct_get_email_encoder_pass_key() ) {
            return true;
        }

        if (
            apbct_is_plugin_active('ultimate-member/ultimate-member.php') &&
            isset($_POST['um_request']) &&
            array_key_exists('REQUEST_METHOD', $_SERVER) &&
            strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' &&
            empty(Post::get('encodedEmail'))
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if email is placed in the tag that has attributes of exclusions.
     * @param $email_match - email
     * @return bool
     */
    private function hasAttributeExclusions($email_match)
    {
        foreach ( $this->attribute_exclusions_signs as $tag => $array_of_attributes ) {
            foreach ( $array_of_attributes as $attribute ) {
                $pattern = '/<' . $tag . '.*' . $attribute . '=".*' . $email_match . '.*"/';
                preg_match($pattern, $this->temp_content, $attr_match);
                if ( !empty($attr_match) ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Modify content to skip aria-label cases correctly.
     * @param string $content
     * @param bool $reverse
     *
     * @return string
     */
    private function modifyAriaLabelContent($content, $reverse = false)
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

    public function bufferOutput()
    {
        global $apbct;
        echo $this->modifyContent($apbct->buffer);
    }

    private function handlePrivacyPolicyHook()
    {
        if ( !$this->privacy_policy_hook_handled && current_action() === 'the_title' ) {
            add_filter('the_privacy_policy_link', function ($link) {
                return wp_specialchars_decode($link);
            });
            $this->privacy_policy_hook_handled = true;
        }
    }

    /**
     * Skip encoder run on hooks.
     *
     * 1. Applies filter "apbct_hook_skip_email_encoder_on_url_list" to get modified list of URI chunks that needs to skip.
     * @return bool
     */
    private static function skipEncodingOnHooks()
    {
        $skip_encode = false;
        $url_chunk_list = array();

        // Apply filter "apbct_hook_skip_email_encoder_on_url_list" to get the URI chunk list.
        $url_chunk_list = apply_filters('apbct_skip_email_encoder_on_uri_chunk_list', $url_chunk_list);

        if ( !empty($url_chunk_list) && is_array($url_chunk_list) ) {
            foreach ($url_chunk_list as $chunk) {
                if (is_string($chunk) && strpos(TT::toString(Server::get('REQUEST_URI')), $chunk) !== false) {
                    $skip_encode = true;
                    break;
                }
            }
        }

        return $skip_encode;
    }

    /**
     * Fluid. Ignore SSL mode for encoding/decoding on the instance.
     * @return $this
     */
    public function ignoreOpenSSLMode()
    {
        $this->use_ssl = false;
        return $this;
    }

    /**
     * Register AJAX routes to run decoding
     * @return void
     */
    public function registerAjaxRoute()
    {
        add_action('wp_ajax_apbct_decode_email', array($this, 'ajaxDecodeEmailHandler'));
        add_action('wp_ajax_nopriv_apbct_decode_email', array($this, 'ajaxDecodeEmailHandler'));
    }

    private function registerShortcodeForEncoding()
    {
        add_shortcode('apbct_encode_data', [$this, 'shortcodeCallback']);
    }

    public function shortcodeCallback($_atts, $content, $_tag)
    {
        if ( Cookie::get('apbct_email_encoder_passed') === apbct_get_email_encoder_pass_key() ) {
            return $content;
        }

        return $this->modifyAny($content);
    }

    private function registerHookHandler()
    {
        add_filter('apbct_encode_data', [$this, 'modifyAny']);
        add_filter('apbct_encode_email_data', [$this, 'modifyContent']);
    }
}
