<?php

namespace Cleantalk\Variables;

/**
 * Class Request
 * Safety handler for $_REQUEST
 *
 * @usage \Cleantalk\Variables\Request::get( $name );
 *
 * @package Cleantalk\Variables
 */
class Request extends ServerVariables
{
    protected static $instance;

    /**
     * Gets given $_REQUEST variable and save it to memory
     *
     * @param $name
     *
     * @return mixed|string
     */
    protected function getVariable($name)
    {
        // Return from memory. From $this->variables
        if (isset(static::$instance->variables[$name])) {
            return static::$instance->variables[$name];
        }

        $value = isset($_REQUEST[$name]) ? $_REQUEST[$name] : '';

        // Remember for further calls
        static::getInstance()->rememberVariable($name, $value);

        return $value;
    }
}
