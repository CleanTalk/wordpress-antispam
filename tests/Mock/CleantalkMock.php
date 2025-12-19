<?php

namespace Cleantalk\Antispam;

use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\HTTP\Request;
use Cleantalk\Common\DNS;

/**
 * Cleantalk base class
 *
 * @version 2.2
 * @package Cleantalk
 * @subpackage Base
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see https://github.com/CleanTalk/php-antispam
 *
 */
class CleantalkMock
{
    /**
     * Maximum data size in bytes
     * @var int
     */
    private $dataMaxSise = 32768;

    /**
     * Data compression rate
     * @var int
     */
    private $compressRate = 6;

    /**
     * Server connection timeout in seconds
     * @var int
     */
    private $server_timeout = 15;

    /**
     * Cleantalk server url
     * @var string
     */
    public $server_url;

    /**
     * Last work url
     * @var string
     */
    public $work_url;

    /**
     * Work url ttl
     * @var int
     */
    public $server_ttl;

    /**
     * Codepage of the data
     * @var string
     */
    public $data_codepage = '';

    /**
     * API version to use
     * @var string
     */
    public $api_version = '/api2.0';

    /**
     * @var string
     */
    public $method_uri = '';

    /**
     * Function checks whether it is possible to publish the message
     *
     * @param CleantalkRequest $request
     *
     * @return bool|CleantalkResponse
     */
    public function isAllowMessage(CleantalkRequest $request)
    {
        $msg = $this->createMsg('check_message', $request);

        return $this->httpRequest($msg);
    }

    /**
     * Function checks whether it is possible to publish the message
     *
     * @param CleantalkRequest $request
     *
     * @return bool|CleantalkResponse
     */
    public function isAllowUser(CleantalkRequest $request)
    {
        $msg = $this->createMsg('check_newuser', $request);

        return $this->httpRequest($msg);
    }

    /**
     * Function sends the results of manual moderation
     *
     * @param CleantalkRequest $request
     *
     * @return bool|CleantalkResponse
     */
    public function sendFeedback(CleantalkRequest $request)
    {
        $msg = $this->createMsg('send_feedback', $request);

        return $this->httpRequest($msg);
    }

    /**
     * Create msg for cleantalk server
     *
     * @param string $method
     * @param CleantalkRequest $request
     *
     * @return CleantalkRequest
     */
    private function createMsg($method, CleantalkRequest $request)
    {
        switch ( $method ) {
            case 'check_message':
                // Convert strings to UTF8
                $request->message         = $request->message != null ? Helper::toUTF8($request->message, $this->data_codepage) : '';
                $request->example         = $request->example != null ? Helper::toUTF8($request->example, $this->data_codepage) : '';
                $request->sender_email    = Helper::toUTF8($request->sender_email, $this->data_codepage);
                $request->sender_nickname = Helper::toUTF8($request->sender_nickname, $this->data_codepage);
                $request->message         = $request->message != null && is_string($request->message) ? $this->compressData($request->message) : '';
                $request->example         = $request->example != null && is_string($request->example) ? $this->compressData($request->example) : '';
                break;

            case 'check_newuser':
                // Convert strings to UTF8
                $request->sender_email    = Helper::toUTF8($request->sender_email, $this->data_codepage);
                $request->sender_nickname = Helper::toUTF8($request->sender_nickname, $this->data_codepage);
                break;

            case 'send_feedback':
                if ( is_array($request->feedback) ) {
                    $request->feedback = implode(';', $request->feedback);
                }
                break;

            case 'check_bot':
                $request->message_to_log   = $this->compressData($request->message_to_log);
                break;
        }

        // Removing non UTF8 characters from request, because non UTF8 or malformed characters break json_encode().
        foreach ( $request as $param => $value ) {
            if ( is_array($request->$param) || is_string($request->$param) ) {
                $request->$param = Helper::removeNonUTF8($value);
            }
        }

        $request->method_name = $method;
        $request->message     = is_array($request->message) ? json_encode($request->message) : $request->message;

        // Wiping session cookies from request
        $ct_tmp = apache_request_headers();

        if (isset($ct_tmp['Cookie'])) {
            $cookie_name = 'Cookie';
        } elseif (isset($ct_tmp['cookie'])) {
            $cookie_name = 'cookie';
        } else {
            $cookie_name = 'COOKIE';
        }

        if (isset($ct_tmp[$cookie_name])) {
            unset($ct_tmp[$cookie_name]);
        }

        $request->all_headers = !empty($ct_tmp) ? json_encode($ct_tmp) : '';

        return $request;
    }

    /**
     * Compress data and encode to base64
     *
     * @param string|null $data
     *
     * @return null|string
     */
    private function compressData($data = null)
    {
        if ( $data != null && strlen($data) > $this->dataMaxSise && function_exists('\gzencode') && function_exists('base64_encode') ) {
            $localData = \gzencode($data, $this->compressRate, FORCE_GZIP);

            if ( $localData === false ) {
                return $data;
            }

            return base64_encode($localData);
        }

        return $data;
    }

    /**
     * httpRequest
     *
     * @param $msg
     *
     * @return CleantalkResponse
     */
    private function httpRequest($msg)
    {
        var_dump('httpRecuest', $msg->method_name);

        switch ($msg->method_name) {
            case 'check_message':
                $response = new \stdClass();
                $response->stop_words = 'stop_word';
                $response->stop_word = 'stop_word';
                $response->allow = 0;
                return $response;
        }

        return '$response';
    }

    /**
     * Send JSON request to servers
     *
     * @param string|array $data
     * @param string $url
     * @param int $server_timeout
     *
     * @return boolean|CleantalkResponse
     * @throws \Exception
     */
    private function sendRequest($data, $url, $server_timeout = 3)
    {
        //Cleaning from 'null' values
        $tmp_data = array();
        /** @psalm-suppress PossiblyInvalidIterator */
        foreach ( $data as $key => $value ) {
            if ( $value !== null ) {
                $tmp_data[$key] = $value;
            }
        }
        $data = $tmp_data;
        unset($key, $value, $tmp_data);

        // Convert to JSON
        $data = json_encode($data);

        if ( isset($this->api_version) ) {
            $url .= $this->api_version;
        }

        $http = new Request();

        $presets = array();

        if (!empty($this->api_version) && $this->api_version === '/api3.0') {
            if (empty($this->method_uri) || !is_string($this->method_uri)) {
                throw new \Exception('CleanTalk: API method of version 3.0 should have specified method URI');
            }
            //set special preset for /api3.0
            $presets[] = 'api3.0';
            //add method uri if provided
        }

        //common way - left this if we need to specify method uri for 2.0
        $url = !empty($this->method_uri) && is_string($this->method_uri)
            ? $url . '/' . $this->method_uri
            : $url;

        $result = $http->setUrl($url)
                       ->setData($data)
                       ->setPresets($presets)
                       ->setOptions(['timeout' => $server_timeout])
                       ->request();

        $errstr   = null;
        $response = is_string($result) ? json_decode($result) : false;
        if ( $result !== false && is_object($response) ) {
            $response->errno  = 0;
            $response->errstr = $errstr;
        } else {
            if ( isset($result['error']) ) {
                $error = $result['error'];
            } else if ( is_string($result) ) {
                $error = $result;
            } else {
                $error = '';
            }

            $errstr = 'Unknown response from ' . $url . ': ' . $error;

            $response           = null;
            $response['errno']  = 1;
            $response['errstr'] = $errstr;
            $response           = json_decode(json_encode($response));
        }

        return $response;
    }

     /**
     * Call check_bot API method
     *
     * Make a decision if it's bot or not based on limited input JavaScript data
     *
     * @param CleantalkRequest $request
     *
     * @return CleantalkResponse
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function checkBot(CleantalkRequest $request)
    {
        $msg = $this->createMsg('check_bot', $request);

        return $this->httpRequest($msg);
    }

    private function getTypeError($result)
    {
        if (isset($result->errstr)) {
            switch ($result->errstr) {
                case strpos($result->errstr, 'cURL error 28: Operation timed out after') !== false:
                    return 'connection_timeout';
                case strpos($result->errstr, 'getaddrinfo() thread failed to start') !== false:
                    return 'getaddrinfo_error';
                default:
                    return 'unknown';
            }
        }

        return 'unknown';
    }
}
