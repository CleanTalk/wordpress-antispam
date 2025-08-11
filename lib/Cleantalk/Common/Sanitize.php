<?php

namespace Cleantalk\Common;

class Sanitize
{
    /**
     * Runs sanitizing process for input
     *
     * Now contains no filters: xss, url
     *
     * @param mixed $variable Input to sanitize
     * @param string $filter_name Sanitizing filter name
     *
     * @return string
     */
    public static function sanitize($variable, $filter_name)
    {
        switch ( $filter_name ) {
            // XSS. Recursive.
            case 'xss':
                return static::cleanXss($variable);

            // URL
            case 'url':
                return static::cleanUrl($variable);

            // Simple string
            case 'word':
                return static::cleanWord($variable);

            case 'int':
                return static::cleanInt($variable);

            case 'cleanEmail':
                return static::cleanEmail($variable);
        }

        return $variable;
    }

    /**
     * Simple method: clean xss
     */
    public static function cleanXss($variable)
    {
        $variable_filtered = preg_replace('#[\'"].*?>.*?<#i', '', $variable);

        return $variable === $variable_filtered
            ? htmlspecialchars($variable_filtered)
            : static::sanitize($variable_filtered, 'xss');
    }

    /**
     * Simple method: clean url
     */
    public static function cleanUrl($variable)
    {
        return preg_replace('#[^a-zA-Z0-9$\-_.+!*\'(),{}|\\^~\[\]`<>\#%";\/?:@&=.]#i', '', $variable);
    }

    /**
     * Simple method: clean word
     */
    public static function cleanWord($variable)
    {
        return preg_replace('#[^a-zA-Z0-9_.\-,]#', '', $variable);
    }

    /**
     * Simple method: clean int
     */
    public static function cleanInt($variable)
    {
        return preg_replace('#[^0-9.,]#', '', $variable);
    }

    /**
     * Simple method: clean email
     */
    public static function cleanEmail($variable)
    {
        // TODO
        return $variable;
    }

    /**
     * Simple method: clean file name
     */
    public static function cleanFileName($variable)
    {
        // TODO
    }

    /**
     * Simple method: clean hex color
     */
    public static function cleanHexColor($variable)
    {
        // TODO
    }

    /**
     * Simple method: clean hex color no hash
     */
    public static function cleanHexColorNoHash($variable)
    {
        // TODO
    }

    /**
     * Simple method: clean html class
     */
    public static function cleanHtmlClass($variable)
    {
        // TODO
    }

    /**
     * Simple method: clean key
     */
    public static function cleanKey($variable)
    {
        // TODO
    }

    /**
     * Simple method: clean meta
     */
    public static function cleanMeta($meta_key, $meta_value, $object_type)
    {
        // TODO
    }

    /**
     * Simple method: clean mime type
     */
    public static function cleanMimeType($variable)
    {
        // TODO
    }

    /**
     * Simple method: clean option
     */
    public static function cleanOption($option, $value)
    {
        // TODO
    }

    /**
     * Simple method: clean sql order by
     */
    public static function cleanSqlOrderBy($variable)
    {
        // TODO
    }

    /**
     * Simple method: clean text field
     */
    public static function cleanTextField($variable)
    {
        // TODO
    }

    /**
     * Simple method: clean textarea field
     */
    public static function cleanTextareaField($variable)
    {
        // TODO
    }

    /**
     * Simple method: clean title
     */
    public static function cleanTitle($variable)
    {
        // TODO
    }

    /**
     * Simple method: clean title for query
     */
    public static function cleanTitleForQuery($variable)
    {
        // TODO
    }

    /**
     * Simple method: clean title with dashes
     */
    public static function cleanTitleWithDashes($variable)
    {
        // TODO
    }

    /**
     * Simple method: clean user
     */
    public static function cleanUser($variable)
    {
        // TODO
    }
}
