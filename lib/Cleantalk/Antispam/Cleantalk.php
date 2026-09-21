<?php

namespace Cleantalk\Antispam;

use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\HTTP\Request;
use Cleantalk\Common\DNS;
use Cleantalk\Common\TT;

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
     * IP of the node the work url is pinned to.
     *
     * The URL always carries the pool hostname, so it alone does not identify a node.
     * This IP is what actually selects the server, via CURLOPT_RESOLVE.
     *
     * @var string|null
     */
    public $work_ip;

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
     * IPs already picked by the IP fallback during the current request cycle.
     * The pool hostname is shared by every node, so only the IP identifies a server there.
     *
     * @var array
     */
    private $down_ips = array();

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
    public function createMsg($method, CleantalkRequest $request)
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
    public function httpRequest($msg)
    {
        $failed_urls = null;
        // Using current server without changing it.
        // work_ip pins the node the URL was resolved to last time, see selectModerateNode().
        $result = ! empty($this->work_url) && $this->server_changed + 86400 > time()
            ? $this->sendRequest($msg, $this->work_url, $this->server_timeout, $this->work_ip)
            : false;

        // Changing server if no work_url or request has an error
        $number_of_connection_attempts = 2;
        $attempt = 1;

        while (($result === false || (is_object($result) && $result->errno != 0)) && $attempt <= $number_of_connection_attempts) {
            // Getting type of error
            $type_error = $this->getTypeError($result);

            // The URL always carries the pool hostname, so the node that just failed
            // is identified by its IP. Keep it out of the next rotation.
            if ( ! empty($this->work_ip) && ! in_array($this->work_ip, $this->down_ips, true) ) {
                $this->down_ips[] = $this->work_ip;
            }

            $failed_urls = $this->describeWorkServer();
            if ( ! empty($this->work_url) ) {
                $this->downServers[] = $this->work_url;
            }

            if ( ($type_error === 'getaddrinfo_error' || $type_error === 'connection_timeout') && $attempt === 1 ) {
                $ip_to_resolve = $this->rotateModerateAndUseIP();
                // Exit if next sendRequest failed, because bypassing DNS is the last retry.
                $attempt = $attempt + 2;
            } else {
                $ip_to_resolve = $this->rotateModerate();
                //try change server again if next sendRequest failed
                $attempt = $attempt + 1;
            }

            $result = $this->sendRequest($msg, $this->work_url, $this->server_timeout, $ip_to_resolve);
            /** @psalm-suppress PossiblyInvalidPropertyFetch */
            if ( $result !== false && $result->errno === 0 ) {
                $this->server_change = true;
                break;
            }

            $failed_urls .= ', ' . $this->describeWorkServer();
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
     * Selects the next moderate node that has not failed yet in the current cycle.
     *
     * The URL always keeps the pool hostname from server_url. It is the only name
     * known to match the API certificate, and deriving it from DNS (a PTR record)
     * would let a poisoned resolver choose the very name TLS is validated against.
     * The node is selected by IP instead and has to be pinned with CURLOPT_RESOLVE.
     *
     * getServersIp() returns the candidates sorted by ping, so the first usable
     * entry is the closest responding node.
     *
     * @return string|false Selected node IP, false when no candidate is left.
     */
    private function selectModerateNode()
    {
        // Split server url to parts
        preg_match("/^(https?:\/\/)([^\/:]+)(.*)/i", $this->server_url, $matches);

        $url_protocol = isset($matches[1]) ? $matches[1] : '';
        $url_host     = isset($matches[2]) ? $matches[2] : '';
        $url_suffix   = isset($matches[3]) ? $matches[3] : '';

        $servers = $this->getServersIp($url_host);

        if ( ! $servers ) {
            return false;
        }

        // Loop until find work server
        foreach ( $servers as $server ) {
            $ip = TT::getArrayValueAsString($server, 'ip');

            // The pool hostname is shared by every node, so only the IP identifies one.
            // Skipping by URL here would discard all of the candidates at once.
            if ( empty($ip) || in_array($ip, $this->down_ips, true) ) {
                continue;
            }

            $this->work_url      = $url_protocol . $url_host . $url_suffix;
            $this->work_ip       = $ip;
            $this->down_ips[]    = $ip;
            $this->server_ttl    = TT::getArrayValueAsInt($server, 'ttl');
            $this->server_change = true;

            return $ip;
        }

        return false;
    }

    /**
     * Renders the current server for connection reports.
     *
     * Every node now shares the pool hostname, so the URL alone no longer tells
     * which server was contacted. The pinned IP is appended to keep the report
     * able to answer that.
     *
     * @return string
     */
    private function describeWorkServer()
    {
        if ( empty($this->work_url) ) {
            return '';
        }

        return empty($this->work_ip)
            ? $this->work_url
            : $this->work_url . ' (' . $this->work_ip . ')';
    }

    /**
     * Rotates to the closest responding moderate node.
     *
     * @return string|false Selected node IP, false when no candidate is left.
     */
    public function rotateModerate()
    {
        return $this->selectModerateNode();
    }

    /**
     * Rotates to the closest responding moderate node when DNS for the pool
     * hostname is unusable. Identical to rotateModerate(), kept as a separate
     * entry point because the caller treats this as the last retry.
     *
     * @return string|false Selected node IP, false when no candidate is left.
     */
    public function rotateModerateAndUseIP()
    {
        return $this->selectModerateNode();
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
            $verified_host = Helper::ipResolve($host);
            $host = 'https://' . ($verified_host ?: $host);
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
     * @param string|false $ip_to_resolve IP address to use for the URL hostname without changing the URL.
     *
     * @return boolean|CleantalkResponse
     * @throws \Exception
     */
    private function sendRequest($data, $url, $server_timeout = 3, $ip_to_resolve = false)
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

        $options = array('timeout' => $server_timeout);
        $connection_resolve_string = $ip_to_resolve
            ? $this->maybeResolveIPInsteadOfHost($url, $ip_to_resolve)
            : false;

        if ($connection_resolve_string && defined('CURLOPT_RESOLVE')) {
            $options[CURLOPT_RESOLVE] = array($connection_resolve_string);
        }

        // CURLOPT_RESOLVE is a cURL option, the WordPress HTTP API silently drops it.
        // Without it the hostname would be resolved by DNS again and the node
        // selection would be lost, so force the cURL transport for pinned requests.
        $http_api_state = $this->disableBuiltInHttpApi(isset($options[CURLOPT_RESOLVE]));

        $result = $http->setUrl($url)
                       ->setData($data)
                       ->setPresets($presets)
                       ->setOptions($options)
                       ->request();

        $this->restoreBuiltInHttpApi($http_api_state);

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
     * @param string $url
     * @param string $ip_to_resolve
     * @return false|string
     */
    public function maybeResolveIPInsteadOfHost(string $url, string $ip_to_resolve)
    {
        if (
            filter_var($ip_to_resolve, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ) {
            $url_parts = parse_url($url);
            $host = TT::getArrayValueAsString($url_parts, 'host');
            $port = TT::getArrayValueAsInt($url_parts, 'port');
            $is_ssl = TT::getArrayValueAsString($url_parts, 'scheme') !== 'http';
            if (!empty($host)) {
                // URL may omit the port, then it is defined by the scheme
                $port = !empty($port) ? $port : ($is_ssl ? 443 : 80);
                return $host . ':' . $port . ':' . $ip_to_resolve;
            }
        }
        return false;
    }

    /**
     * Forces the cURL transport when a request has to be pinned to an IP.
     *
     * @param bool $needed
     *
     * @return bool|null Previous setting value, null when nothing was changed.
     */
    private function disableBuiltInHttpApi($needed)
    {
        global $apbct;

        if ( ! $needed || ! isset($apbct->settings['wp__use_builtin_http_api']) ) {
            return null;
        }

        $previous = $apbct->settings['wp__use_builtin_http_api'];
        $apbct->settings['wp__use_builtin_http_api'] = false;

        return $previous;
    }

    /**
     * @param bool|null $previous Value returned by disableBuiltInHttpApi().
     *
     * @return void
     */
    private function restoreBuiltInHttpApi($previous)
    {
        global $apbct;

        if ( $previous === null || ! isset($apbct->settings) ) {
            return;
        }

        $apbct->settings['wp__use_builtin_http_api'] = $previous;
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
