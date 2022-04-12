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

    public static function isUrl($variable)
    {
        // @ToDo
        return $variable;
    }
}
