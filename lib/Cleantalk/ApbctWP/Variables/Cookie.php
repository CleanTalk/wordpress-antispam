<?php

namespace Cleantalk\ApbctWP\Variables;

use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Validate;
use Cleantalk\Variables\Server;

class Cookie extends \Cleantalk\Variables\Cookie
{
    /**
     * @inheritDoc
     */
    public static function get($name, $validation_filter = null, $sanitize_filter = null)
    {
        global $apbct;

        // Return from memory. From $this->variables
        if (isset(static::$instance->variables[$name])) {
            $value = static::$instance->variables[$name];
            // Get from GLOBAL variable
        } else {
            // Getting by alternative way if enabled
            if ($apbct->data['cookies_type'] === 'alternative') {
                $value = AltSessions::get($name);
                // The old way
            } else {
                $name = apbct__get_cookie_prefix() . $name;
                if (function_exists('filter_input')) {
                    $value = filter_input(INPUT_COOKIE, $name);
                }

                if (empty($value)) {
                    $value = isset($_COOKIE[$name]) ? $_COOKIE[$name] : '';
                }
            }

            // Validate variable
            if ( $validation_filter && ! Validate::validate($value, $validation_filter) ) {
                return false;
            }

            if ( $sanitize_filter ) {
                $value = Sanitize::sanitize($value, $sanitize_filter);
            }

            // Remember for further calls
            static::getInstance()->rememberVariable($name, $value);
        }

        // Decoding
        $value = urldecode($value); // URL decode

        return $value;
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

        if ($apbct->data['cookies_type'] === 'none' && ! is_admin()) {
            return;
        } elseif ($apbct->data['cookies_type'] === 'alternative') {
            AltSessions::set($name, $value);
        } else {
            self::setNativeCookie(apbct__get_cookie_prefix() . $name, $value, $expires, $path, $domain, $secure, $httponly, $samesite);
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

    /**
     * Getting visible fields collection
     *
     * @return array
     */
    public static function getVisibleFields()
    {
        global $apbct;

        if ( $apbct->data['cookies_type'] === 'native' ) {
            // Get from separated native cookies and convert it to collection
            $visible_fields_cookies_array = array_filter($_COOKIE, function ($key) {
                return strpos($key, 'apbct_visible_fields_') !== false;
            }, ARRAY_FILTER_USE_KEY);
            $visible_fields_collection = array();
            foreach ( $visible_fields_cookies_array as $visible_fields_key => $visible_fields_value ) {
                $prepared_key = str_replace('apbct_visible_fields_', '', $visible_fields_key);
                $prepared_value = json_decode(str_replace('\\', '', $visible_fields_value), true);
                $visible_fields_collection[$prepared_key] = $prepared_value;
            }
        } else {
            // Get from alt cookies storage
            $visible_fields_collection = (array) self::get('apbct_visible_fields');
        }

        return $visible_fields_collection;
    }
}
