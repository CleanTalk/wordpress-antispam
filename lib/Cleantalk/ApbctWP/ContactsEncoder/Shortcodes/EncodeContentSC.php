<?php

namespace Cleantalk\ApbctWP\ContactsEncoder\Shortcodes;

use Cleantalk\ApbctWP\ContactsEncoder\ContactsEncoder;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use Cleantalk\Common\ContactsEncoder\Exclusions\ExclusionsService;

/**
 * Shortcode to encode any string content.
 *
 * This class defines a shortcode that encodes content using the EmailEncoder.
 * It includes methods to handle the shortcode callback and modify content
 * before and after encoding.
 */
class EncodeContentSC extends EmailEncoderShortCode
{
    /**
     * @var string The public name of the shortcode.
     */
    protected $public_name = 'apbct_encode_data';
    /**
     * @var array Stores replacements for shortcodes to protect them during encoding.
     */
    public $shortcode_replacements = array();
    /**
     * @var string The wrapper used to mark content for inclusion during encoding.
     */
    public $exclusion_wrapper = '%%APBCT_SHORT_CODE_INCLUDE_EE_0%%';

    /**
     * @var int Counter for shortcode placeholders
     */
    private $shortcode_counter = 0;

    /**
     * @var ExclusionsService
     */
    private $exclusions;

    /**
     * @param string $public_name
     */
    public function __construct(Params $params)
    {
        $this->exclusions = new ExclusionsService($params);
    }


    /**
     * Callback function for the shortcode.
     *
     * Encodes the content using the EmailEncoder unless a specific cookie is set.
     *
     * @param array $_atts Attributes passed to the shortcode.
     * @param string|null $content The content enclosed by the shortcode.
     * @param string $_tag The name of the shortcode tag.
     *
     * @return string The encoded content or the original content if the cookie is set.
     * @psalm-suppress PossiblyUnusedReturnValue, TooFewArguments
     * @throws \Exception
     */
    public function callback($_atts, $content, $_tag)
    {
        if (is_null($content)) {
            return '';
        }

        if ( Cookie::get('apbct_email_encoder_passed') === apbct_get_email_encoder_pass_key() ) {
            return (string)$content;
        }

        $mode = Params::OBFUSCATION_MODE_BLUR;
        if (isset($_atts['mode']) && in_array($_atts['mode'], [Params::OBFUSCATION_MODE_BLUR, Params::OBFUSCATION_MODE_OBFUSCATE, Params::OBFUSCATION_MODE_REPLACE])) {
            $mode = $_atts['mode'];
        }

        if (isset($_atts['replacing_text']) && !empty($_atts['replacing_text']) && is_string($_atts['replacing_text'])) {
            $replacing_text = esc_html($_atts['replacing_text']);
        } else {
            $replacing_text = null;
        }

        return apbctGetContactsEncoder()->modifyShortcodeContent($content, $mode, $replacing_text);
    }

    /**
     * Modifies the content before the encoder processes it.
     *
     * Extracts shortcode content and replaces it with placeholders to protect it
     * from being encoded.
     *
     * @param string $content The content to modify.
     * @return string The modified content with placeholders for shortcodes.
     * @psalm-suppress PossiblyUnusedReturnValue
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function changeContentBeforeEncoderModify($content)
    {
        if ( $this->exclusions->doReturnContentBeforeModify($content) ) {
            return $content;
        }

        // skip encoding if the content is already encoded with hook
        // Extract shortcode content to protect it from email encoding
        $shortcode_exist_pattern = sprintf('/\[%s\](.*?)\[\/%s\]/s', $this->public_name, $this->public_name);
        $content = preg_replace_callback($shortcode_exist_pattern, function ($matches) {
            $placeholder = preg_replace('/EE\_\d+/', 'EE_' . (string)$this->shortcode_counter++, $this->exclusion_wrapper);
            if (is_null($placeholder)) {
                $placeholder = $this->exclusion_wrapper;
            }
            if (isset($matches[0])) {
                $this->shortcode_replacements[$placeholder] = $matches[0];
            }

            return $placeholder;
        }, $content);

        return $content;
    }

    /**
     * Modifies the content after the encoder processes it.
     *
     * Restores the original shortcodes from placeholders and executes the callback action.
     *
     * @param string $content The content to modify.
     * @return string The modified content with restored shortcodes.
     * @psalm-suppress PossiblyUnusedReturnValue
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function changeContentAfterEncoderModify($content)
    {
        // Restore shortcodes
        $content = $content === null ? '' : $content;

        foreach ($this->shortcode_replacements as $placeholder => $original) {
            $content = str_replace($placeholder, $original, $content);
        }
        return $this->doCallbackAction($content);
    }
}
