<?php

namespace Cleantalk\ApbctWP\ContactsEncoder\Shortcodes;

use Cleantalk\ApbctWP\ContactsEncoder\ContactsEncoder;
use Cleantalk\ApbctWP\ContactsEncoder\ContactsEncoderTagProcessor;
use Cleantalk\Common\ContactsEncoder\Helper\ContactsEncoderHelper;

/**
 * Email encoder shortcode base class.
 *
 * This class provides a base implementation for handling email encoder shortcodes.
 * It includes methods for processing shortcodes and modifying content before and after encoding.
 */
class EmailEncoderShortCode extends \Cleantalk\ApbctWP\ShortCode
{
    /**
     * Max number of shortcode pairs processed in one content.
     */
    const MAX_SHORTCODES_TO_PROCESS = 100;

    /**
     * @var string The public name of the shortcode.
     */
    protected $public_name;

    /**
     * @var bool Whether the opening tag of the shortcode may have attributes.
     */
    protected $has_attributes = false;

    /**
     * @var string Wrapper template overridden by child classes as the placeholder source.
     */
    protected $exclusion_wrapper = '';

    // Placeholder nonce for ensuring unique placeholders per render.
    protected $placeholder_nonce = '';

    /**
     * Lightweight pre-check to avoid running heavy regexes on every hit.
     *
     * Any valid shortcode pair must contain an opening tag `[public_name` — a cheap
     * strpos() lookup rejects the vast majority of content before preg_* is involved.
     *
     * @param string|null $content
     *
     * @return bool True if the content may contain this shortcode.
     */
    protected function contentMayContainShortcode($content)
    {
        if ( ! is_string($content) || $content === '' || (string)$this->public_name === '' ) {
            return false;
        }

        return strpos($content, '[' . $this->public_name) !== false;
    }

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
     * @inheritDoc
     */
    public function register()
    {
        parent::register();
        add_filter('pre_do_shortcode_tag', array($this, 'skipMalformedShortcode'), 10, 4);
    }

    /**
     * Leave the shortcode text as is if the shortcode has no enclosed content
     * or its opening tag contains markup.
     *
     * @param false|string $output Short-circuit return value
     * @param string $tag Shortcode name
     * @param array $_attr Not used here
     * @param array $m Regular expression match array
     *
     * @return false|string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function skipMalformedShortcode($output, $tag, $_attr, $m)
    {
        if ( $output !== false || $tag !== $this->public_name || ! isset($m[0]) ) {
            return $output;
        }

        $attributes = isset($m[3]) ? (string)$m[3] : '';
        $content = isset($m[5]) ? (string)$m[5] : '';

        if ( $content === '' || strpbrk($attributes, '<>') !== false ) {
            return $m[0];
        }

        return $output;
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

        if ( ! $this->contentMayContainShortcode($content) ) {
            return $content;
        }

        return $this->replaceShortcodePairs(
            $content,
            $this->findAllowedShortcodePairs($content),
            function ($pair) {
                $atts = shortcode_parse_atts($pair['atts']);
                $result = $this->callback(is_array($atts) ? $atts : array(), $pair['inner'], $this->public_name);

                return is_string($result) ? $result : $pair['opener'] . $pair['inner'] . '[/' . $this->public_name . ']';
            }
        );
    }

    /**
     * Modifies the content before the encoder processes it.
     *
     * @param string $content The content to modify.
     * @return string The modified content.
     * @psalm-suppress PossiblyUnusedMethod
     */
    protected function changeContentBeforeEncoderModify($content = '')
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
    protected function changeContentAfterEncoderModify($content = '')
    {
        return $content;
    }

    /**
     * Find pairs of the opening and the closing tags of this shortcode.
     * The opening tag is paired with the nearest closing tag after it.
     *
     * @param string $content
     *
     * @return array[] list of pairs: start and end offsets, opening tag, attributes, inner content
     */
    protected function findShortcodePairs($content)
    {
        $pairs = array();

        $attributes_pattern = $this->has_attributes ? '[^\[\]\/]*(?:\/(?!\])[^\[\]\/]*)*' : '';
        $pattern = '/\[(\/?)' . preg_quote($this->public_name, '/') . '(?![\w-])(' . $attributes_pattern . ')\]/';

        if ( ! preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) ) {
            return $pairs;
        }

        $opener = null;
        foreach ( $matches as $match ) {
            if ( $match[1][0] !== '/' ) {
                if ( $opener === null ) {
                    $opener = $match;
                }
                continue;
            }

            if ( $opener === null || $match[2][0] !== '' ) {
                continue;
            }

            $inner_start = $opener[0][1] + strlen($opener[0][0]);
            $pairs[] = array(
                'start'  => $opener[0][1],
                'end'    => $match[0][1] + strlen($match[0][0]),
                'opener' => $opener[0][0],
                'atts'   => $opener[2][0],
                'inner'  => (string)substr($content, $inner_start, $match[0][1] - $inner_start),
            );
            $opener = null;
        }

        return $pairs;
    }

    /**
     * Find the shortcode pairs which can be processed: the whole pair is placed in one text node,
     * it is not placed inside the opening tag of another shortcode and the inner content has no markup.
     *
     * @param string $content
     *
     * @return array[]
     */
    protected function findAllowedShortcodePairs($content)
    {
        $pairs = $this->findShortcodePairs($content);

        if ( empty($pairs) || ! ContactsEncoder::isHtmlApiSupported() ) {
            return array();
        }

        $helper = new ContactsEncoderHelper();
        $text_ranges = ContactsEncoderTagProcessor::getTextRanges($content);
        $other_shortcodes_pattern = $this->getOtherShortcodesPattern();

        $allowed_pairs = array();
        foreach ( $pairs as $pair ) {
            if (
                $this->shortcodeContentContainsHtmlTags($pair['inner']) ||
                ! $helper->isInsideRanges($text_ranges, $pair['start'], $pair['end']) ||
                $this->isInsideOtherShortcodeTag($content, $pair['start'], $other_shortcodes_pattern)
            ) {
                continue;
            }

            $allowed_pairs[] = $pair;

            // Take first N pairs
            if ( count($allowed_pairs) >= self::MAX_SHORTCODES_TO_PROCESS ) {
                break;
            }
        }

        return $allowed_pairs;
    }

    /**
     * Replace the shortcode pairs in the content with the result of the callback.
     *
     * @param string $content
     * @param array[] $pairs Pairs sorted by the offset
     * @param callable $callback
     *
     * @return string
     */
    protected function replaceShortcodePairs($content, $pairs, $callback)
    {
        if ( empty($pairs) ) {
            return $content;
        }

        $result = '';
        $cursor = 0;
        foreach ( $pairs as $pair ) {
            $result .= substr($content, $cursor, $pair['start'] - $cursor) . call_user_func($callback, $pair);
            $cursor = $pair['end'];
        }

        return $result . substr($content, $cursor);
    }

    /**
     * Restore the placeholders of this shortcode.
     * A placeholder found outside a text node is removed.
     *
     * @param string $content
     * @param array $replacements Placeholder => replacement
     *
     * @return string
     */
    protected function restorePlaceholdersInTextNodes($content, $replacements)
    {
        if ( ! is_string($content) || $content === '' || empty($replacements) ) {
            return $content;
        }

        $pattern = '/' . str_replace('EE_0', 'EE_\d+_[0-9a-z]+', (string)$this->exclusion_wrapper) . '/i';
        if ( ! preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE) || ! isset($matches[0]) ) {
            return $content;
        }

        $helper = new ContactsEncoderHelper();
        $text_ranges = ContactsEncoder::isHtmlApiSupported() ? ContactsEncoderTagProcessor::getTextRanges($content) : array();
        $other_shortcodes_pattern = $this->getOtherShortcodesPattern();

        $result = '';
        $cursor = 0;
        foreach ( $matches[0] as $match ) {
            // Skip placeholders of other passes
            if ( ! isset($replacements[$match[0]]) ) {
                continue;
            }

            $start = $match[1];
            $end = $start + strlen($match[0]);
            $is_in_text = $helper->isInsideRanges($text_ranges, $start, $end) &&
                ! $this->isInsideOtherShortcodeTag($content, $start, $other_shortcodes_pattern);

            $result .= substr($content, $cursor, $start - $cursor) . ($is_in_text ? $replacements[$match[0]] : '');
            $cursor = $end;
        }

        return $result . substr($content, $cursor);
    }

    /**
     * Check if the offset is placed inside the opening tag of another shortcode, like [caption class="..."].
     *
     * @param string $content
     * @param int $offset
     * @param string $pattern Pattern of the other shortcodes names
     *
     * @return bool
     */
    protected function isInsideOtherShortcodeTag($content, $offset, $pattern)
    {
        if ( $pattern === '' || $offset <= 0 ) {
            return false;
        }

        // The opening tag of a shortcode ends with the first "]"
        $previous_tag_end = strrpos($content, ']', $offset - strlen($content) - 1);
        $start = $previous_tag_end === false ? 0 : $previous_tag_end + 1;

        return (bool)preg_match($pattern, substr($content, $start, $offset - $start));
    }

    /**
     * Pattern to find the opening tags of the other registered shortcodes.
     *
     * @return string Empty string if there are no other shortcodes
     */
    protected function getOtherShortcodesPattern()
    {
        global $shortcode_tags;

        if ( empty($shortcode_tags) || ! is_array($shortcode_tags) ) {
            return '';
        }

        $names = array();
        foreach ( array_keys($shortcode_tags) as $name ) {
            if ( is_string($name) && $name !== '' && $name !== $this->public_name ) {
                $names[] = preg_quote($name, '/');
            }
        }

        return empty($names) ? '' : '/\[(?:' . implode('|', $names) . ')(?![\w-])/';
    }

    /**
     * Skip shortcode pairs whose inner content contains markup symbols.
     *
     * In buffer mode the lazy [tag]...[/tag] match can pair an opener in one
     * fragment with a closer in another and swallow everything between them
     * (comments, articles, any markup). A legitimate shortcode wraps text only.
     *
     * @param string $content Raw inner shortcode content.
     *
     * @return bool
     */
    protected function shortcodeContentContainsHtmlTags($content)
    {
        if ( ! is_string($content) || $content === '' ) {
            return false;
        }

        return strpbrk($content, '"<>') !== false;
    }
}
