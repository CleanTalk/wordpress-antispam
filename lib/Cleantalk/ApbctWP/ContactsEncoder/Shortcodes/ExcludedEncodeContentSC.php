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
}
