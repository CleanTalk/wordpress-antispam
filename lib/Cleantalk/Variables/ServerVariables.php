<?php

namespace Cleantalk\Variables;

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
     *
     * @return string|array
     */
    public static function get($name)
    {
        return static::getInstance()->getVariable($name);
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
