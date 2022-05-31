<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\HTTP\Request;

/**
 * CleanTalk Anti-Spam Helper class.
 * Compatible only with WordPress.
 *
 * @depends \Cleantalk\Common\Helper
 *
 * @package Anti-Spam Plugin by CleanTalk
 * @subpackage Helper
 * @Version 1.0
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see https://github.com/CleanTalk/wordpress-antispam
 */
class Helper extends \Cleantalk\Common\Helper
{
    /**
     * Function sends raw http request
     *
     * May use 4 presets(combining possible):
     * get_code - getting only HTTP response code
     * async    - async requests
     * get      - GET-request
     * ssl      - use SSL
     *
     * @param string|array<string> $url URL
     * @param array|string|int $data POST|GET indexed array with data to send
     * @param string|array $presets String or Array with presets: get_code, async, get, ssl, dont_split_to_array
     * @param array $opts Optional option for CURL connection
     *
     * @return array|bool|string (array || array('error' => true))
     */
    public static function httpRequest($url, $data = array(), $presets = null, $opts = array())
    {
        // Set APBCT User-Agent and passing data to parent method
        $opts = self::arrayMergeSaveNumericKeys(
            array(
                CURLOPT_USERAGENT => 'APBCT-wordpress/' .
                                     (defined('APBCT_VERSION') ? APBCT_VERSION : 'unknown') .
                                     '; ' . get_bloginfo('url'),
            ),
            $opts
        );

        $http = new Request();

        return $http->setUrl($url)
                    ->setData($data)
                    ->setPresets($presets)
                    ->setOptions($opts)
                    ->request();
    }

    /**
     * Wrapper for http_request
     * Requesting HTTP response code for $url
     *
     * @param string $url
     *
     * @return array|mixed|string
     */
    public static function httpRequestGetResponseCode($url)
    {
        return static::httpRequest($url, array(), 'get_code get');
    }

    /**
     * Wrapper for http_request
     * Requesting data via HTTP request with GET method
     *
     * @param string $url
     *
     * @return array|mixed|string
     */
    public static function httpRequestGetContent($url)
    {
        return static::httpRequest($url, array(), 'get dont_split_to_array');
    }

    /**
     * Performs remote call to the current website
     *
     * @param string $rc_action
     * @param array $request_params
     * @param array $patterns
     * @param bool $do_check Perform check before main remote call or not
     *
     * @return bool|string[]
     */
    public static function httpRequestRcToHost($rc_action, $request_params, $patterns = array(), $do_check = true)
    {
        global $apbct;

        // RemoteCallsCounter
        $logging_data = array(
            'rc_action' => $rc_action,
            'request_params' => $request_params
        );
        $RCCounter = new RemoteCallsCounter($logging_data);
        $RCCounter->execute();

        $request_params = array_merge(array(
            'spbc_remote_call_token' => md5($apbct->api_key),
            'spbc_remote_call_action' => $rc_action,
            'plugin_name' => 'apbct',
        ), $request_params);
        $patterns       = array_merge(
            array(
                'dont_split_to_array'
            ),
            $patterns
        );

        if ($do_check) {
            $result__rc_check_website = static::httpRequestRcToHostTest($rc_action, $request_params, $patterns);
            if ( ! empty($result__rc_check_website['error'])) {
                return $result__rc_check_website;
            }
        }

        static::httpRequest(
            substr(get_option('home'), -1) === '/' ? get_option('home') : get_option('home') . '/',
            $request_params,
            $patterns
        );

        return true;
    }

    /**
     * Performs test remote call to the current website
     * Expects 'OK' string as good response
     *
     * @param array $request_params
     * @param array $patterns
     *
     * @return array|bool|string
     */
    public static function httpRequestRcToHostTest($rc_action, $request_params, $patterns = array())
    {
        // RemoteCallsCounter
        $logging_data = array(
            'rc_action' => $rc_action,
            'request_params' => $request_params
        );
        $RCCounter = new RemoteCallsCounter($logging_data);
        $RCCounter->execute();

        // Delete async pattern to get the result in this process
        $key = array_search('async', $patterns, true);
        if ($key) {
            unset($patterns[$key]);
        }

        $result = static::httpRequest(
            substr(get_option('home'), -1) === '/' ? get_option('home') : get_option('home') . '/',
            array_merge($request_params, array('test' => 'test')),
            $patterns
        );

        // Considering empty response as error
        if ($result === '') {
            $result = array('error' => 'WRONG_SITE_RESPONSE TEST ACTION : ' . $rc_action . ' ERROR: EMPTY_RESPONSE');
            // Wrap and pass error
        } elseif ( ! empty($result['error'])) {
            $result = array('error' => 'WRONG_SITE_RESPONSE TEST ACTION: ' . $rc_action . ' ERROR: ' . $result['error']);
            // Expects 'OK' string as good response otherwise - error
        } elseif ( ( is_string($result) && ! preg_match('@^.*?OK$@', $result) ) || ! is_string($result) ) {
            $result = array(
                'error' => 'WRONG_SITE_RESPONSE ACTION: ' .
                           $rc_action .
                           ' RESPONSE: ' .
                           '"' .
                           htmlspecialchars(
                               substr(
                                   ! is_string($result) ? print_r($result, true) : $result,
                                   0,
                                   400
                               )
                           ) . '"'
            );
        }

        return $result;
    }

    /**
     * Wrapper for http_request
     * Get data from remote GZ archive with all following checks
     *
     * @param string $url
     *
     * @return array|mixed|string
     */
    public static function httpGetDataFromRemoteGz($url)
    {
        $response_code = static::httpRequestGetResponseCode($url);

        if ($response_code === 200) { // Check if it's there
            $data = static::httpRequestGetContent($url);

            if (empty($data['error'])) {
                if (static::getMimeType($data, 'application/x-gzip')) {
                    if (function_exists('gzdecode')) {
                        $data = gzdecode($data);

                        if ($data !== false) {
                            return $data;
                        } else {
                            return array('error' => 'Can not unpack datafile');
                        }
                    } else {
                        return array('error' => 'Function gzdecode not exists. Please update your PHP at least to version 5.4 ' . $data['error']);
                    }
                } else {
                    return array('error' => 'Wrong file mime type: ' . $url);
                }
            } else {
                return array('error' => 'Getting datafile ' . $url . '. Error: ' . $data['error']);
            }
        } else {
            return array('error' => 'Bad HTTP response (' . (int)$response_code . ') from file location: ' . $url);
        }
    }

    /**
     * Wrapper for http__get_data_from_remote_gz
     * Get data and parse CSV from remote GZ archive with all following checks
     *
     * @param string $url
     *
     * @return array|string
     */
    public static function httpGetDataFromRemoteGzAndParseCsv($url)
    {
        $result = static::httpGetDataFromRemoteGz($url);

        return empty($result['error'])
            ? static::bufferParseCsv($result)
            : $result;
    }
}
