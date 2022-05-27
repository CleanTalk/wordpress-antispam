<?php

namespace Cleantalk\Antispam;

use Cleantalk\ApbctWP\Escape;
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
        return preg_replace_callback('/([_A-Za-z0-9-]+(\.[_A-Za-z0-9-]+)*@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*(\.[A-Za-z]{2,}))/', function ($matches) {
            if ( in_array(strtolower($matches[4]), ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp']) ) {
                return $matches[0];
            }

            $obfuscated = $this->obfuscateEmail($matches[0]);

            $encoded = $this->encodeEmail($matches[0], $this->secret_key);

            $tooltip = esc_html__('This contact was encoded by CleanTalk. Click to decode.', 'cleantalk-spam-protect');

            return '<span 
                data-original-string="' . $encoded . '" 
                class="apbct-email-encoder"
                title="' . esc_attr($tooltip) . '">' . $obfuscated . '</span>';
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
        $email = $this->decodeEmail($encoded_email, $this->secret_key);
        wp_send_json_success(Escape::escHtml($email));
    }

    private function encodeEmail($plain_email, $key)
    {
        if ( $this->encription ) {
            $encoded_email = htmlspecialchars(@openssl_encrypt($plain_email, 'aes-128-cbc', $key));
        } else {
            $encoded_email = htmlspecialchars(base64_encode(str_rot13($plain_email)));
        }
        return $encoded_email;
    }

    private function decodeEmail($encoded_email, $key)
    {
        if ( $this->encription  ) {
            $decoded_email = htmlspecialchars_decode(@openssl_decrypt($encoded_email, 'aes-128-cbc', $key));
        } else {
            $decoded_email = htmlspecialchars_decode(base64_decode($encoded_email));
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
}
