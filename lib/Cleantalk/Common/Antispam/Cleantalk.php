<?php

namespace Cleantalk\Common\Antispam;

use Cleantalk\Common\Cleaner\Validate;
use Cleantalk\Common\Helper\Helper;
use Cleantalk\Common\Mloader\Mloader;

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
                $request->message = Helper::toUTF8($request->message, $this->data_codepage);
                $request->example = Helper::toUTF8($request->example, $this->data_codepage);
                $request->sender_email = Helper::toUTF8($request->sender_email, $this->data_codepage);
                $request->sender_nickname = Helper::toUTF8($request->sender_nickname, $this->data_codepage);
                $request->message = $this->compressData($request->message);
                $request->example = $this->compressData($request->example);
                break;

            case 'check_newuser':
                // Convert strings to UTF8
                $request->sender_email = Helper::toUTF8($request->sender_email, $this->data_codepage);
                $request->sender_nickname = Helper::toUTF8($request->sender_nickname, $this->data_codepage);
                break;

            case 'send_feedback':
                if ( is_array($request->feedback) ) {
                    $request->feedback = implode(';', $request->feedback);
                }
                break;
        }

        // Removing non UTF8 characters from request, because non UTF8 or malformed characters break json_encode().
        foreach ( $request as $param => $value ) {
            if ( is_array($request->$param) || is_string($request->$param) ) {
                $request->$param = Helper::removeNonUTF8($value);
            }
        }

        $request->method_name = $method;
        $request->message = is_array($request->message) ? json_encode($request->message) : $request->message;

        // Wiping cleantalk's headers but, not for send_feedback
        if ( $request->method_name !== 'send_feedback' ) {
            $ct_tmp = Helper::httpGetHeaders();

            if ( isset($ct_tmp['Cookie']) ) {
                $cookie_name = 'Cookie';
            } elseif ( isset($ct_tmp['cookie']) ) {
                $cookie_name = 'cookie';
            } else {
                $cookie_name = 'COOKIE';
            }

            if ( $ct_tmp ) {
                if ( isset($ct_tmp[$cookie_name]) ) {
                    $ct_tmp[$cookie_name] = preg_replace(array(
                        '/\s?ct_checkjs=[a-z0-9]*[^;]*;?/',
                        '/\s?ct_timezone=.{0,1}\d{1,2}[^;]*;?/',
                        '/\s?ct_pointer_data=.*5D[^;]*;?/',
                        '/\s?apbct_timestamp=\d*[^;]*;?/',
                        '/\s?apbct_site_landing_ts=\d*[^;]*;?/',
                        '/\s?apbct_cookies_test=%7B.*%7D[^;]*;?/',
                        '/\s?apbct_prev_referer=http.*?[^;]*;?/',
                        '/\s?ct_ps_timestamp=.*?[^;]*;?/',
                        '/\s?ct_fkp_timestamp=\d*?[^;]*;?/',
                        '/\s?wordpress_ct_sfw_pass_key=\d*?[^;]*;?/',
                        '/\s?apbct_page_hits=\d*?[^;]*;?/',
                        '/\s?apbct_visible_fields_count=\d*?[^;]*;?/',
                        '/\s?apbct_visible_fields=%7B.*%7D[^;]*;?/',
                        '/\s?apbct_visible_fields_\d=%7B.*%7D[^;]*;?/',
                    ), '', $ct_tmp[$cookie_name]);
                }
                $request->all_headers = json_encode($ct_tmp);
            }
        }

        return $request;
    }

    /**
     * Compress data and encode to base64
     *
     * @param string $data
     *
     * @return null|string
     */
    private function compressData($data = null)
    {
        if ( !is_string($data) ) {
            return $data;
        }

        if ( strlen($data) > $this->dataMaxSise && function_exists('\gzencode') && function_exists('base64_encode') ) {
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
        // Using current server without changing it
        $result = !empty($this->work_url) && $this->server_changed + 86400 > time()
            ? $this->sendRequest($msg, $this->work_url, $this->server_timeout)
            : false;

        // Changing server if no work_url or request has an error
        if ( $result === false || (is_object($result) && $result->errno != 0) ) {
            if ( !empty($this->work_url) ) {
                $this->downServers[] = $this->work_url;
            }
            $this->rotateModerate();
            $result = $this->sendRequest($msg, $this->work_url, $this->server_timeout);
            if ( $result !== false && $result->errno === 0 ) {
                $this->server_change = true;
            }
        }
        $response = new CleantalkResponse($result);

        if ( !empty($this->data_codepage) && $this->data_codepage !== 'UTF-8' ) {
            if ( !empty($response->comment) ) {
                $response->comment = Helper::fromUTF8($response->comment, $this->data_codepage);
            }
            if ( !empty($response->errstr) ) {
                $response->errstr = Helper::fromUTF8($response->errstr, $this->data_codepage);
            }
            if ( !empty($response->sms_error_text) ) {
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
        $url_host = isset($matches[2]) ? $matches[2] : '';
        $url_suffix = isset($matches[3]) ? $matches[3] : '';

        $servers = $this->getServersIp($url_host);

        if ( !$servers ) {
            return;
        }

        // Loop until find work server
        foreach ( $servers as $server ) {
            $dns = Helper::ipResolveCleantalks($server['ip']);
            if ( !$dns ) {
                continue;
            }

            $this->work_url = $url_protocol . $dns . $url_suffix;

            // Do not checking previous down server
            if ( !empty($this->downServers) && in_array($this->work_url, $this->downServers) ) {
                continue;
            }

            $this->server_ttl = $server['ttl'];
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
        if ( !isset($host) ) {
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
                        "ip" => $server,
                        "host" => $host,
                        "ttl" => $this->server_ttl
                    );
                }
            }
        }

        // If couldn't get records
        if ( count($servers) === 0 ) {
            $servers[] = array(
                "ip" => null,
                "host" => $host,
                "ttl" => $this->server_ttl
            );
            // If records received
        } else {
            $tmp = array();
            $fast_server_found = false;

            foreach ( $servers as $server ) {
                if ( $fast_server_found ) {
                    $ping = $this->max_server_timeout;
                } else {
                    $ping = $this->httpPing($server['ip']);
                    $ping *= 1000;
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
        $file = @fsockopen($host, 443, $errno, $errstr, $this->max_server_timeout / 1000);
        $stoptime = microtime(true);

        if ( !$file ) {
            $status = $this->max_server_timeout / 1000;  // Site is down
        } else {
            fclose($file);
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
     */
    private function sendRequest($data, $url, $server_timeout = 3)
    {
        //Cleaning from 'null' values
        $tmp_data = array();
        foreach ( $data as $key => $value ) {
            if ( $value !== null ) {
                $tmp_data[$key] = $value;
            }
        }
        $data = $tmp_data;
        unset($key, $value, $tmp_data);

        $js_on = ( isset($data['js_on']) && (int)$data['js_on'] === 1 ) ||
            ( isset($data['event_token']) && Validate::isHash($data['event_token']) );

        // Convert to JSON
        $data = json_encode($data);

        if ( isset($this->api_version) ) {
            $url .= $this->api_version;
        }

        /** @var \Cleantalk\Common\Http\Request $request_class */
        $request_class = Mloader::get('Http\Request');
        $http = new $request_class();

        $result = $http->setUrl($url)
            ->setData($data)
            ->setOptions(['timeout' => $server_timeout])
            ->request();

        $errstr = null;
        $response = is_string($result) ? json_decode($result) : false;
        if ( $result !== false && is_object($response) ) {
            $response->errno = 0;
            $response->errstr = $errstr;
        } else {
            if ( isset($result['error']) ) {
                $error = $result['error'];
            } else {
                if ( is_string($result) ) {
                    $error = $result;
                } else {
                    $error = '';
                }
            }

            $errstr = 'Unknown response from ' . $url . ': ' . $error;

            $response = null;
            $response['errno'] = 1;
            $response['errstr'] = $errstr;

            if ( ! $js_on ) {
                $response['allow']   = 0;
                $response['spam']    = '1';
                $response['comment'] = sprintf(
                    'We\'ve got an issue: %s. Forbidden. Please, enable Javascript.',
                    $errstr
                );
            }
            $response = json_decode(json_encode($response));
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
     */
    public function checkBot(CleantalkRequest $request)
    {
        $msg = $this->createMsg('check_bot', $request);

        return $this->httpRequest($msg);
    }

    public static function getLockPageFile()
    {
        return __DIR__ . '/lock-page-ct-die.html';
    }
}
