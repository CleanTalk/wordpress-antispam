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
        if (! isset(static::getInstance()->variables[$name])) {
            if ( isset($_POST[$name]) ) {
                $value = $this->getAndSanitize($_POST[$name]);
            } else {
                $value = '';
            }

            // Remember for further calls
            static::getInstance()->rememberVariable($name, $value);

            return $value;
        }

        return static::getInstance()->variables[$name];
    }

    protected function sanitizeDefault($value)
    {
        return sanitize_textarea_field($value);
    }
}
