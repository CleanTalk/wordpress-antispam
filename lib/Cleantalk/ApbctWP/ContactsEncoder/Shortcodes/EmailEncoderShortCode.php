<?php

namespace Cleantalk\ApbctWP\ContactsEncoder\Shortcodes;

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

    /**
     * Checks whether any shortcode occurrence is located inside an HTML tag.
     *
     * This validation is used to prevent shortcode extraction from HTML
     * attribute contexts such as:
     *
     * <a title="[apbct_encode_data]...[/apbct_encode_data]">
     *
     * Processing shortcodes inside HTML tags may lead to malformed markup
     * after WordPress content filters (e.g. wptexturize()) mutate surrounding
     * content. Such mutations may potentially lead to attribute injection or
     * mutation-XSS issues.
     *
     * The method scans all opening and closing shortcode tags and verifies
     * whether their offsets are located between an unclosed "<" and ">" pair.
     *
     * @param string $content The content to validate.
     *
     * @return bool True if any shortcode boundary is detected inside an HTML tag,
     *              false otherwise.
     */
    protected function isShortcodeInsideHtmlTag($content)
    {
        preg_match_all(
            sprintf(
                '/\[\/?%s(?:\s[^\]]*)?\]/', //supports sc attributes(!)
                preg_quote($this->public_name, '/')
            ),
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        if (isset($matches[0])) {
            foreach ($matches[0] as $match) {
                $offset = $match[1] ?? null;

                if ($offset === null) {
                    continue;
                }

                if ($this->isOffsetInsideHtmlTag($content, $offset)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determines whether a given character offset is located inside an HTML tag.
     *
     * The method locates the nearest "<" before the offset, then scans forward
     * respecting quoted attribute values. If no unquoted ">" is found between
     * the last "<" and the offset, the offset is considered inside an HTML tag.
     *
     * @param string $content The full content string.
     * @param int    $offset  Character offset to validate.
     *
     * @return bool True if the offset is located inside an HTML tag,
     *              false otherwise.
     */
    public function isOffsetInsideHtmlTag($content, $offset)
    {
        $before = substr($content, 0, $offset);

        $last_open = strrpos($before, '<');
        if ($last_open === false) {
            return false;
        }

        // Scan from the last '<' to the offset and detect an unquoted '>'
        $segment = substr($before, $last_open);
        $in_single = false;
        $in_double = false;
        $len = strlen($segment);
        for ($i = 0; $i < $len; $i++) {
            $ch = $segment[$i];
            if ($ch === "'" && ! $in_double) {
                $in_single = ! $in_single;
                continue;
            }
            if ($ch === '"' && ! $in_single) {
                $in_double = ! $in_double;
                continue;
            }
            if ($ch === '>' && ! $in_single && ! $in_double) {
                return false;
            }
        }
        return true;
    }
}
