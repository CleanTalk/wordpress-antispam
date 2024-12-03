<?php

namespace Cleantalk\ApbctWP;

class Sanitize extends \Cleantalk\Common\Sanitize
{
    /**
     * Simple method: clean url
     */
    public static function cleanUrl($variable)
    {
        return sanitize_url($variable);
    }

    /**
     * Simple method: clean email
     */
    public static function cleanEmail($variable)
    {
        return sanitize_email($variable);
    }

    /**
     * Simple method: clean file name
     */
    public static function cleanFileName($variable)
    {
        return sanitize_file_name($variable);
    }

    /**
     * Simple method: clean hex color
     */
    public static function cleanHexColor($variable)
    {
        return sanitize_hex_color($variable);
    }

    /**
     * Simple method: clean hex color no hash
     */
    public static function cleanHexColorNoHash($variable)
    {
        return sanitize_hex_color_no_hash($variable);
    }

    /**
     * Simple method: clean html class
     */
    public static function cleanHtmlClass($variable)
    {
        return sanitize_html_class($variable);
    }

    /**
     * Simple method: clean key
     */
    public static function cleanKey($variable)
    {
        return sanitize_key($variable);
    }

    /**
     * Simple method: clean meta
     */
    public static function cleanMeta($meta_key, $meta_value, $object_type)
    {
        return sanitize_meta($meta_key, $meta_value, $object_type);
    }

    /**
     * Simple method: clean mime type
     */
    public static function cleanMimeType($variable)
    {
        return sanitize_mime_type($variable);
    }

    /**
     * Simple method: clean option
     */
    public static function cleanOption($option, $value)
    {
        return sanitize_option($option, $value);
    }

    /**
     * Simple method: clean sql order by
     */
    public static function cleanSqlOrderBy($variable)
    {
        return sanitize_sql_orderby($variable);
    }

    /**
     * Simple method: clean text field
     */
    public static function cleanTextField($variable)
    {
        return sanitize_text_field($variable);
    }

    /**
     * Simple method: clean textarea field
     */
    public static function cleanTextareaField($variable)
    {
        return sanitize_textarea_field($variable);
    }

    /**
     * Simple method: clean title
     */
    public static function cleanTitle($variable)
    {
        return sanitize_title($variable);
    }

    /**
     * Simple method: clean title for query
     */
    public static function cleanTitleForQuery($variable)
    {
        return sanitize_title_for_query($variable);
    }

    /**
     * Simple method: clean title with dashes
     */
    public static function cleanTitleWithDashes($variable)
    {
        return sanitize_title_with_dashes($variable);
    }

    /**
     * Simple method: clean user
     */
    public static function cleanUser($variable)
    {
        return sanitize_user($variable);
    }

    /**
     * @param $url
     *
     * @return string|null
     */
    public static function sanitizeCleantalkServerUrl($url)
    {
        if (!is_string($url)) {
            return null;
        }
        return preg_match('/^.*(moderate|api).*\.cleantalk.org(?!\.)[\/\\\\]{0,1}/m', $url)
            ? $url
            : null;
    }
}
