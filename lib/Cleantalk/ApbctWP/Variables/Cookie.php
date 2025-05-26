<?php

namespace Cleantalk\ApbctWP\Variables;

use Cleantalk\ApbctWP\Escape;

class Cookie extends \Cleantalk\Variables\Cookie
{
    protected static $instance;

    public static $force_alt_cookies_global = false;

    public static $force_to_use_alternative_cookies = array(
        'ct_sfw_pass_key',
        'ct_sfw_passed',
        'wordpress_apbct_antibot',
        'apbct_anticrawler_passed',
        'apbct_antiflood_passed',
        'apbct_email_encoder_passed',
        'apbct_bot_detector_exist'
    );

    /**
     * @inheritDoc
     */
    public function getVariable($name)
    {
        global $apbct;

        $name = apbct__get_cookie_prefix() . $name;

        $value = '';

        // Return from memory. From $this->variables
        if ( ! isset(static::$instance->variables[$name]) ) {
            // Getting by alternative way if enabled
            switch ( $apbct->data['cookies_type'] ) {
                case 'alternative':
                    $value = AltSessions::get($name);
                    // Try to get it from native cookies ^_^
                    if ( empty($value) && isset($_COOKIE[$name]) ) {
                        $value = $this->getAndSanitize(urldecode($_COOKIE[$name]));
                    }
                    break;
                case 'none':
                    if ( static::$force_alt_cookies_global || in_array($name, static::$force_to_use_alternative_cookies, true) ) {
                        if ( in_array($name, array(
                            'apbct_page_hits',
                            'apbct_prev_referer',
                            'apbct_timestamp',
                            'apbct_site_referer'))
                        ) {
                            $value = NoCookie::get($name);

                            if (!$value && in_array($name, array('apbct_site_referer', 'apbct_prev_referer', 'apbct_page_hits'))) {
                                $value = AltSessions::get($name);
                            }
                        } else {
                            $value = AltSessions::get($name);
                        }
                    } else {
                        $value = NoCookie::get($name);
                    }
                    break;
                default:
                    if ( static::$force_alt_cookies_global ) {
                        $value = AltSessions::get($name);
                        if ( empty($value) && isset($_COOKIE[$name]) ) {
                            $value = $this->getAndSanitize(urldecode($_COOKIE[$name]));
                        }
                    } else {
                        if ( isset($_COOKIE[$name]) ) {
                            $value = $this->getAndSanitize(urldecode($_COOKIE[$name]));
                        }
                    }
            }

            // Remember for further calls
            static::getInstance()->rememberVariable($name, $value);

            return $value;
        }

        return static::$instance->variables[$name];
    }

    /**
     * Universal method to adding cookies
     * Using Alternative Sessions or native cookies or NoCookie handler depends on settings
     *
     * @param string $name
     * @param string $value
     * @param int $expires
     * @param string $path
     * @param string $domain
     * @param bool|null $secure
     * @param bool $httponly
     * @param string $samesite
     * @param bool $no_cookie_to_db
     * @psalm-suppress PossiblyUnusedReturnValue
     * @psalm-suppress ImplementedReturnTypeMismatch
     * @return bool
     */
    public static function set(
        $name,
        $value = '',
        $expires = 0,
        $path = '',
        $domain = '',
        $secure = null,
        $httponly = false,
        $samesite = 'Lax',
        $no_cookie_to_db = false
    ) {
        global $apbct;

        if ( ! $apbct->data['key_is_ok'] ) {
            return true;
        }

        if ($apbct->data['cookies_type'] === 'alternative' ||
            static::$force_alt_cookies_global ||
            in_array($name, static::$force_to_use_alternative_cookies, true)
        ) {
            return AltSessions::set($name, $value);
        }

        if ( $apbct->data['cookies_type'] === 'none' ) {
            return NoCookie::set($name, $value);
        }

        self::setNativeCookie(apbct__get_cookie_prefix() . $name, $value, $expires, $path, $domain, $secure, $httponly, $samesite);

        return true;
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
        if (headers_sent()) {
            return;
        }

        $value = Escape::escHtml($value);

        $secure = ! is_null($secure) ? $secure : Server::get('HTTPS') || Server::get('SERVER_PORT') == 443;
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
            // Get from alt cookies storage or NoCookie storage
            $visible_fields_collection = (array) self::get('apbct_visible_fields');
        }

        return $visible_fields_collection;
    }

    /**
     * @inheritDoc
     */
    protected function sanitizeDefault($value)
    {
        return sanitize_textarea_field($value);
    }
}
