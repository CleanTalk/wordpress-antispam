<?php

namespace Cleantalk\ApbctWP\RequestParameters;

use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\NoCookie;

class RequestParameters
{
    /**
     * @param string $param_name Param name
     * @param bool $http_only Use alternative sessions source for NoCookie mode
     *
     * @return mixed
     */
    public static function get($param_name, $http_only = false)
    {
        switch ( self::getParamsType() ) {
            case 'none':
                if ( $http_only ) {
                    $out = static::getCommonStorage($param_name);
                    break;
                }
                $out = NoCookie::get($param_name);
                break;

            case 'alternative':
                $out = AltSessions::get($param_name);
                break;

            case 'native':
            default:
                $out = Cookie::get($param_name);
        }

        return $out;
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
                    return self::setCommonStorage($param_name, $param_value);
                }
                return NoCookie::set($param_name, $param_value);

            case 'alternative':
                return AltSessions::set($param_name, $param_value);

            case 'native':
            default:
                return Cookie::set($param_name, $param_value, 0, '/', '', null, $http_only);
        }
    }

    /**
     * @return mixed
     */
    private static function getParamsType()
    {
        global $apbct;
        return $apbct->data['cookies_type'];
    }

    /**
     * Use common storage to get param. Use this way for the params if there is no difference of cookies type set up.
     * @param $param_name
     * @return false|mixed|string
     */
    public static function getCommonStorage($param_name)
    {
        //for now common storage is AltSession logic.
        return AltSessions::get($param_name);
    }

    /**
     * Use common storage to set param. Use this way for the params if there is no difference of cookies type set up.
     * @param $param_name
     * @param $param_value
     * @return bool
     */
    public static function setCommonStorage($param_name, $param_value)
    {
        //for now common storage is AltSession logic.
        return AltSessions::set($param_name, $param_value);
    }
}
