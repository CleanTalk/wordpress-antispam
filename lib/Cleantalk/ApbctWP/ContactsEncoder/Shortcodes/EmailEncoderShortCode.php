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
     * @var string Wrapper template overridden by child classes as the placeholder source.
     */
    protected $exclusion_wrapper = '';

    // Placeholder nonce for ensuring unique placeholders per render.
    protected $placeholder_nonce = '';

    /**
     * Build a placeholder for the given counter using the child's $exclusion_wrapper as a template.
     * Lazy-initialises the per-render nonce so replacements survive isolated render passes.
     *
     * @param int $counter
     *
     * @return string
     */
    protected function buildPlaceholder($counter)
    {
        if ($this->placeholder_nonce === '') {
            $this->placeholder_nonce = $this->generatePlaceholderNonce();
        }

        $wrapper = (string)$this->exclusion_wrapper;
        $placeholder = preg_replace(
            '/EE\_\d+/',
            'EE_' . (string)$counter . '_' . $this->placeholder_nonce,
            $wrapper
        );

        return is_null($placeholder) ? $wrapper : $placeholder;
    }

    /**
     * Rotates the placeholder nonce to ensure unique placeholders for subsequent renders.
     * @return void
     */
    protected function rotatePlaceholderNonce()
    {
        $this->placeholder_nonce = $this->generatePlaceholderNonce();
    }

    /**
     * Generates a new high-entropy nonce for placeholders.
     * @return string
     */
    protected function generatePlaceholderNonce()
    {
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes(16));
            } catch (\Exception $e) {
                // fall through to WP fallback
            }
        }
        if (function_exists('wp_generate_password')) {
            return strtolower(wp_generate_password(32, false));
        }
        return substr(hash('sha256', uniqid((string)mt_rand(), true)), 0, 32);
    }

    /**
     * Process only this encoder's shortcode tags in the content.
     *
     * Must not call do_shortcode() on the full string: untrusted content (e.g. comments)
     * could trigger execution of arbitrary site shortcodes nested in or beside the tag.
     *
     * @param string $content The content to process.
     * @return string The processed content with only this shortcode executed.
     */
    protected function doCallbackAction($content)
    {
        if ( ! is_string($content) || $this->public_name === '' ) {
            return $content;
        }

        if ( ! has_shortcode($content, $this->public_name) ) {
            return $content;
        }

        $pattern = sprintf(
            '/(\[%s(?:\s[^\]]*)?\])([\s\S]*?)(\[\/%s\])/s',
            preg_quote($this->public_name, '/'),
            preg_quote($this->public_name, '/')
        );

        return preg_replace_callback(
            $pattern,
            function ($matches) {
                $atts = array();
                if ( isset($matches[1]) && preg_match('/\[' . preg_quote($this->public_name, '/') . '([^\]]*)\]/', $matches[1], $tag_matches) && isset($tag_matches[1]) ) {
                    $parsed_atts = shortcode_parse_atts($tag_matches[1]);
                    $atts = is_array($parsed_atts) ? $parsed_atts : array();
                }

                $inner_content = isset($matches[2]) ? $matches[2] : '';

                return $this->callback($atts, $inner_content, $this->public_name);
            },
            $content
        );
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
        if ( ! is_string($content) ) {
            return false;
        }

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
