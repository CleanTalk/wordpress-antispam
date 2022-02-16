<?php

namespace Cleantalk\Variables;

/**
 * Class Post
 * Safety handler for $_POST
 *
 * @usage \Cleantalk\Variables\Post::get( $name );
 *
 * @package Cleantalk\Variables
 */
class Post extends ServerVariables
{
    protected static $instance;

    /**
     * Gets given $_POST variable and save it to memory
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
                $value = filter_input(INPUT_POST, $name);
            }

            if (empty($value)) {
                $value = isset($_POST[$name]) ? $_POST[$name] : '';
            }

            // Remember for further calls
            static::getInstance()->rememberVariable($name, $value);

            return $value;
        }

        return static::$instance->variables[$name];
    }
}
