<?php

namespace Cleantalk\Antispam\EmailEncoder\Shortcodes;

/**
 * Shortcode to skip content from any encoding.
 *
 * This class defines a shortcode that wraps content in a special exclusion wrapper
 * to prevent it from being encoded. It also provides methods to modify content
 * before and after encoding, and to check if content is excluded.
 *
 * @psalm-suppress UnusedClass
 */
class SkipContentFromEncodeSC extends EmailEncoderShortCode
{
    /**
     * @var string The public name of the shortcode.
     */
    protected $public_name = 'apbct_skip_encoding';
    /**
     * @var string The wrapper used to mark content for exclusion.
     */
    private $exclusion_wrapper = '##SCE_%d##';
    private $replaces = array();

    /**
     * Callback function for the shortcode.
     *
     * Wraps the content in the exclusion wrapper to prevent encoding.
     *
     * @param array $_atts Attributes passed to the shortcode.
     * @param string|null $content The content enclosed by the shortcode.
     * @param string $_tag The name of the shortcode tag.
     * @return string The content wrapped in the exclusion wrapper.
     */
    public function callback($_atts, $content, $_tag)
    {
        return $this->processExclusions($content);
    }

    /**
     * Modifies the content before the encoder processes it.
     *
     * Executes the shortcode callback action on the content.
     *
     * @param string $content The content to modify.
     * @return string The modified content.
     * @psalm-suppress PossiblyUnusedReturnValue
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function changeContentBeforeEncoderModify($content)
    {
        return $this->doCallbackAction($content);
    }

    /**
     * Modifies the content after the encoder processes it.
     *
     * Removes the exclusion wrapper from the content.
     *
     * @param string $content The content to modify.
     * @return string The modified content.
     * @psalm-suppress PossiblyUnusedReturnValue
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function changeContentAfterEncoderModify($content)
    {
        return $this->revertExclusions($content);
    }

    /**
     * Apply exclusions to replace modified shortcodes with service symbols. Then collect all the performed
     * replacements to memory storage to being reverted after common modifying.
     *
     * @param string|null $content The content to check.
     * @return string Returns content with handled exclusions
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function processExclusions($content)
    {
        if (is_null($content)) {
            return (string)$content;
        }
        $index = count($this->replaces);
        $placeholder = sprintf($this->exclusion_wrapper, $index);
        $this->replaces[$index] = [
            'origin' => $content,
            'replace' => $placeholder
        ];
        $wrappedContent = $placeholder;

        return $wrappedContent;
    }

    /**
     * Rollback al the replaces with modified shortcodes after common encoding.
     * @param string $content
     *
     * @return string
     */
    public function revertExclusions($content)
    {
        foreach ($this->replaces as $_item => $data) {
            if (isset($data['replace'], $data['origin']) && is_string($data['replace']) && is_string($data['origin'])) {
                $content = str_replace($data['replace'], $data['origin'], $content);
            }
        }
        return $content;
    }

    /**
     * Clear the title if visitor is already checked.
     * @param $content
     *
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function clearTitleContentFromShortcodeConstruction($content)
    {
        $shortcode_pattern = sprintf('/\[%s\](.*?)\[\/%s\]/s', $this->public_name, $this->public_name);
        preg_match_all($shortcode_pattern, $content, $matches);
        $data_skipped =  isset($matches[1][0]) ? $matches[1][0] : null;
        if (is_null($data_skipped)) {
            return $content;
        }
        $content_cleared = preg_replace($shortcode_pattern, $data_skipped, $content);
        return is_string($content_cleared) ? $content_cleared : $content;
    }
}
