<?php

namespace Cleantalk\ApbctWP\RequestParameters;

use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\NoCookie;

class RequestParameters
{
    /**
     * @param string $param_name
     * @param bool $http_only
     *
     * @return mixed
     */
    public static function get($param_name, $http_only = false)
    {
        switch ( self::getParamsType() ) {
            case 'none':
                if ( $http_only ) {
                    return AltSessions::get($param_name);
                }
                return NoCookie::get($param_name);

            case 'alternative':
                return AltSessions::get($param_name);

            case 'native':
            default:
                return Cookie::get($param_name);
        }
    }

    /**
     * @param string $param_name
     * @param string $param_value
     * @param bool $http_only
     *
     * @return bool
     *
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public static function set($param_name, $param_value, $http_only = false)
    {
        switch ( self::getParamsType() ) {
            case 'none':
                if ( $http_only ) {
                    return AltSessions::set($param_name, $param_value);
                }
                return NoCookie::set($param_name, $param_value);

            case 'alternative':
                return AltSessions::set($param_name, $param_value);

            case 'native':
            default:
                return Cookie::set($param_name, $param_value, 0, '/', '', null, $http_only);
        }
    }

    private static function getParamsType()
    {
        global $apbct;
        return $apbct->data['cookies_type'];
    }
}
