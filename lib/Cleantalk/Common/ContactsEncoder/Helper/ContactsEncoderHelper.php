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
     * Checking if the string contains mailto: link
     *
     * @param string $string
     *
     * @return bool
     */
    public function isMailto($string)
    {
        return strpos($string, 'mailto:') !== false;
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
        return strpos($string, 'tel:') !== false;
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

        $cc_position = strrpos(substr($content, 0, $position), 'cc=');
        if ( $cc_position !== false && $cc_position + 3 == $position ) {
            return true;
        }

        $bcc_position = strrpos(substr($content, 0, $position), 'bcc=');
        if ( $bcc_position !== false && $bcc_position + 4 == $position ) {
            return true;
        }

        return false;
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

        $last_option_start = strrpos(substr($content, 0, $pos), '<option');
        if ($last_option_start === false) {
            return false;
        }

        $option_tag_end = strpos($content, '>', $last_option_start);
        if ($option_tag_end === false || $pos <= $option_tag_end) {
            return false;
        }

        $option_close = stripos($content, '</option>', $last_option_start);
        if ($option_close === false) {
            return false;
        }

        return $pos > $option_tag_end && $pos < $option_close;
    }

    /**
     * Check if the given email is inside a script tag
     * @param string $email The email to check
     * @param string $content The full content
     * @param int|false|null $position Known match offset; null looks up the first occurrence
     * @return bool
     */
    public function isInsideScriptTag($email, $content, $position = null)
    {
        $pos = $this->resolveMatchPosition($email, $content, $position);
        if ($pos === false) {
            return false;
        }

        // Find the last script opening tag before the email
        $last_script_start = strrpos(substr($content, 0, $pos), '<script');
        if ($last_script_start === false) {
            return false;
        }

        // Find the first script closing tag after the last opening tag
        $script_end = strpos($content, '</script>', $last_script_start);
        if ($script_end === false) {
            return false;
        }

        // The email is inside a script tag if its position is between the opening and closing tags
        return ($pos > $last_script_start && $pos < $script_end);
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
