<?php

namespace Cleantalk\ApbctWP\ContactsEncoder\Integrations;

/**
 * Skip Contacts Encoder shortcode handling inside rendered comment lists in buffer mode.
 */
class CEIntegrationCommentList
{
    /**
     * @var array<string, string>
     */
    private $region_placeholders = array();

    /**
     * @param string $content
     *
     * @return string
     */
    public function protect($content)
    {
        if ( ! is_string($content) || $content === '' ) {
            return $content;
        }

        if (
            stripos($content, 'comment-list') === false
            && stripos($content, 'id="comments"') === false
            && stripos($content, "id='comments'") === false
        ) {
            return $content;
        }

        $this->region_placeholders = array();

        $content = $this->protectElementsById($content, 'comments');
        $content = $this->protectElementsByClass($content, 'comment-list', 'ol');
        $content = $this->protectElementsByClass($content, 'comment-list', 'ul');

        return $content;
    }

    /**
     * @param string $content
     *
     * @return string
     */
    public function restore($content)
    {
        if ( ! is_string($content) || $this->region_placeholders === array() ) {
            return $content;
        }

        $content = strtr($content, $this->region_placeholders);
        $this->region_placeholders = array();

        return $content;
    }

    /**
     * @param string $content
     * @param string $id
     *
     * @return string
     */
    private function protectElementsById($content, $id)
    {
        $pattern = '/<div\b(?=[^>]*\bid=(["\'])' . preg_quote($id, '/') . '\1)/i';

        return $this->protectByOpeningTagPattern($content, $pattern, 'div');
    }

    /**
     * @param string $content
     * @param string $class
     * @param string $tag_name
     *
     * @return string
     */
    private function protectElementsByClass($content, $class, $tag_name)
    {
        $pattern = '/<' . $tag_name . '\b(?=[^>]*\bclass=(["\'])[^"\']*\b'
            . preg_quote($class, '/')
            . '\b[^"\']*\1)/i';

        return $this->protectByOpeningTagPattern($content, $pattern, $tag_name);
    }

    /**
     * @param string $content
     * @param string $open_pattern
     * @param string $tag_name
     *
     * @return string
     */
    private function protectByOpeningTagPattern($content, $open_pattern, $tag_name)
    {
        $offset = 0;

        while ( preg_match($open_pattern, $content, $matches, PREG_OFFSET_CAPTURE, $offset) ) {
            $start = $matches[0][1] ?? null;

            if ( $start === null ) {
                break;
            }

            $element = $this->extractBalancedElement($content, $start, $tag_name);

            if ( $element === null ) {
                $offset = $start + 1;
                continue;
            }

            $placeholder = '%%APBCT_COMMENT_REGION_' . count($this->region_placeholders) . '%%';
            $this->region_placeholders[$placeholder] = $element;

            $content = substr($content, 0, $start) . $placeholder . substr($content, $start + strlen($element));
            $offset = $start + strlen($placeholder);
        }

        return $content;
    }

    /**
     * @param string $content
     * @param int    $start
     * @param string $tag_name
     *
     * @return string|null
     */
    private function extractBalancedElement($content, $start, $tag_name)
    {
        $open_end = strpos($content, '>', $start);

        if ( $open_end === false ) {
            return null;
        }

        $depth = 1;
        $pos = $open_end + 1;
        $length = strlen($content);
        $open_needle = '<' . $tag_name;
        $close_needle = '</' . $tag_name;

        while ( $pos < $length && $depth > 0 ) {
            $next_open = stripos($content, $open_needle, $pos);
            $next_close = stripos($content, $close_needle, $pos);

            if ( $next_close === false ) {
                return null;
            }

            if ( $next_open !== false && $next_open < $next_close && $this->isTagOpenAt($content, $next_open, $tag_name) ) {
                $depth++;
                $pos = $next_open + 1;
                continue;
            }

            $depth--;
            $close_end = strpos($content, '>', $next_close);

            if ( $close_end === false ) {
                return null;
            }

            $pos = $close_end + 1;

            if ( $depth === 0 ) {
                return substr($content, $start, $pos - $start);
            }
        }

        return null;
    }

    /**
     * @param string $content
     * @param int    $offset
     * @param string $tag_name
     *
     * @return bool
     */
    private function isTagOpenAt($content, $offset, $tag_name)
    {
        return (bool) preg_match('/^<' . preg_quote($tag_name, '/') . '\b/i', substr($content, $offset, strlen($tag_name) + 2));
    }
}
