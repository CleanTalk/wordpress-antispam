<?php

namespace Cleantalk\ApbctWP\Variables;

use Cleantalk\ApbctWP\Helper;
use Cleantalk\Variables\Server;

class Cookie extends \Cleantalk\Variables\Cookie
{
    /**
     * @param string $name
     * @param string|array $default
     * @param null|string $cast_to
     * @param false $raw
     *
     * @return string|array
     */
    public static function get($name, $default = '', $cast_to = null, $raw = false)
    {
        global $apbct;

        // Return from memory. From $this->variables
        if (isset(static::$instance->variables[$name])) {
            $value = static::$instance->variables[$name];
            // Get from GLOBAL variable
        } else {
            // Getting by alternative way if enabled
            if ($apbct->settings['data__set_cookies'] == 2) {
                $value = AltSessions::get($name);
                // The old way
            } else {
                if (function_exists('filter_input')) {
                    $value = filter_input(INPUT_COOKIE, $name);
                }

                if (empty($value)) {
                    $value = isset($_COOKIE[$name]) ? $_COOKIE[$name] : '';
                }
            }

            // Remember for further calls
            static::getInstance()->rememberVariable($name, $value);
        }

        // Decoding by default
        if ( ! $raw) {
            $value = urldecode($value); // URL decode
            $value = Helper::isJson($value) ? json_decode($value, true) : $value; // JSON decode
            if ( ! is_null($cast_to)) {
                settype($value, $cast_to);
                $value = $cast_to === 'array' && $value === array('') ? array() : $value;
            }
        }

        return ! $value ? $default : $value;
    }

    /**
     * Universal method to adding cookies
     * Using Alternative Sessions or native cookies depends on settings
     *
     * @param string $name
     * @param string $value
     * @param int $expires
     * @param string $path
     * @param string $domain
     * @param bool|null $secure
     * @param bool $httponly
     * @param string $samesite
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
        global $apbct;

        if ($apbct->settings['data__set_cookies'] == 0 && ! is_admin()) {
            return;
        } elseif ($apbct->settings['data__set_cookies'] == 2) {
            AltSessions::set($name, $value);
        } else {
            self::setNativeCookie($name, $value, $expires, $path, $domain, $secure, $httponly, $samesite);
        }
    }

    /**
     * Universal method to adding cookies
     * Wrapper for setcookie() Considering PHP version
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
     */
    public static function setNativeCookie(
        $name,
        $value = '',
        $expires = 0,
        $path = '',
        $domain = '',
        $secure = null,
        $httponly = false,
        $samesite = 'Lax'
    ) {
        // For PHP 7.3+ and above
        if (version_compare(phpversion(), '7.3.0', '>=')) {
            $secure = ! is_null($secure) ? $secure : Server::get('HTTPS') || Server::get('SERVER_PORT') == 443;

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
}
