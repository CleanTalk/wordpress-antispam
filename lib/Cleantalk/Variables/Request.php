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
abstract class Request extends ServerVariables
{
    protected static $instance;

    /**
     * Gets given $_REQUEST variable and save it to memory
     *
     * @param $name
     *
     * @return mixed|string
     * @throws \ReflectionException
     */
    protected function getVariable($name)
    {
        // Return from memory. From $this->variables
        if (isset(static::$instance->variables[$name])) {
            return static::$instance->variables[$name];
        }

        $value = '';

        $class_name = get_class(self::getInstance());
        $reflection_class = new \ReflectionClass($class_name);
        $namespace = $reflection_class->getNamespaceName();

        if ( $namespace . '\\' . Post::get($name) ) {
            $value = $namespace . '\\' . Post::get($name);
        } elseif ( $namespace . '\\' . Get::get($name) ) {
            $value = $namespace . '\\' . Get::get($name);
        } elseif ( $namespace . '\\' . Cookie::get($name) ) {
            $value = $namespace . '\\' . Cookie::get($name);
        }

        // Remember for further calls
        static::getInstance()->rememberVariable($name, $value);

        return $value;
    }
}
