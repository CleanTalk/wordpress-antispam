<?php

namespace Cleantalk\ApbctWP\ContactsEncoder\Shortcodes;

class ExcludedEncodeContentSC extends EmailEncoderShortCode
{
    protected $public_name = 'apbct_skip_encoding';

    /**
     * Callback for the shortcode
     *
     * @param $_atts array Not used here
     * @param $content string|null Encoded content like `<span data-original-string='abc'>***@***.***</span>`
     * @param $_tag string Not used here
     *
     * @return string Decoded content like `abc@abc.com`
     */
    public function callback($_atts, $content, $_tag)
    {
        if ( ! $content ) {
            return $content;
        }

        // Pattern to get data-original-string attribute
        $pattern = '/data-original-string=(["\'])(.*?)\1/';
        preg_match($pattern, $content, $matches);

        if (isset($matches[2])) {
            $encoder = apbctGetContactsEncoder();
            $decoded_data = $encoder->decodeContactData([$matches[2]]);
            if ( $decoded_data && is_array($decoded_data) ) {
                return current($decoded_data);
            }
        }

        return $content;
    }

    /**
     * This method runs at the end of Contacts Encoder and tries to process unprocessed shortcodes
     * The unprocessed shortcodes may be only in `the_title` hook
     *
     * @param string $content
     *
     * @return string Replaces $apbct->buffer by probably modified content or just return probably modified $content
     */
    public function changeContentAfterEncoderModify($content)
    {
        global $apbct;

        if ( ! $apbct->settings['data__email_decoder_buffer'] && current_action() !== 'the_title' ) {
            return $content;
        }

        if ( $apbct->settings['data__email_decoder_buffer'] ) {
            $content = $apbct->buffer;
        }

        $pattern = '/\[apbct_skip_encoding\](.*?)\[\/apbct_skip_encoding\]/s';
        $result = preg_replace_callback($pattern, function ($matches) {
            // $matches[0] - all full match
            if ( isset($matches[1]) ) {
                // $matches[1] - only between tags group
                $modifiedContent = $this->callback([], $matches[1], '');
                return $modifiedContent; // Return modified (decoded) content without tags
            }
            /** @psalm-suppress PossiblyUndefinedIntArrayOffset */
            return $matches[0]; // By default, return not modified match
        }, $content);

        if ( $apbct->settings['data__email_decoder_buffer'] ) {
            $apbct->buffer = $result;
        }
        return $result;
    }
}
