<?php

namespace Cleantalk\Variables;

use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Validate;
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
     *
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
     * @param string $value
     */
    protected function rememberVariable($name, $value)
    {
        static::$instance->variables[$name] = $value;
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
        return stripos(self::get($var), $string) !== false;
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
}
