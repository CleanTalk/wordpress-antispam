<?php

namespace Cleantalk\Variables;

/**
 * Class Cookie
 * Safety handler for $_COOKIE
 *
 * @usage \Cleantalk\Variables\Cookie::get( $name );
 *
 * @package Cleantalk\Variables
 */
class Cookie extends ServerVariables
{
    /**
     * Gets given $_COOKIE variable and save it to memory
     *
     * @param $name
     *
     * @return mixed|string
     */
    protected function getVariable($name)
    {
        // Return from memory. From $this->variables
        if (! isset(static::getInstance()->variables[$name])) {
            if ( isset($_COOKIE[$name]) ) {
                $value = $this->getAndSanitize($_COOKIE[$name]);
            } else {
                $value = '';
            }

            // Remember for further calls
            static::getInstance()->rememberVariable($name, $value);

            return $value;
        }

        return static::getInstance()->variables[$name];
    }

    /**
     * Universal method to adding cookies
     * Wrapper for setcookie() Conisdering PHP version
     *
     * @see https://www.php.net/manual/ru/function.setcookie.php
     *
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param int $expires Expiration timestamp. 0 - expiration with session
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     * @param string $samesite
     *
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function set(
        $name,
        $value = '',
        $expires = 0,
        $path = '',
        $domain = '',
        $secure = null,
        $httponly = false,
        $samesite = 'Lax'
    ) {
        if (headers_sent()) {
            return;
        }

        $secure = ! is_null($secure) ? $secure : ! in_array(Server::get('HTTPS'), ['off', '']) || Server::get('SERVER_PORT') == 443;

        // For PHP 7.3+ and above
        if ( version_compare(phpversion(), '7.3.0', '>=') ) {
            $params = array(
                'expires' => $expires,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httponly,
            );

            if ($samesite) {
                $params['samesite'] = $samesite;
            }

            /**
             * @psalm-suppress InvalidArgument
             */
            setcookie($name, $value, $params);
            // For PHP 5.6 - 7.2
        } else {
            setcookie($name, $value, $expires, $path, $domain, $secure, $httponly);
        }
    }

    protected function sanitizeDefault($value)
    {
        return sanitize_textarea_field($value);
    }

    public static function getNativeCookieValue($cookie_name)
    {
        if ( isset($_COOKIE[$cookie_name]) ) {
            return sanitize_textarea_field($_COOKIE[$cookie_name]);
        }

        return null;
    }
}
