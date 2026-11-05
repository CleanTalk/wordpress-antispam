<?php

namespace Cleantalk\Common\ContactsEncoder\Helper;

/**
 * Exclusions to use on content during modification chunks.
 */
class ContactsEncoderHelper
{
    /**
     * Attribute names to skip content encoding contains them. Keep arrays of tag=>[attributes].
     * @var array[]
     */
    private $attribute_exclusions_signs = array(
        'input' => array('placeholder', 'value', 'data-mask'),
        'option' => array('value'),
        'sc-customer-email' => array('placeholder', 'value'),
        'img' => array('alt', 'title'),
        'div' => array('data-et-multi-view'),
    );

    /**
     * Runtime map of tag => attribute names. Null means use the built-in defaults.
     * @var array[]|null
     */
    private $runtime_attribute_exclusions_signs;

    /**
     * Flat list of HTML attribute names to skip encoding in, regardless of tag.
     * @var string[]
     */
    private $attribute_exclusions_list = array();

    /**
     * Tag names whose inner text is not markup and must never be rewritten.
     * @var string[]
     */
    private $raw_text_tags = array('script', 'style', 'template', 'noscript');

    /**
     * Content the offset indexes below were built for.
     * @var string|null
     */
    private $indexed_content;

    /**
     * Sorted list of [start, end) offsets of every raw text block in the indexed content.
     * @var array[]
     */
    private $raw_text_ranges = array();

    /**
     * Sorted list of [start, end, tag, attribute] of every attribute value in the indexed content.
     * @var array<int, array{0: int, 1: int, 2: string, 3: string}>
     */
    private $attribute_value_ranges = array();

    /**
     * Sorted list of [start, end) offsets of the text of every option element in the indexed content.
     * @var array[]
     */
    private $option_text_ranges = array();

    /**
     * Sorted list of [start, end, attribute names] of the tags with attributes in the indexed content.
     * @var array<int, array{0: int, 1: int, 2: array<int, string>}>
     */
    private $tags_with_attributes = array();

    /**
     * Elements whose inner text is not markup for a browser, the tokenizer jumps to their end tag.
     * @var string[]
     */
    private static $unparsed_text_tags = array('script', 'style', 'textarea', 'title', 'xmp', 'iframe', 'noembed', 'noframes');

    /**
     * One linear pass over the content, follows the way a browser splits it into tags.
     * No PCRE here: the result must not depend on JIT stack or backtrack limits.
     *
     * Every token is an array:
     *  - start, end: offsets of the whole token, the end is exclusive;
     *  - name: lowercase tag name, `!--` for a comment, `!` for a declaration or a processing instruction;
     *  - closing: true for an end tag;
     *  - attributes: list of [name, name_start, value_start, value_end, end]. A value without a quote or
     *    without a value at all has the same bounds as its `end`.
     * A tag or a comment that is not closed takes the rest of the content, as it does in a browser.
     *
     * @param string $content
     *
     * @return array<int, array{start: int, end: int, name: string, closing: bool, attributes: array<int, array{0: string, 1: int, 2: int, 3: int, 4: int}>}>
     */
    public static function scanMarkup($content)
    {
        $tokens = array();

        if ( ! is_string($content) || $content === '' ) {
            return $tokens;
        }

        $length = strlen($content);
        $offset = 0;

        while ( $offset < $length ) {
            $start = strpos($content, '<', $offset);
            if ( $start === false ) {
                break;
            }

            $next = $start + 1 < $length ? $content[$start + 1] : '';

            if ( $next === '!' && substr($content, $start, 4) === '<!--' ) {
                $end = self::findCommentEnd($content, $start, $length);
                $tokens[] = array('start' => $start, 'end' => $end, 'name' => '!--', 'closing' => false, 'attributes' => array());
                $offset = $end;
                continue;
            }

            if ( $next === '!' || $next === '?' ) {
                $close = strpos($content, '>', $start);
                $end = $close === false ? $length : $close + 1;
                $tokens[] = array('start' => $start, 'end' => $end, 'name' => '!', 'closing' => false, 'attributes' => array());
                $offset = $end;
                continue;
            }

            $closing = $next === '/';
            $name_start = $closing ? $start + 2 : $start + 1;
            if ( $name_start >= $length || ! ctype_alpha($content[$name_start]) ) {
                // Just a "less than" sign in the text.
                $offset = $start + 1;
                continue;
            }

            $token = self::readTag($content, $start, $name_start, $closing, $length);
            $tokens[] = $token;
            $offset = $token['end'];

            if ( ! $closing && in_array($token['name'], self::$unparsed_text_tags, true) ) {
                // `</scriptx` does not end a script for a browser, the name must be followed by a delimiter.
                $found = preg_match('~</' . $token['name'] . '(?=[ \t\r\n\f/>])~i', $content, $close, PREG_OFFSET_CAPTURE, $offset);
                $offset = $found && isset($close[0][1]) ? $close[0][1] : $length;
            }
        }

        return $tokens;
    }

    /**
     * @param string $content
     * @param int $start Offset of `<!--`.
     * @param int $length
     *
     * @return int Exclusive end of the comment.
     */
    private static function findCommentEnd($content, $start, $length)
    {
        // `<!-->` and `<!--->` are complete comments for a browser.
        $search_from = $start + 2;

        while ( true ) {
            $dashes = strpos($content, '--', $search_from);
            if ( $dashes === false ) {
                return $length;
            }

            $tail = substr($content, $dashes + 2, 2);
            if ( $tail !== '' && $tail[0] === '>' ) {
                return $dashes + 3;
            }
            if ( $tail === '!>' ) {
                return $dashes + 4;
            }

            $search_from = $dashes + 1;
        }
    }

    /**
     * @param string $content
     * @param int $start Offset of `<`.
     * @param int $name_start
     * @param bool $closing
     * @param int $length
     *
     * @return array{start: int, end: int, name: string, closing: bool, attributes: array<int, array{0: string, 1: int, 2: int, 3: int, 4: int}>} Token, see scanMarkup().
     */
    private static function readTag($content, $start, $name_start, $closing, $length)
    {
        $space = " \t\r\n\f";
        $name_length = strcspn($content, $space . '/>', $name_start);
        $position = $name_start + $name_length;
        $token = array(
            'start' => $start,
            'end' => $length,
            'name' => strtolower(substr($content, $name_start, $name_length)),
            'closing' => $closing,
            'attributes' => array(),
        );

        while ( $position < $length ) {
            $position += strspn($content, $space . '/', $position);
            if ( $position >= $length ) {
                break;
            }
            if ( $content[$position] === '>' ) {
                $token['end'] = $position + 1;
                break;
            }

            // A leading `=` belongs to the attribute name.
            $attribute_start = $position;
            $position += 1 + strcspn($content, $space . '/>=', $position + 1);
            $attribute_name = strtolower(substr($content, $attribute_start, $position - $attribute_start));

            $after_name = $position + strspn($content, $space, $position);
            if ( $after_name >= $length || $content[$after_name] !== '=' ) {
                $token['attributes'][] = array($attribute_name, $attribute_start, $position, $position, $position);
                continue;
            }

            $value = $after_name + 1 + strspn($content, $space, $after_name + 1);
            if ( $value >= $length ) {
                break;
            }

            $quote = $content[$value];
            if ( $quote === '"' || $quote === "'" ) {
                $close = strpos($content, $quote, $value + 1);
                if ( $close === false ) {
                    $token['attributes'][] = array($attribute_name, $attribute_start, $value + 1, $length, $length);
                    break;
                }
                $token['attributes'][] = array($attribute_name, $attribute_start, $value + 1, $close, $close + 1);
                $position = $close + 1;
                continue;
            }

            $value_end = $value + strcspn($content, $space . '>', $value);
            $token['attributes'][] = array($attribute_name, $attribute_start, $value, $value_end, $value_end);
            $position = $value_end;
        }

        return $token;
    }

    /**
     * @param array<int, array{start: int, end: int, name: string, closing: bool, attributes: array<int, array{0: string, 1: int, 2: int, 3: int, 4: int}>}> $tokens Sorted result of scanMarkup().
     * @param int $position
     *
     * @return int|false Index of the token containing the offset.
     *
     * @psalm-suppress PossiblyUnusedMethod Public API for host apps
     */
    public static function findTokenIndex($tokens, $position)
    {
        $low = 0;
        $high = count($tokens) - 1;

        while ( $low <= $high ) {
            $middle = intdiv($low + $high, 2);

            if ( $position < $tokens[$middle]['start'] ) {
                $high = $middle - 1;
            } elseif ( $position >= $tokens[$middle]['end'] ) {
                $low = $middle + 1;
            } else {
                return $middle;
            }
        }

        return false;
    }

    /**
     * Checks whether the tag enclosing the given offset already declares the attribute.
     *
     * Scheme links are rewritten from the inside of the href value, so the encoder appends its own
     * attributes to the opening tag. This lookup prevents emitting a duplicate of an attribute the
     * author has already set, e.g. `title` on `<a title="Write to us" href="mailto:...">`.
     *
     * @param string $content Whole content being processed.
     * @param int $position Offset of the match inside $content.
     * @param string $attribute Attribute name to look for.
     *
     * @return bool
     */
    public function enclosingTagHasAttribute($content, $position, $attribute)
    {
        if ( ! is_string($content) || ! is_int($position) || $position < 0 ) {
            return false;
        }

        $this->indexMarkup($content);

        $index = $this->findRangeIndex($this->tags_with_attributes, $position);

        return $index !== false && in_array(strtolower($attribute), $this->tags_with_attributes[$index][2], true);
    }

    /**
     * Checking if the string contains mailto: link
     *
     * @param string $string
     *
     * @return bool
     */
    public function isMailto($string)
    {
        return stripos($string, 'mailto:') !== false;
    }

    /**
     * Checking if the string contains tel: link
     *
     * @param string $string
     *
     * @return bool
     */
    public function isTelTag($string)
    {
        return stripos($string, 'tel:') !== false;
    }

    /**
     * Checking if the string contains mailto: link
     *
     * @param string $email
     * @param string $content
     * @param int|false|null $position Known match offset; null looks up the first occurrence
     *
     * @return bool
     */
    public function isMailtoAdditionalCopy($email, $content, $position = null)
    {
        $position = $this->resolveMatchPosition($email, $content, $position);

        if ($position === false) {
            return false;
        }

        // `bcc=` ends with `cc=`.
        return $position >= 3 && substr($content, $position - 3, 3) === 'cc=';
    }

    /**
     * Check if the given email is inside an option element text (not attributes).
     *
     * @param string $email
     * @param string $content
     * @param int|false|null $position Known match offset; null looks up the first occurrence
     *
     * @return bool
     */
    public function isInsideOptionTag($email, $content, $position = null)
    {
        $pos = $this->resolveMatchPosition($email, $content, $position);
        if ($pos === false) {
            return false;
        }

        $this->indexMarkup($content);

        return $this->findRangeIndex($this->option_text_ranges, $pos) !== false;
    }

    /**
     * Check if the given email is inside a script tag
     * @param string $email The email to check
     * @param string $content The full content
     * @param int|false|null $position Known match offset; null looks up the first occurrence
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function isInsideScriptTag($email, $content, $position = null)
    {
        return $this->isInsideRawTextTag($email, $content, $position, array('script'));
    }

    /**
     * Check whether the match sits inside the raw text of a tag whose content is not markup
     * (script, style, template, noscript). Covers inline scripts, JSON-LD and CSS at once.
     *
     * @param string $needle The matched contact
     * @param string $content The full content
     * @param int|false|null $position Known match offset; null looks up the first occurrence
     * @param string[]|null $tags Restrict the check to these tags; null uses the full raw text list
     * @return bool
     */
    public function isInsideRawTextTag($needle, $content, $position = null, $tags = null)
    {
        $pos = $this->resolveMatchPosition($needle, $content, $position);
        if ( $pos === false ) {
            return false;
        }

        $this->indexMarkup($content);

        $index = $this->findRangeIndex($this->raw_text_ranges, $pos);
        if ( $index === false || ! isset($this->raw_text_ranges[$index][2]) ) {
            return false;
        }

        $range_tag = $this->raw_text_ranges[$index][2];

        return $tags === null || in_array($range_tag, $tags, true);
    }

    /**
     * Build the raw text offset index for the given content once.
     * Repeated calls with the same content reuse the cached index.
     *
     * @param string $content
     * @return void
     */
    public function indexMarkup($content)
    {
        if ( ! is_string($content) ) {
            return;
        }

        if ( $this->indexed_content !== null && $this->indexed_content === $content ) {
            return;
        }

        $this->indexed_content = $content;
        // The tokens are big on a big page, only the ranges stay in the memory.
        $tokens = self::scanMarkup($content);
        $this->raw_text_ranges = $this->buildElementTextRanges($tokens, $this->raw_text_tags);
        $this->option_text_ranges = $this->buildElementTextRanges($tokens, array('option'));
        $this->buildAttributeRanges($tokens);
    }

    /**
     * Check whether the match sits inside an HTML attribute value.
     *
     * Encoding wraps the match into a <span>, which is only valid in a text node. Inside an
     * attribute value the markup would be consumed by the attribute itself and the quotes of the
     * injected span would terminate the value early, swallowing the rest of the tag and whatever
     * follows it until the next quote in the document.
     *
     * @param string $needle The matched contact
     * @param string $content The full content
     * @param int|false|null $position Known match offset; null looks up the first occurrence
     * @return bool
     */
    public function isInsideAttributeValue($needle, $content, $position = null)
    {
        $pos = $this->resolveMatchPosition($needle, $content, $position);
        if ( $pos === false ) {
            return false;
        }

        $this->indexMarkup($content);

        return $this->findRangeIndex($this->attribute_value_ranges, $pos) !== false;
    }

    /**
     * Offsets of every attribute value and of every tag with attributes.
     *
     * @param array<int, array{start: int, end: int, name: string, closing: bool, attributes: array<int, array{0: string, 1: int, 2: int, 3: int, 4: int}>}> $tokens
     * @return void
     */
    private function buildAttributeRanges($tokens)
    {
        $this->attribute_value_ranges = array();
        $this->tags_with_attributes = array();

        foreach ( $tokens as $token ) {
            if ( empty($token['attributes']) ) {
                continue;
            }

            $names = array();
            foreach ( $token['attributes'] as $attribute ) {
                $names[] = $attribute[0];
                if ( $attribute[3] > $attribute[2] ) {
                    $this->attribute_value_ranges[] = array($attribute[2], $attribute[3], $token['name'], $attribute[0]);
                }
            }
            $this->tags_with_attributes[] = array($token['start'], $token['end'], $names);
        }
    }

    /**
     * Offsets of the inner text of the given elements.
     *
     * @param array<int, array{start: int, end: int, name: string, closing: bool, attributes: array<int, array{0: string, 1: int, 2: int, 3: int, 4: int}>}> $tokens
     * @param string[] $tags
     * @return array[] list of [start, end, tag]
     */
    private function buildElementTextRanges($tokens, $tags)
    {
        $ranges = array();
        $open = null;

        foreach ( $tokens as $token ) {
            if ( $open === null ) {
                if ( ! $token['closing'] && in_array($token['name'], $tags, true) ) {
                    $open = $token;
                }
                continue;
            }

            if ( $token['closing'] && $token['name'] === $open['name'] ) {
                $ranges[] = array($open['end'], $token['start'], $token['name']);
                $open = null;
            }
        }

        return $ranges;
    }

    /**
     * Index of the range containing the offset, or false when the offset is outside all of them.
     *
     * @param array[] $ranges
     * @param int $position
     * @return int|false
     */
    private function findRangeIndex($ranges, $position)
    {
        if ( ! is_array($ranges) || empty($ranges) ) {
            return false;
        }

        $low = 0;
        $high = count($ranges) - 1;

        while ( $low <= $high ) {
            $middle = intdiv($low + $high, 2);
            if ( ! isset($ranges[$middle][0], $ranges[$middle][1]) ) {
                return false;
            }

            if ( $position < $ranges[$middle][0] ) {
                $high = $middle - 1;
            } elseif ( $position >= $ranges[$middle][1] ) {
                $low = $middle + 1;
            } else {
                return $middle;
            }
        }

        return false;
    }

    /**
     * Built-in tag => attribute map. Returned as a copy so callers can mutate it safely.
     *
     * @return array[]
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getDefaultAttributeExclusionsSigns()
    {
        $copy = array();
        foreach ( $this->attribute_exclusions_signs as $tag => $attributes ) {
            $copy[$tag] = is_array($attributes) ? array_values($attributes) : $attributes;
        }

        return $copy;
    }

    /**
     * Replace the working tag => attribute map (e.g. after a host-app filter).
     *
     * @param array $map
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setAttributeExclusionsMap(array $map)
    {
        $this->runtime_attribute_exclusions_signs = $map;
    }

    /**
     * Merge extra attribute names for a tag into the working map.
     *
     * @param string $tag
     * @param string[] $attributes
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function addAttributeExclusions($tag, array $attributes)
    {
        if ( ! is_string($tag) || $tag === '' ) {
            return;
        }

        $map = $this->getWorkingAttributeExclusionsSigns();
        if ( ! isset($map[$tag]) || ! is_array($map[$tag]) ) {
            $map[$tag] = array();
        }

        foreach ( $attributes as $attribute ) {
            if ( is_string($attribute) && $attribute !== '' && ! in_array($attribute, $map[$tag], true) ) {
                $map[$tag][] = $attribute;
            }
        }

        $this->runtime_attribute_exclusions_signs = $map;
    }

    /**
     * Replace the flat list of attribute names skipped on any tag.
     *
     * @param array $names
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setAttributeNames(array $names)
    {
        $this->attribute_exclusions_list = $this->sanitizeAttributeNames($names);
    }

    /**
     * Append attribute names skipped on any tag.
     *
     * @param string[] $names
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function addAttributeNames(array $names)
    {
        foreach ( $this->sanitizeAttributeNames($names) as $attribute ) {
            if ( ! in_array($attribute, $this->attribute_exclusions_list, true) ) {
                $this->attribute_exclusions_list[] = $attribute;
            }
        }
    }

    /**
     * Check if email is placed in the tag that has attributes of exclusions.
     *
     * @param string $email_match - email
     * @param string $temp_content - email
     * @param int|false|null $position Known match offset; null accepts any occurrence
     * @return bool
     */
    public function hasAttributeExclusions($email_match, $temp_content, $position = null)
    {
        if ( ! is_string($email_match) || $email_match === '' || ! is_string($temp_content) ) {
            return false;
        }

        if ( $position !== null ) {
            $position = $this->resolveMatchPosition($email_match, $temp_content, $position);
            if ( $position === false ) {
                return false;
            }

            return $this->isPositionInExcludedAttribute($temp_content, $position);
        }

        $quoted_match = preg_quote($email_match, '/');
        $attribute_signs = $this->getWorkingAttributeExclusionsSigns();

        foreach ( $attribute_signs as $tag => $array_of_attributes ) {
            if ( ! is_string($tag) || $tag === '' || ! is_array($array_of_attributes) ) {
                continue;
            }
            foreach ( $array_of_attributes as $attribute ) {
                if ( ! is_string($attribute) || $attribute === '' ) {
                    continue;
                }
                if ( $this->isMatchInsideAttribute($quoted_match, $attribute, $temp_content, $tag, $position) ) {
                    return true;
                }
            }
        }

        foreach ( $this->attribute_exclusions_list as $attribute ) {
            if ( $this->isMatchInsideAttribute($quoted_match, $attribute, $temp_content, null, $position) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $content
     * @param int $position
     * @return bool
     */
    private function isPositionInExcludedAttribute($content, $position)
    {
        $this->indexMarkup($content);

        $index = $this->findRangeIndex($this->attribute_value_ranges, $position);
        if ( $index === false ) {
            return false;
        }

        $tag = $this->attribute_value_ranges[$index][2];
        $attribute = $this->attribute_value_ranges[$index][3];

        if ( in_array($attribute, array_map('strtolower', $this->attribute_exclusions_list), true) ) {
            return true;
        }

        foreach ( $this->getWorkingAttributeExclusionsSigns() as $sign_tag => $attributes ) {
            if (
                is_string($sign_tag) && is_array($attributes)
                && strtolower($sign_tag) === $tag
                && in_array($attribute, array_map('strtolower', array_filter($attributes, 'is_string')), true)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    private function getWorkingAttributeExclusionsSigns()
    {
        return is_array($this->runtime_attribute_exclusions_signs)
            ? $this->runtime_attribute_exclusions_signs
            : $this->attribute_exclusions_signs;
    }

    /**
     * @param array $names
     * @return string[]
     */
    private function sanitizeAttributeNames(array $names)
    {
        $result = array();
        foreach ( $names as $attribute ) {
            if ( is_string($attribute) && $attribute !== '' ) {
                $result[] = $attribute;
            }
        }

        return $result;
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @param int|false|null $position
     * @return int|false
     */
    private function resolveMatchPosition($needle, $haystack, $position)
    {
        if ( $position === null ) {
            return strpos($haystack, $needle);
        }

        if ( $position === false || ! is_int($position) || $position < 0 || ! is_string($needle) || $needle === '' ) {
            return false;
        }

        $length = strlen($needle);
        if ( $position > strlen($haystack) - $length ) {
            return false;
        }

        if ( substr($haystack, $position, $length) !== $needle ) {
            return false;
        }

        return $position;
    }

    /**
     * @param string $quoted_match
     * @param string $attribute
     * @param string $content
     * @param string|null $tag
     * @param int|null $position
     * @return bool
     */
    private function isMatchInsideAttribute($quoted_match, $attribute, $content, $tag = null, $position = null)
    {
        $quoted_attribute = preg_quote($attribute, '/');
        // Always require an HTML tag so plain text like attr="..." is not treated as markup.
        $tag_prefix = $tag === null
            ? '<[a-zA-Z][\w:-]*\s+[^>]*'
            : '<' . preg_quote($tag, '/') . '\s+[^>]*';

        $pattern = '/'
            . $tag_prefix
            . '\b'
            . $quoted_attribute
            . '\s*=\s*(["\'])[^"\']*'
            . $quoted_match
            . '[^"\']*\1/';

        if ( $position === null ) {
            return (bool) preg_match($pattern, $content);
        }

        if ( ! preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE) || ! isset($matches[0]) ) {
            return false;
        }

        foreach ( $matches[0] as $match ) {
            if ( ! isset($match[0], $match[1]) ) {
                continue;
            }
            $start = $match[1];
            $end = $start + strlen($match[0]);
            if ( $position >= $start && $position < $end ) {
                return true;
            }
        }

        return false;
    }
}
