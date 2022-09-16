<?php

namespace Cleantalk\Antispam;

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
     * @var bool Show if the encryption functions are avaliable in current surroundings
     */
    private $encryption_is_available;

    /**
     * Keep arrays of exclusion signs in the array
     * @var array
     */
    private $encoding_exclusions_signs;

    /**
     * @var string
     */
    protected $decoded_email;

    /**
     * @var string
     */
    protected $encoded_email;

    /**
     * @var string
     */
    private $response;

    /**
     * @inheritDoc
     */
    protected function init()
    {

        global $apbct;

        if ( ! $apbct->settings['data__email_decoder'] ) {
            return;
        }

        // Excluded request
        if ($this->isExcludedRequest()) {
            return;
        }

        //list of encoding exclusions signs
        $this->encoding_exclusions_signs = array(
            //divi contact forms additional emails
            array('_unique_id', 'et_pb_contact_form')
        );

        $this->secret_key = md5($apbct->api_key);

        $this->encryption_is_available = function_exists('openssl_encrypt') && function_exists('openssl_decrypt');

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
        );
        foreach ( $hooks_to_encode as $hook ) {
            add_filter($hook, array($this, 'modifyContent'));
        }

        add_action('wp_ajax_nopriv_apbct_decode_email', array($this, 'ajaxDecodeEmailHandler'));
        add_action('wp_ajax_apbct_decode_email', array($this, 'ajaxDecodeEmailHandler'));
    }

    /**
     * @param $content string
     *
     * @return string
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function modifyContent($content)
    {
        if ( apbct_is_user_logged_in() ) {
            return $content;
        }

        if ( $this->contentHasExclusions($content) ) {
            return $content;
        }

        return preg_replace_callback('/(mailto\:\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,})|(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+(\.[A-Za-z]{2,}))/', function ($matches) {

            if ( isset($matches[3]) && in_array(strtolower($matches[3]), ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp']) ) {
                return $matches[0];
            }

            if ( $this->isMailto($matches[0]) ) {
                return $this->encodeMailtoLink($matches[0]);
            }

            return $this->encodePlainEmail($matches[0]);
        }, $content);
    }

    /**
     * Ajax handler for the apbct_decode_email action
     *
     * @return void returns json string to the JS
     */
    public function ajaxDecodeEmailHandler()
    {
        if (! defined('REST_REQUEST')) {
            check_ajax_referer('ct_secret_stuff');
        }

        $this->response = $this->processDecodeRequest();

        wp_send_json_success($this->response);
    }

    public function processDecodeRequest()
    {
        $this->decoded_email = $this->decodeEmailFromPost();
        $allow_request       = $this->checkRequest();
        $this->response      = $this->compileResponse($this->decoded_email, $allow_request);

        return $this->response;
    }

    /**
     * Main logic of the decoding the encoded data.
     *
     * @return string encoded email
     */
    public function decodeEmailFromPost()
    {
        $this->encoded_email = trim(Post::get('encodedEmail'));
        $this->decoded_email = $this->decodeString($this->encoded_email, $this->secret_key);

        return $this->decoded_email;
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

    protected function compileResponse($decoded_email, $is_allowed)
    {
        return strip_tags($decoded_email, '<a>');
    }

    /**
     * Encoding any string
     *
     * @param $plain_string string
     * @param $key string
     *
     * @return string
     */
    private function encodeString($plain_string, $key)
    {
        if ( $this->encryption_is_available ) {
            $encoded_email = htmlspecialchars(@openssl_encrypt($plain_string, 'aes-128-cbc', $key));
        } else {
            $encoded_email = htmlspecialchars(base64_encode(str_rot13($plain_string)));
        }
        return $encoded_email;
    }

    /**
     * Decoding previously encoded string
     *
     * @param $encoded_string string
     * @param $key string
     *
     * @return string
     */
    private function decodeString($encoded_string, $key)
    {
        if ( $this->encryption_is_available  ) {
            $decoded_email = htmlspecialchars_decode(@openssl_decrypt($encoded_string, 'aes-128-cbc', $key));
        } else {
            $decoded_email = htmlspecialchars_decode(base64_decode($encoded_string));
            $decoded_email = str_rot13($decoded_email);
        }
        return $decoded_email;
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
        $first_part = strpos($email, '@') > 2
            ? substr($email, 0, 2) . str_pad('', strpos($email, '@') - 2, '*')
            : str_pad('', strpos($email, '@'), '*');
        $second_part = substr($email, strpos($email, '@') + 1, 2)
                       . str_pad('', strpos($email, '.', strpos($email, '@')) - 3 - strpos($email, '@'), '*');
        $last_part = substr($email, (int) strrpos($email, '.', -1) - strlen($email));
        return $first_part . '@' . $second_part . $last_part;
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

        $encoded = $this->encodeString($email_str, $this->secret_key);

        return '<span 
                data-original-string="' . $encoded . '"
                class="apbct-email-encoder"
                title="' . esc_attr($this->getTooltip()) . '">' . $obfuscated . '</span>';
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
                return $this->obfuscateEmail($matches[0]);
            }, $matches[1]);
        }
        $mailto_link_str = str_replace('mailto:', '', $mailto_link_str);
        $encoded = $this->encodeString($mailto_link_str, $this->secret_key);

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
        return esc_html__('This contact has been encoded by CleanTalk. Click to decode. To finish the decoding make sure that JavaScript is enabled in your browser.', 'cleantalk-spam-protect');
    }

    /**
     * Check content if it contains exclusions from exclusion list
     * @param $content - content to check
     * @return bool - true if exclusions found, else - false
     */
    private function contentHasExclusions($content)
    {
        if ( is_array($this->encoding_exclusions_signs) ) {
            foreach ( array_values($this->encoding_exclusions_signs) as $_signs_array => $signs ) {
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
        if (
            apbct_is_plugin_active('ultimate-member/ultimate-member.php') &&
            isset($_POST['um_request']) &&
            strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' &&
            empty(Post::get('encodedEmail'))
        ) {
            return true;
        }

        return false;
    }
}
