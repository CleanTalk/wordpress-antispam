<?php

namespace Cleantalk\Antispam\EmailEncoder;

/**
 * Exclusions to use on content during modification chunks.
 */
class EmailEncoderHelper
{
    /**
     * Attribute names to skip content encoding contains them. Keep arrays of tag=>[attributes].
     * @var array[]
     */
    private $attribute_exclusions_signs = array(
        'input' => array('placeholder', 'value'),
        'sc-customer-email' => array('placeholder', 'value'),
        'img' => array('alt', 'title'),
        'div' => array('data-et-multi-view'),
    );

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
     * @param array $match
     * @param string $content
     *
     * @return bool
     */
    public function isMailtoAdditionalCopy($match, $content)
    {
        $position = isset($match[1]) ? (int)$match[1] : null;

        if (null === $position) {
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
     * Checking if email in link
     *
     * @param array $matches
     * @param string $content
     *
     * @return bool
     */
    public function isEmailInLink($matches, $content)
    {
        $email = isset($matches[0]) && is_string($matches[0]) ? $matches[0] : null;
        $position = isset($matches[1]) ? (int)$matches[1] : null;

        if (null === $position || null === $email) {
            return false;
        }

        $href_position = strrpos(substr($content, 0, $position), 'href=');

        if ( $href_position !== false && $href_position + 6 == $position ) {
            return true;
        }

        return strpos($email, 'mailto:') !== false;
    }

    /**
     * Check if the given email is inside a script tag
     * @param string $email The email to check
     * @param string $content The full content
     * @return bool
     */
    public function isInsideScriptTag($email, $content)
    {
        // Find position of the email in content
        $pos = strpos($content, $email);
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
     * Check if email is placed in the tag that has attributes of exclusions.
     * @param string $email_match - email
     * @param string $temp_content - email
     * @return bool
     */
    public function hasAttributeExclusions($email_match, $temp_content)
    {
        $email_match = preg_quote($email_match);
        foreach ( $this->attribute_exclusions_signs as $tag => $array_of_attributes ) {
            foreach ( $array_of_attributes as $attribute ) {
                //do not remove IDE highlighted unnecessary escape!
                $pattern = '/<'
                           . $tag
                           . '+\s+[^>]*\b'
                           . $attribute
                           . '=((\\\')|")?[^"]*\b'
                           . $email_match
                           . '\b[^"]*((\\\')|")?"[^>]*>/';
                preg_match($pattern, $temp_content, $attr_match);
                if ( !empty($attr_match) ) {
                    return true;
                }
            }
        }
        return false;
    }
}
