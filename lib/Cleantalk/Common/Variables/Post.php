<?php


namespace Cleantalk\Common\Variables;

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
            if ( isset($_POST[$name]) ) {
                $value = $this->getAndSanitize($_POST[$name]);
            } else {
                $value = '';
            }

            // Remember for further calls
            static::getInstance()->rememberVariable($name, $value);

            return $value;
        }

        return static::$instance->variables[$name];
    }
}
