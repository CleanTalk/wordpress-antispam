<?php

namespace Cleantalk\Variables;

/**
 * Class Get
 * Safety handler for $_GET
 *
 * @usage \Cleantalk\Variables\Get::get( $name );
 *
 * @package Cleantalk\Variables
 */
class Get extends ServerVariables
{
    protected static $instance;

    /**
     * Gets given $_GET variable and save it to memory
     *
     * @param $name
     *
     * @return mixed|string
     */
    protected function getVariable($name)
    {
        // Return from memory. From $this->variables
        if (! isset(static::$instance->variables[$name])) {
            if (function_exists('filter_input')) {
                $value = filter_input(INPUT_GET, $name);
            }

            if (empty($value)) {
                $value = isset($_GET[$name]) ? $_GET[$name] : '';
            }

            // Remember for further calls
            static::getInstance()->rememberVariable($name, $value);

            return $value;
        }

        return static::$instance->variables[$name];
    }
}
