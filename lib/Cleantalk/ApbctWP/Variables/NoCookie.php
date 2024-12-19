<?php

namespace Cleantalk\ApbctWP\Variables;

use Cleantalk\ApbctWP\Helper;

class NoCookie
{
    public static $no_cookies_data = array();

    /**
     * Set value of NoCookie data. Just save to the static prop $no_cookies_data. Returns result of operation.
     * @param $name
     * @param $value
     * @return bool
     */
    public static function set($name, $value)
    {
        if ( is_int($value) ) {
            $value = (string)$value;
        }

        // Bad incoming data
        if ( !$name
            || ( empty($value) && $value !== "0" )
            || is_array($value)
            || is_array($name)
        ) {
            return false;
        }

        self::$no_cookies_data[$name] = $value;

        return true;
    }

    /**
     * Get NoCookie data from static prop $no_cookies_data.
     * @param $name string
     * @return false|mixed|string
     */
    public static function get($name)
    {
        // Bad incoming data
        if ( !$name
            ||
            !is_string($name)
        ) {
            return false;
        }

        if ( isset(self::$no_cookies_data[$name]) ) {
            return self::$no_cookies_data[$name];
        }

        return false;
    }


    /**
     * Check data transferred via ct_no_cookie_hidden_field, handle them then
     * @param string $data
     * @return bool
     */
    public static function setDataFromHiddenField($data)
    {
        if ( !empty($data) && is_string($data)) {
            // remove noise if exists
            $delimiters = ['_ct_no_cookie_data_', '%', '&'];
            foreach ($delimiters as $delimiter) {
                $noise_start_on = strpos($data, $delimiter);
                if ($noise_start_on !== false) {
                    $data = substr($data, $noise_start_on);
                }
            }
            //delete sign of no cookie raw data
            $data = str_replace('_ct_no_cookie_data_', '', $data);
            //decode raw data
            $data = base64_decode($data);
            if ( $data ) {
                //decode json
                $data = json_decode($data, true);
                if ( !empty($data) && is_array($data) ) {
                    self::$no_cookies_data = array_merge(self::$no_cookies_data, $data);
                    return true;
                }
            }
        }

        return false;
    }
}
