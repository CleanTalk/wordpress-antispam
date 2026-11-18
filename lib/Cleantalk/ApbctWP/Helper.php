<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\HTTP\Request;
use Cleantalk\Common\TT;

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
     * Value of the "misc__get_ip" setting for the automatic IP source detection.
     */
    const IP_SOURCE_AUTO = 1;

    /**
     * Value of the "misc__get_ip" setting for the REMOTE_ADDR IP source.
     */
    const IP_SOURCE_REMOTE_ADDR = 2;

    /**
     * Known IP sources collection: array( id => array(slug, name), ... ).
     * Ids are used as values of the "misc__get_ip" setting, slugs are ipGet() types, names are HTTP headers.
     *
     * @var array<int, array{slug: string, name: string}>
     */
    public static $known_ip_headers_collection = array(
        2 => array('slug' => 'remote_addr', 'name' => 'Remote Addr'),
        3 => array('slug' => 'x_forwarded_for', 'name' => 'X-Forwarded-For'),
        4 => array('slug' => 'x_real_ip', 'name' => 'X-Real-Ip'),
        5 => array('slug' => 'incapsula', 'name' => 'Incap-Client-Ip'),
        6 => array('slug' => 'ico_x_forwarded_for', 'name' => 'Ico-X-Forwarded-For'),
        7 => array('slug' => 'stackpath', 'name' => 'X-Sp-Forwarded-Ip'),
        8 => array('slug' => 'x_forwarded_by', 'name' => 'X-Client-Ip'),
        9 => array('slug' => 'sucury', 'name' => 'X-Sucuri-Clientip'),
        10 => array('slug' => 'ezoic', 'name' => 'X-Middleton-Ip'),
        11 => array('slug' => 'gtranslate', 'name' => 'X-Gt-Viewer-Ip'),
        12 => array('slug' => 'cloud_flare', 'name' => 'Cf-Connecting-Ip'),
        13 => array('slug' => 'ovh', 'name' => 'Remote-Ip'),
    );

    /**
     * Get the visitor IP respecting the "misc__get_ip" setting.
     *
     * If a concrete IP source is selected, the "real" IP is taken from it only. If the selected
     * header is not provided, REMOTE_ADDR is used: the request did not come through the selected proxy,
     * so any other proxy header in it is untrusted.
     *
     * @param string $ip_type_to_get Type of IP you want to receive
     * @param bool $v4_only
     * @param array $headers
     *
     * @return string|null
     */
    public static function ipGet($ip_type_to_get = 'real', $v4_only = true, $headers = array())
    {
        $source_slug = $ip_type_to_get === 'real' ? self::getSelectedIpSourceSlug() : null;

        if ( $source_slug === null ) {
            return parent::ipGet($ip_type_to_get, $v4_only, $headers);
        }

        $ip = parent::ipGet($source_slug, $v4_only, $headers);

        return $ip ?: parent::ipGet('remote_addr', $v4_only, $headers);
    }

    /**
     * Slug of the IP source selected in the "misc__get_ip" setting. Null for the automatic detection.
     *
     * @return string|null
     */
    public static function getSelectedIpSourceSlug()
    {
        global $apbct;

        if ( ! isset($apbct->settings['misc__get_ip']) ) {
            return null;
        }

        $source_id = TT::toInt($apbct->settings['misc__get_ip']);

        return isset(self::$known_ip_headers_collection[$source_id])
            ? self::$known_ip_headers_collection[$source_id]['slug']
            : null;
    }

    /**
     * Returns list of known CDN headers names.
     *
     * @return string[]
     */
    public static function getKnownCDNHeadersNames()
    {
        return array_map(function ($cdn_record_data) {
            return $cdn_record_data['name'];
        }, self::$known_ip_headers_collection);
    }

    /**
     * Returns IP source ID by slug.
     *
     * @param string $slug
     *
     * @return int|null
     */
    public static function getIpHeaderIDBySlug($slug = '')
    {
        foreach ( self::$known_ip_headers_collection as $header_id => $header_data ) {
            if ( $header_data['slug'] === $slug ) {
                return (int)$header_id;
            }
        }

        return null;
    }

    /**
     * Returns IP source slug by the header name.
     *
     * @param string $name
     *
     * @return string
     */
    public static function getIpHeaderSlugByName($name = '')
    {
        foreach ( self::$known_ip_headers_collection as $header_data ) {
            if ( strtolower($header_data['name']) === strtolower($name) ) {
                return $header_data['slug'];
            }
        }

        return '';
    }

    /**
     * Function sends raw http request
     *
     * May use 4 presets(combining possible):
     * get_code - getting only HTTP response code
     * async    - async requests
     * get      - GET-request
     *
     * @param string|array<string> $url URL
     * @param array|string|int $data POST|GET indexed array with data to send
     * @param string|array $presets String or Array with presets: get_code, async, get, ssl, dont_split_to_array
     * @param array $opts Optional option for CURL connection
     *
     * @return array|bool|string (array || array('error' => true))
     */
    public static function httpRequest($url, $data = array(), $presets = [], $opts = array())
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

        $value_for_token = '';
        if ( $apbct->api_key ) {
            $value_for_token = $apbct->api_key;
        } elseif ( apbct__is_hosting_license() ) {
            $value_for_token = $apbct->api_key . $apbct->data['salt'];
        }

        $request_params = array_merge(array(
            'spbc_remote_call_token' => md5($value_for_token),
            'spbc_remote_call_action' => $rc_action,
            'plugin_name' => 'apbct',
        ), $request_params);
        $patterns       = array_merge(
            array(
                'dont_split_to_array',
                'no_cache'
            ),
            $patterns
        );

        // Issue before RC test: test validates token without consuming; real call consumes it.
        if ($rc_action === 'sfw_update__worker') {
            $self_token = RemoteCalls::issueSfwUpdateWorkerSelfToken();
            if ($self_token !== '') {
                $request_params[RemoteCalls::SFW_WORKER_SELF_TOKEN_PARAM] = $self_token;
            }
        }

        if ($do_check) {
            $result__rc_check_website = static::httpRequestRcToHostTest($rc_action, $request_params, $patterns);
            if ( ! empty($result__rc_check_website['error'])) {
                // Drop unused token if connectivity probe failed.
                if ( ! empty($request_params[RemoteCalls::SFW_WORKER_SELF_TOKEN_PARAM]) ) {
                    RemoteCalls::consumeSfwUpdateWorkerSelfToken(
                        $request_params[RemoteCalls::SFW_WORKER_SELF_TOKEN_PARAM]
                    );
                }

                return $result__rc_check_website;
            }
        }

        return static::httpRequest(
            substr(get_option('home'), -1) === '/' ? get_option('home') : get_option('home') . '/',
            $request_params,
            $patterns
        );
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

        $request_params = array_merge($request_params, array('test' => 'test'));
        $url = substr(get_option('home'), -1) === '/'
            ? get_option('home')
            : get_option('home') . '/';

        $result = static::httpRequest(
            $url,
            $request_params,
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

            if (is_array($data) && !empty($data['error'])) {
                return array('error' => 'Getting datafile ' . $url . '. Error: ' . $data['error']);
            }

            if (is_string($data)) {
                if (static::getMimeType($data, 'application/x-gzip')) {
                    if (function_exists('gzdecode')) {
                        $data = gzdecode($data);

                        if ($data !== false) {
                            return $data;
                        } else {
                            return array('error' => 'Can not unpack datafile');
                        }
                    } else {
                        return array('error' => 'Function gzdecode not exists. Please update your PHP at least to version 5.4.');
                    }
                } else {
                    return array('error' => 'Wrong file mime type: ' . $url);
                }
            } else {
                return array('error' => 'Getting datafile ' . $url . '. Content is not a string');
            }
        } else {
            return array('error' => 'Bad HTTP response (' . TT::toString($response_code) . ') from file location: ' . $url);
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

    /**
     * Cast arrayObject to array.
     * @param $object - An ArrayObject
     * @return array
     */
    public static function arrayObjectToArray($object)
    {
        /** @psalm-suppress */
        if ( !is_object($object) && !is_array($object) ) {
            return $object;
        }

        if ( ( (is_array($object) || $object instanceof \Countable) ) && !@count($object) ) {
            return array();
        }

        $callable = PHP_VERSION_ID >= 80200 ? [self::class, 'arrayObjectToArray'] : 'self::arrayObjectToArray';
        return array_map($callable, (array)$object);
    }

    /**
     * Validate if arg is valid JSON string
     *
     * @param string $string
     *
     * @return bool
     * @psalm-suppress UnusedFunctionCall
     */
    public static function isJson($string)
    {
        try {
            $out = json_decode($string);
            unset($out);
            return (json_last_error() === JSON_ERROR_NONE);
        } catch (\Exception $e) {
            return false;
        }
    }
}
