<?php

namespace Cleantalk\Common;

class Validate
{
    /**
     * Runs validation for input parameter
     *
     * Now contains filters: hash
     *
     * @param mixed $variable Input to validate
     * @param string $filter_name Validation filter name
     *
     * @return bool
     */
    public static function validate($variable, $filter_name)
    {
        switch ( $filter_name ) {
            case 'hash':
                return static::isHash($variable);
            case 'int':
                return static::isInt($variable);
            case 'float':
                return static::isFloat($variable);
            case 'word':
                return static::isWord($variable);
            case 'isUrl':
                return static::isUrl($variable);
        }

        return false;
    }

    /**
     * Simple method: validate hash
     */
    public static function isHash($variable)
    {
        return preg_match('#^[a-zA-Z0-9]{8,128}$#', $variable) === 1;
    }

    /**
     * Simple method: validate int
     */
    public static function isInt($variable)
    {
        return preg_match('#^\d+$#', $variable) === 1;
    }

    /**
     * Simple method: validate float
     */
    public static function isFloat($variable)
    {
        return preg_match('#^[\d.]+\d+$#', $variable) === 1;
    }

    /**
     * Simple method: validate word
     */
    public static function isWord($variable)
    {
        return preg_match('#^[a-zA-Z0-9_.\-,]+$#', $variable);
    }

    /**
     * Simple method: validate email
     */
    public static function isEmail($variable)
    {
        // TODO
    }

    /**
     * Simple method: validate file path (not exists)
     */
    public static function isValidFilePath($variable)
    {
        // TODO
    }

    public static function isUrl($url)
    {
        if ( ! is_string($url) || $url === '' ) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        // Allow only http/https. Checking the scheme prevents javascript:/data: bypasses
        // that embed "http://" or "https://" in the rest of the string.
        return in_array($scheme, array('http', 'https'), true) &&
               (bool) filter_var($url, FILTER_VALIDATE_URL);
    }

    /**
     * Checks if given string is valid regular expression
     *
     * @param string $regexp
     *
     * @return bool
     */
    public static function isRegexp($regexp)
    {
        return @preg_match('/' . $regexp . '/', '') !== false;
    }

    /**
     * Check if the string is encoded by urlencode()
     *
     * @param $value
     *
     * @return bool
     */
    public static function isUrlencoded($value)
    {
        return urlencode(urldecode($value)) === $value;
    }
}
