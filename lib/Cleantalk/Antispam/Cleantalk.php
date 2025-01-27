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
class Cleantalk
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
     * Time work_url changed
     * @var int
     */
    public $server_changed;

    /**
     * Flag is change server url
     * @var bool
     */
    public $server_change = false;

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
     * Minimal server response in milliseconds to catch the server
     * @var int
     */
    public $min_server_timeout = 50;

    /**
     * Maximal server response in milliseconds to catch the server
     * @var int
     */
    public $max_server_timeout = 1500;

    /**
     * List of the down servers.
     * Non responsible moderate servers list
     *
     * @var array
     */
    private $downServers;

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
        $failed_urls = null;
        // Using current server without changing it
        $result = ! empty($this->work_url) && $this->server_changed + 86400 > time()
            ? $this->sendRequest($msg, $this->work_url, $this->server_timeout)
            : false;

        // Changing server if no work_url or request has an error
        $number_of_connection_attempts = 2;
        $attempt = 1;

        while (($result === false || (is_object($result) && $result->errno != 0)) && $attempt <= $number_of_connection_attempts) {
            // Getting type of error
            $type_error = $this->getTypeError($result);

            $failed_urls = $this->work_url;
            if ( ! empty($this->work_url) ) {
                $this->downServers[] = $this->work_url;
            }

            if ( ($type_error === 'getaddrinfo_error' || $type_error === 'connection_timeout') && $attempt === 1 ) {
                $this->rotateModerateAndUseIP();
                //exit if next sendRequest failed, because change dns->ip is only way to fix errors above
                $attempt = $attempt + 2;
            } else {
                $this->rotateModerate();
                //try change server again if next sendRequest failed
                $attempt = $attempt + 1;
            }

            $result = $this->sendRequest($msg, $this->work_url, $this->server_timeout);
            /** @psalm-suppress PossiblyInvalidPropertyFetch */
            if ( $result !== false && $result->errno === 0 ) {
                $this->server_change = true;
                break;
            }

            $failed_urls .= ', ' . $this->work_url;
        }
        /** @psalm-suppress PossiblyInvalidArgument */
        $response = new CleantalkResponse($result, $failed_urls);

        if ( ! empty($this->data_codepage) && $this->data_codepage !== 'UTF-8' ) {
            if ( ! empty($response->comment) ) {
                $response->comment = Helper::fromUTF8($response->comment, $this->data_codepage);
            }
            if ( ! empty($response->errstr) ) {
                $response->errstr = Helper::fromUTF8($response->errstr, $this->data_codepage);
            }
            if ( ! empty($response->sms_error_text) ) {
                $response->sms_error_text = Helper::fromUTF8($response->sms_error_text, $this->data_codepage);
            }
        }

        return $response;
    }

    /**
     * * @todo Refactor / fix logic errors
     */
    public function rotateModerate()
    {
        // Split server url to parts
        preg_match("/^(https?:\/\/)([^\/:]+)(.*)/i", $this->server_url, $matches);

        $url_protocol = isset($matches[1]) ? $matches[1] : '';
        $url_host     = isset($matches[2]) ? $matches[2] : '';
        $url_suffix   = isset($matches[3]) ? $matches[3] : '';

        $servers = $this->getServersIp($url_host);

        if ( ! $servers ) {
            return;
        }

        // Loop until find work server
        foreach ( $servers as $server ) {
            $dns = Helper::ipResolve($server['ip']);
            if ( ! $dns ) {
                continue;
            }

            $this->work_url = $url_protocol . $dns . $url_suffix;

            // Do not checking previous down server
            if ( ! empty($this->downServers) && in_array($this->work_url, $this->downServers) ) {
                continue;
            }

            $this->server_ttl    = $server['ttl'];
            $this->server_change = true;
            break;
        }
    }

    /**
     * * @todo Refactor / fix logic errors
     */
    public function rotateModerateAndUseIP()
    {
        // Split server url to parts
        global $apbct;
        preg_match("/^(https?:\/\/)([^\/:]+)(.*)/i", $this->server_url, $matches);

        $url_protocol = isset($matches[1]) ? $matches[1] : '';
        $url_host     = isset($matches[2]) ? $matches[2] : '';
        $url_suffix   = isset($matches[3]) ? $matches[3] : '';

        $servers = $this->getServersIp($url_host);

        if ( ! $servers ) {
            return;
        }

        $apbct->settings['wp__use_builtin_http_api'] = false;

        // Loop until find work server
        foreach ( $servers as $server ) {
            $dns = Helper::ipResolve($server['ip']);
            if ( ! $dns ) {
                continue;
            }

            $this->work_url = $url_protocol . $server['ip'] . $url_suffix;

            // Do not checking previous down server
            if ( ! empty($this->downServers) && in_array($this->work_url, $this->downServers) ) {
                continue;
            }

            $this->server_ttl    = $server['ttl'];
            $this->server_change = true;
            break;
        }
    }

    /**
     * @param $host
     * @return array|null
     * @todo Refactor / fix logic errors
     *
     * Function DNS request
     *
     * @psalm-suppress RedundantCondition
     */
    public function getServersIp($host)
    {
        if ( ! isset($host) ) {
            return null;
        }

        $servers = array();

        // Get DNS records about URL
        if ( function_exists('dns_get_record') ) {
            $records = @dns_get_record($host, DNS_A);
            if ( $records !== false ) {
                foreach ( $records as $server ) {
                    $servers[] = $server;
                }
            }
        }

        // Another try if first failed
        if ( count($servers) === 0 && function_exists('gethostbynamel') ) {
            $records = gethostbynamel($host);
            if ( $records !== false ) {
                foreach ( $records as $server ) {
                    $servers[] = array(
                        "ip"   => $server,
                        "host" => $host,
                        "ttl"  => $this->server_ttl
                    );
                }
            }
        }

        // If couldn't get records
        if ( count($servers) === 0 ) {
            $servers[] = array(
                "ip"   => null,
                "host" => $host,
                "ttl"  => $this->server_ttl
            );
            // If records received
        } else {
            $tmp               = array();
            $fast_server_found = false;

            foreach ( $servers as $server ) {
                $ping = '';
                if ( $fast_server_found ) {
                    $ping = $this->max_server_timeout;
                } else {
                    if (array_key_exists('ip', $server)) {
                        $ping = $this->httpPing($server['ip']);
                        $ping *= 1000;
                    }
                }

                $tmp[(int)$ping] = $server;

                $fast_server_found = $ping < $this->min_server_timeout;
            }

            if ( count($tmp) ) {
                ksort($tmp);
                $response = $tmp;
            }
        }

        return empty($response) ? null : $response;
    }

    /**
     * Function to check response time
     *
     * @param string $host
     *
     * @return float|int
     */
    public function httpPing($host)
    {
        // Skip localhost ping cause it raise error at fsockopen.
        // And return minimum value
        if ( $host === 'localhost' ) {
            return 0.001;
        }

        $starttime = microtime(true);
        if ( function_exists('fsockopen') ) {
            $file = @fsockopen($host, 443, $errno, $errstr, $this->max_server_timeout / 1000);
        } else {
            $http = new Request();
            $host = 'https://' . gethostbyaddr($host);
            $file = $http->setUrl($host)
                ->setOptions(['timeout' => $this->max_server_timeout / 1000])
                ->setPresets('get_code get')
                ->request();
            if ( !empty($file['error']) || $file !== '200' ) {
                $file = false;
            }
        }
        $stoptime = microtime(true);

        if ( !$file ) {
            $status = $this->max_server_timeout / 1000;  // Site is down
        } else {
            if ( function_exists('fsockopen') ) {
                fclose($file);
            }
            $status = ($stoptime - $starttime);
            $status = round($status, 4);
        }

        return $status;
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
