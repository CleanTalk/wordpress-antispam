<?php

namespace Cleantalk\Common\Variables;

/**
 * Class Server
 * Wrapper to safely get $_SERVER variables
 *
 * @usage \CleantalkSP\Variables\Server::get( $name );
 *
 * @package \CleantalkSP\Variables
 */
class Server extends ServerVariables
{
    protected static $instance;

    /**
     * Gets given $_SERVER variable and save it to memory
     *
     * @param string $name
     *
     * @return mixed|string
     */
    protected function getVariable($name)
    {
        // Return from memory. From $this->server
        if (isset(static::$instance->variables[$name])) {
            return static::$instance->variables[$name];
        }

        $name = strtoupper($name);

        if ( isset($_SERVER[$name]) ) {
            $value = $this->getAndSanitize($_SERVER[$name]);
        } else {
            $value = '';
        }

        // Convert to upper case for REQUEST_METHOD
        if ($name === 'REQUEST_METHOD') {
            $value = strtoupper($value);
        }

        // Convert HTML chars for HTTP_USER_AGENT, HTTP_USER_AGENT, SERVER_NAME
        if (in_array($name, array('HTTP_USER_AGENT', 'HTTP_USER_AGENT', 'SERVER_NAME'))) {
            $value = htmlspecialchars($value);
        }

        // Remember for further calls
        static::getInstance()->rememberVariable($name, $value);

        return $value;
    }

    /**
     * Checks if $_SERVER['REQUEST_URI'] contains string
     *
     * @param string $needle
     *
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function inUri($needle)
    {
        return self::hasString('REQUEST_URI', $needle);
    }

    /**
     * Is the host contains the string
     *
     * @param string $needle
     *
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function inHost($needle)
    {
        return self::hasString('HTTP_HOST', $needle);
    }

    /**
     * Getting domain name
     *
     * @return false|string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function getDomain()
    {
        preg_match('@\S+\.(\S+)\/?$@', self::get('HTTP_HOST'), $matches);

        return isset($matches[1]) ? $matches[1] : false;
    }

    /**
     * Checks if $_SERVER['REQUEST_URI'] contains string
     *
     * @param string $needle needle
     *
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function inReferer($needle)
    {
        return self::hasString('HTTP_REFERER', $needle);
    }

    /**
     * Checks if the current request method is POST
     *
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function isPost()
    {
        return self::get('REQUEST_METHOD') === 'POST';
    }

    /**
     * Checks if the current request method is GET
     *
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function isGet()
    {
        return self::get('REQUEST_METHOD') === 'GET';
    }

    /**
     * Determines if SSL is used.
     *
     * @return bool True if SSL, otherwise false.
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function isSSL()
    {
        return self::get('HTTPS') === 'on' ||
               self::get('HTTPS') === '1' ||
               self::get('SERVER_PORT') == '443';
    }
}
