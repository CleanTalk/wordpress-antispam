<?php

namespace Cleantalk\ApbctWP\ContactsEncoder;

/**
 * Reads the markup of the content with the WordPress HTML API.
 * Needs WordPress 6.5+, check ContactsEncoder::isHtmlApiSupported() before use.
 */
class ContactsEncoderTagProcessor extends \WP_HTML_Tag_Processor
{
    /**
     * Elements whose inner text must not be modified.
     * @var string[]
     */
    private static $skip_text_inside = array('NOSCRIPT', 'TEMPLATE', 'SELECT', 'DATALIST', 'SVG', 'MATH');

    /**
     * Get offsets of the text nodes of the HTML.
     * Text of comments, raw text elements and the elements from $skip_text_inside is not included.
     *
     * @param string $html
     *
     * @return array[] list of [start, end]
     */
    public static function getTextRanges($html)
    {
        $ranges = array();

        if ( ! is_string($html) || $html === '' ) {
            return $ranges;
        }

        $processor = new self($html);
        $skip_depth = 0;
        $last_start = -1;

        while ( $processor->next_token() ) {
            $span = $processor->getTokenSpan();

            // Stop if the parser does not move forward
            if ( $span === false || $span[0] <= $last_start ) {
                break;
            }
            $last_start = $span[0];

            $token_type = $processor->get_token_type();

            if ( $token_type === '#tag' ) {
                $skip_depth = $processor->getSkipDepth($skip_depth);
            } elseif ( $token_type === '#text' && $skip_depth === 0 ) {
                $ranges[] = $span;
            }
        }

        return $ranges;
    }

    /**
     * Get offsets of the inner HTML of the elements with the given tag names.
     * $callback is called on each opening tag, it must return false to skip the element
     * or any other value to keep it. The value is returned with the offsets.
     *
     * @param string $html
     * @param string[] $tag_names Upper case tag names
     * @param callable $callback
     *
     * @return array[] list of [inner_start, inner_end, callback result]
     */
    public static function getElementInnerRanges($html, $tag_names, $callback)
    {
        $ranges = array();

        if ( ! is_string($html) || $html === '' ) {
            return $ranges;
        }

        $processor = new self($html);
        $skip_depth = 0;
        $last_start = -1;
        $opened = null;

        while ( $processor->next_token() ) {
            $span = $processor->getTokenSpan();

            // Stop if the parser does not move forward
            if ( $span === false || $span[0] <= $last_start ) {
                break;
            }
            $last_start = $span[0];

            if ( $processor->get_token_type() !== '#tag' ) {
                continue;
            }

            $new_skip_depth = $processor->getSkipDepth($skip_depth);
            if ( $new_skip_depth !== $skip_depth ) {
                $skip_depth = $new_skip_depth;
                $opened = null;
                continue;
            }

            if ( $skip_depth > 0 || ! in_array($processor->get_tag(), $tag_names, true) ) {
                continue;
            }

            if ( ! $processor->is_tag_closer() ) {
                // Skip nested elements of the same name
                $data = $opened === null ? call_user_func($callback, $processor) : false;
                $opened = $data === false ? null : array($processor->get_tag(), $span[1], $data);
                continue;
            }

            if ( $opened !== null && $opened[0] === $processor->get_tag() ) {
                $ranges[] = array($opened[1], $span[0], $opened[2]);
            }
            $opened = null;
        }

        return $ranges;
    }

    /**
     * Count the opened elements whose inner text must not be modified.
     *
     * @param int $skip_depth
     *
     * @return int
     */
    private function getSkipDepth($skip_depth)
    {
        $tag_name = $this->get_tag();

        if ( ! in_array($tag_name, self::$skip_text_inside, true) ) {
            return $skip_depth;
        }

        if ( $this->is_tag_closer() ) {
            return max(0, $skip_depth - 1);
        }

        // Only foreign elements are closed by the self-closing flag
        if ( $this->has_self_closing_flag() && ($tag_name === 'SVG' || $tag_name === 'MATH') ) {
            return $skip_depth;
        }

        return $skip_depth + 1;
    }

    /**
     * Offsets of the current token.
     *
     * @return array|false [start, end]
     */
    private function getTokenSpan()
    {
        if ( ! $this->set_bookmark('apbct_token') || ! isset($this->bookmarks['apbct_token']) ) {
            return false;
        }

        $span = $this->bookmarks['apbct_token'];

        return array((int)$span->start, (int)$span->start + (int)$span->length);
    }
}
