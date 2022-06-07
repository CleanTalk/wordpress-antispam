<?php

namespace Cleantalk\Antispam;

use Cleantalk\Templates\Singleton;
use Cleantalk\Variables\Post;

class EmailEncoder
{
    use Singleton;

    private $secret_key;

    private $encription;

    protected function init()
    {
        global $apbct;

        if ( ! $apbct->settings['data__email_decoder'] ) {
            return;
        }

        $this->secret_key = md5($apbct->api_key);

        $this->encription = function_exists('openssl_encrypt') && function_exists('openssl_decrypt');

        $hooks_to_encode = array(
            'the_title',
            'the_content',
            'the_excerpt',
            'get_footer',
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

    public function modifyContent($content)
    {
        if ( apbct_is_user_logged_in() ) {
            return $content;
        }

        return preg_replace_callback('/(<a.*?mailto\:.*?<\/a>?)|(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+(\.[A-Za-z]{2,}))/', function ($matches) {

            if ( isset($matches[3]) && in_array(strtolower($matches[3]), ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp']) ) {
                return $matches[0];
            }

            if ( $this->isMailto($matches[0]) ) {
                return $this->encodeMailtoLink($matches[0]);
            }

            return $this->encodePlainEmail($matches[0]);
        }, $content);
    }

    public function ajaxDecodeEmailHandler()
    {
        check_ajax_referer('ct_secret_stuff');
        $this->ajaxDecodeEmail();
    }

    public function ajaxDecodeEmail()
    {
        // @ToDo implement bot checking via API. the method not implemented yet.

        $encoded_email = trim(Post::get('encodedEmail'));
        $email = $this->decodeString($encoded_email, $this->secret_key);
        wp_send_json_success(strip_tags($email, 'a'));
    }

    private function encodeEmail($plain_email, $key)
    {
        if ( $this->encription ) {
            $encoded_email = htmlspecialchars(@openssl_encrypt($plain_string, 'aes-128-cbc', $key));
        } else {
            $encoded_email = htmlspecialchars(base64_encode(str_rot13($plain_string)));
        }
        return $encoded_email;
    }

    private function decodeEmail($encoded_email, $key)
    {
        if ( $this->encription  ) {
            $decoded_email = htmlspecialchars_decode(@openssl_decrypt($encoded_string, 'aes-128-cbc', $key));
        } else {
            $decoded_email = htmlspecialchars_decode(base64_decode($encoded_string));
            $decoded_email = str_rot13($decoded_email);
        }
        return $decoded_email;
    }

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

    private function encodePlainEmail($email_str)
    {
        $obfuscated = $this->obfuscateEmail($email_str);

        $encoded = $this->encodeString($email_str, $this->secret_key);

        return '<span 
                data-original-string="' . $encoded . '" 
                class="apbct-email-encoder"
                title="' . esc_attr($this->getTooltip()) . '">' . $obfuscated . '</span>';
    }

    private function isMailto($string)
    {
        return strpos($string, 'mailto:') !== false;
    }

    private function encodeMailtoLink($mailto_link_str)
    {
        // Get inner tag text and place it in $matches[1]
        preg_match('/<a.*?mailto\:.*?>(.*?)<\/a>?/', $mailto_link_str, $matches);
        if ( isset($matches[1]) ) {
            $mailto_inner_text = preg_replace_callback('/\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}/', function ($matches) {
                return $this->obfuscateEmail($matches[0]);
            }, $matches[1]);
        }
        $encoded = $this->encodeString($mailto_link_str, $this->secret_key);

        $text = isset($mailto_inner_text) ? $mailto_inner_text : $mailto_link_str;

        return '<span 
                data-original-string="' . $encoded . '" 
                class="apbct-email-encoder"
                title="' . esc_attr($this->getTooltip()) . '">' . $text . '</span>';
    }

    private function getTooltip()
    {
        return esc_html__('This contact was encoded by CleanTalk. Click to decode.', 'cleantalk-spam-protect');
    }
}
