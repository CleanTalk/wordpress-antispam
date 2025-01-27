<?php

namespace Cleantalk\Variables;

use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Validate;
use Cleantalk\Common\TT;
use Cleantalk\Templates\Singleton;

/**
 * Class ServerVariables
 * Safety handler for ${_SOMETHING}
 *
 * @usage \Cleantalk\Variables\{SOMETHING}::get( $name );
 *
 * @package Cleantalk\Variables
 * @psalm-suppress PossiblyUnusedProperty
 */
abstract class ServerVariables
{
    use Singleton;

    /**
     * @var array Contains saved variables
     */
    public $variables = array();

    /**
     * Gets variable from ${_SOMETHING}
     *
     * @param string $name Variable name
     * @param null|string $validation_filter
     * @psalm-param (null|"hash"|"int"|"float"|"word"|"isUrl") $validation_filter
     * @param null|string $sanitize_filter
     * @psalm-param (null|"xss"|"int"|"url"|"word"|"cleanEmail") $sanitize_filter
     * @deprecated Use getInt, getString, getBool, getArray instead
     * @return string|array|false
     */
    public static function get($name, $validation_filter = null, $sanitize_filter = null)
    {
        $variable = static::getInstance()->getVariable($name);

        // Validate variable
        if ( $validation_filter && ! Validate::validate($variable, $validation_filter) ) {
            return false;
        }

        if ( $sanitize_filter ) {
            $variable = Sanitize::sanitize($variable, $sanitize_filter);
        }

        return $variable;
    }

    /**
     * @param $var_name
     * @param $default
     * @param $validation_filter
     * @param $sanitize_filter
     *
     * @return string
     */
    public static function getString($var_name, $default = '', $validation_filter = null, $sanitize_filter = null)
    {
        return TT::toString(
            static::get(
                $var_name,
                $validation_filter,
                $sanitize_filter
            ),
            $default
        );
    }

    /**
     * @param $var_name
     * @param $default
     *
     * @return int
     */
    public static function getInt($var_name, $default = 0)
    {
        return TT::toInt(
            static::get(
                $var_name
            ),
            $default
        );
    }

    /**
     * @param $var_name
     * @param $default
     *
     * @return bool
     */
    public static function getBool($var_name, $default = false)
    {
        return TT::toBool(
            static::get(
                $var_name
            ),
            $default
        );
    }

    /**
     * @param $var_name
     * @param $default
     *
     * @return array
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function getArray($var_name, $default = array())
    {
        return TT::toArray(
            static::get(
                $var_name
            ),
            $default
        );
    }

    /**
     * BLUEPRINT
     * Gets given ${_SOMETHING} variable and save it to memory
     *
     * @param $name
     *
     * @return mixed|string
     */
    abstract protected function getVariable($name);

    /**
     * Save variable to $this->variables[]
     *
     * @param string $name
     * @param mixed $value
     */
    protected function rememberVariable($name, $value)
    {
        static::getInstance()->variables[$name] = $value;
    }

    /**
     * Checks if variable contains given string
     *
     * @param string $var Haystack to search in
     * @param string $string Needle to search
     *
     * @return bool
     */
    public static function hasString($var, $string)
    {
        return stripos(self::getString($var), $string) !== false;
    }

    /**
     * Checks if variable equal to $param
     *
     * @param string $var Variable to compare
     * @param string $param Param to compare
     *
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function equal($var, $param)
    {
        return self::get($var) === $param;
    }

    /**
     * @param $value
     * @param $nesting
     *
     * @return string|array
     */
    public function getAndSanitize($value, $nesting = 0)
    {
        if ( is_array($value) ) {
            foreach ( $value as $_key => & $val ) {
                if ( is_array($val) ) {
                    if ( $nesting > 20 ) {
                        return $value;
                    }
                    $this->getAndSanitize($val, ++$nesting);
                } else {
                    $val = $this->sanitizeDefault($val);
                }
            }
        } else {
            $value = $this->sanitizeDefault($value);
        }
        return $value;
    }

    /**
     * Sanitize gathering data.
     * No sanitizing by default.
     * Override this method in the internal class!
     *
     * @param string $value
     *
     * @return string
     */
    abstract protected function sanitizeDefault($value);
}
