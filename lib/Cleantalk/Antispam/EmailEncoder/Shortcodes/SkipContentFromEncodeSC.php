<?php

namespace Cleantalk\Antispam\EmailEncoder\Shortcodes;

/**
 * Shortcode to skip content from any encoding.
 *
 * This class defines a shortcode that wraps content in a special exclusion wrapper
 * to prevent it from being encoded. It also provides methods to modify content
 * before and after encoding, and to check if content is excluded.
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
    private $exclusion_wrapper = '%%APBCT_SHORT_CODE_EXCLUDE_EE%%';

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
        $wrapper = $this->exclusion_wrapper;
        $content = $wrapper . $content . $wrapper;
        return $content;
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
        $content = str_replace(
            $this->exclusion_wrapper,
            '',
            $content
        );
        return $content;
    }

    /**
     * Checks if the content is excluded from encoding.
     *
     * Uses a regular expression to determine if the content is wrapped
     * in the exclusion wrapper.
     *
     * @param string $content The content to check.
     * @return false|int Returns 1 if the content is excluded, 0 otherwise.
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function isContentExcluded($content)
    {
        $exclusion_regex = '/' . $this->exclusion_wrapper . '.*' . $this->exclusion_wrapper . '/';
        return preg_match($exclusion_regex, $content);
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
