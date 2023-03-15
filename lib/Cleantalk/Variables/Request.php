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
        if (isset(static::getInstance()->variables[$name])) {
            return static::getInstance()->variables[$name];
        }

        $value = '';

        $class_name = get_class(self::getInstance());
        $reflection_class = new \ReflectionClass($class_name);
        $namespace = $reflection_class->getNamespaceName();

        $post_class = $namespace . '\\Post';
        $get_class = $namespace . '\\Get';

        if ( $post_class::get($name) ) {
            $value = $post_class::get($name);
        } elseif ( $get_class::get($name) ) {
            $value = $get_class::get($name);
        }

        // Remember for further calls
        static::getInstance()->rememberVariable($name, $value);

        return $value;
    }

    protected function sanitizeDefault($value)
    {
        return sanitize_textarea_field($value);
    }
}
