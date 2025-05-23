<?php

namespace Cleantalk\Antispam\EmailEncoder\Shortcodes;

/**
 * Email encoder shortcode base class.
 *
 * This class provides a base implementation for handling email encoder shortcodes.
 * It includes methods for processing shortcodes and modifying content before and after encoding.
 */
class EmailEncoderShortCode extends \Cleantalk\ApbctWP\ShortCode
{
    /**
     * @var string The public name of the shortcode.
     */
    protected $public_name;

    /**
     * Processes the content by checking for and executing the shortcode.
     *
     * @param string $content The content to process.
     * @return string The processed content with the shortcode executed, if present.
     */
    protected function doCallbackAction($content)
    {
        // Check if shortcode exists in content
        if (has_shortcode($content, $this->public_name)) {
            // Process the shortcode
            $content = do_shortcode($content);
        }
        return $content;
    }

    /**
     * Modifies the content before the encoder processes it.
     *
     * @param string $content The content to modify.
     * @return string The modified content.
     * @psalm-suppress PossiblyUnusedMethod
     */
    protected function changeContentBeforeEncoderModify($content)
    {
        return $content;
    }

    /**
     * Modifies the content after the encoder processes it.
     *
     * @param string $content The content to modify.
     * @return string The modified content.
     * @psalm-suppress PossiblyUnusedMethod
     */
    protected function changeContentAfterEncoderModify($content)
    {
        return $content;
    }
}
