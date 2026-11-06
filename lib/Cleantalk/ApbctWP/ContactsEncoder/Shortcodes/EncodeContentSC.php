<?php

namespace Cleantalk\ApbctWP\ContactsEncoder\Shortcodes;

use Cleantalk\ApbctWP\ContactsEncoder\ContactsEncoder;
use Cleantalk\ApbctWP\Escape;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use Cleantalk\ApbctWP\ContactsEncoder\Exclusions\ExclusionsService;

/**
 * Shortcode to encode any string content.
 *
 * This class defines a shortcode that encodes content using the EmailEncoder.
 * It includes methods to handle the shortcode callback and modify content
 * before and after encoding.
 */
class EncodeContentSC extends EmailEncoderShortCode
{
    const MAX_REPLACING_TEXT_LENGTH = 100;

    /**
     * @var string The public name of the shortcode.
     */
    protected $public_name = 'apbct_encode_data';
    /**
     * @var array Stores replacements for shortcodes to protect them during encoding.
     */
    public $shortcode_replacements = array();
    /**
     * @var array<string, string> Masks of the replacements, for a placeholder that lands inside a tag.
     */
    private $shortcode_masks = array();
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
     * The author asked to hide this text: over the budget it is masked without encoding.
     *
     * @param array{opener_start: int, inner_start: int, closer_start: int, end: int, attributes: string, inner: string, opener: string, closer: string, over_limit: bool} $pair
     *
     * @return string
     */
    protected function replacePairOverLimit($pair)
    {
        return $this->maskText($pair['inner']);
    }

    /**
     * @param string $text
     *
     * @return string
     */
    private function maskText($text)
    {
        return str_repeat('*', max(1, min(self::MAX_REPLACING_TEXT_LENGTH, mb_strlen($text))));
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

        if ( apbct_is_email_encoder_passed() ) {
            return (string)$content;
        }

        $mode = Params::OBFUSCATION_MODE_BLUR;
        if (isset($_atts['mode']) && in_array($_atts['mode'], [Params::OBFUSCATION_MODE_BLUR, Params::OBFUSCATION_MODE_OBFUSCATE, Params::OBFUSCATION_MODE_REPLACE])) {
            $mode = $_atts['mode'];
        }

        if (isset($_atts['replacing_text']) && !empty($_atts['replacing_text']) && is_string($_atts['replacing_text'])) {
            $replacing_text = esc_html(mb_substr($_atts['replacing_text'], 0, self::MAX_REPLACING_TEXT_LENGTH));
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
    public function changeContentBeforeEncoderModify($content = '')
    {
        if ( ! is_string($content) ) {
            return $content;
        }

        // Cheap pre-check: bail out before any regex if the shortcode is not present at all.
        if ( ! $this->contentMayContainShortcode($content) ) {
            return $content;
        }

        if ( $this->exclusions->doReturnShortcodeContentBeforeModify($content) ) {
            return $content;
        }

        if ($this->isShortcodeInsideHtmlTag($content)) {
            return $content;
        }

        // skip encoding if the content is already encoded with hook
        // Extract shortcode content to protect it from email encoding, supports sc attributes(!)
        return $this->replaceShortcodePairs($content, function ($pair) {
            $placeholder = $this->buildPlaceholder($this->shortcode_counter++);
            $this->shortcode_replacements[$placeholder] = $pair['opener'] . Escape::escKsesPost($pair['inner']) . $pair['closer'];
            $this->shortcode_masks[$placeholder] = $this->maskText($pair['inner']);

            return $placeholder;
        });
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
    public function changeContentAfterEncoderModify($content = '')
    {
        if ( ! is_string($content) ) {
            return $content;
        }

        // Nothing was replaced and no shortcode is present - skip restoring and the callback pass.
        if ( empty($this->shortcode_replacements) && ! $this->contentMayContainShortcode($content) ) {
            return $content;
        }

        $result = $this->doCallbackAction($this->restorePlaceholders($content));
        $this->resetShortcodeReplacements();

        return $result;
    }

    /**
     * Restore the shortcodes. A placeholder inside a tag, e.g. in an attribute built by another shortcode,
     * is not a text of the page: there the shortcode is not processed, so the text is masked.
     *
     * @param string $content
     *
     * @return string
     */
    private function restorePlaceholders($content)
    {
        if ( empty($this->shortcode_replacements) ) {
            return $content;
        }

        $found = array();
        foreach ( $this->shortcode_replacements as $placeholder => $_original ) {
            $placeholder = (string)$placeholder;
            for ( $at = strpos($content, $placeholder); $at !== false; $at = strpos($content, $placeholder, $at + strlen($placeholder)) ) {
                $found[$at] = $placeholder;
            }
        }
        ksort($found);

        $result = '';
        $position = 0;
        foreach ( $found as $at => $placeholder ) {
            if ( $at < $position ) {
                continue;
            }

            $replacement = $this->isOffsetInsideHtmlTag($content, $at)
                ? (isset($this->shortcode_masks[$placeholder]) ? $this->shortcode_masks[$placeholder] : '*')
                : $this->shortcode_replacements[$placeholder];
            $result .= substr($content, $position, $at - $position) . $replacement;
            $position = $at + strlen($placeholder);
        }
        $this->forgetTagIndex();

        return $position === 0 ? $content : $result . substr($content, $position);
    }

    /**
     * @return void
     */
    public function resetShortcodeReplacements()
    {
        $this->shortcode_replacements = array();
        $this->shortcode_masks = array();
        $this->shortcode_counter = 0;
        $this->rotatePlaceholderNonce();
    }
}
