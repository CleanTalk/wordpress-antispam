<?php

namespace Cleantalk\ApbctWP\RequestParameters;

use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\NoCookie;

class RequestParameters
{
    /**
     * @param string $param_name Param name
     * @param bool $alt_sessions_for_none_mode Use alternative sessions source for NoCookie mode
     *
     * @return mixed
     */
    public static function get($param_name, $alt_sessions_for_none_mode = false)
    {
        switch ( self::getParamsType() ) {
            case 'none':
                if ( $alt_sessions_for_none_mode ) {
                    $out = AltSessions::get($param_name);
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
     * @param bool $alt_sessions_for_none_mode Use alternative sessions source for NoCookie mode
     *
     * @return bool
     *
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public static function set($param_name, $param_value, $alt_sessions_for_none_mode = false)
    {
        switch ( self::getParamsType() ) {
            case 'none':
                if ( $alt_sessions_for_none_mode ) {
                    return AltSessions::set($param_name, $param_value);
                }
                return NoCookie::set($param_name, $param_value, true);

            case 'alternative':
                return AltSessions::set($param_name, $param_value);

            case 'native':
            default:
                return Cookie::set($param_name, $param_value, 0, '/', '', null, $alt_sessions_for_none_mode);
        }
    }

    private static function getParamsType()
    {
        global $apbct;
        return $apbct->data['cookies_type'];
    }
}
