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
        if (! isset(static::getInstance()->variables[$name])) {
            if ( isset($_GET[$name]) ) {
                $value = $this->getAndSanitize($_GET[$name]);
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
