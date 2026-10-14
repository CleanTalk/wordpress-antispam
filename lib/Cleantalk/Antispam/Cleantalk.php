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
     * Last work url.
     *
     * NULL means "no usable cached server", which makes httpRequest() start with
     * a rotation instead of reusing the previous target.
     *
     * @var string|null
     */
    public $work_url;

    /**
     * Work url ttl
     * @var int|null
     */
    public $server_ttl;

    /**
     * Time work_url changed
     * @var int|null
     */
    public $server_changed;

    /**
     * A server was CONFIRMED working: a rotation happened and the retry on the new
     * target came back with errno 0.
     *
     * This is the only state worth caching. Set exclusively by httpRequest() after a
     * successful response -- never by the rotation methods, which cannot know yet
     * whether the server they picked actually answers.
     *
     * @var bool
     */
    public $server_change = false;

    /**
     * The in-memory server state no longer matches the stored record, but nothing
     * has been confirmed working.
     *
     * Set by the rotation methods whenever they touch $work_url or $dns_resolve_ip.
     * Keeping the two flags apart is what stops a dead server from being cached for
     * a day, and what stops a stale pinned IP from outliving the rotation that
     * cleared it. See ModerateServerConfig::save() for how each flag is handled.
     *
     * @var bool
     */
    public $server_state_dirty = false;

    /**
     * IP address the moderate hostname must be pinned to via CURLOPT_RESOLVE.
     *
     * Set ONLY by rotateModerateWithDnsOverride(), i.e. only when cURL's own
     * resolver is broken ('getaddrinfo() thread failed to start'). The hostname
     * stays in $work_url, so the '*.cleantalk.org' certificate still validates --
     * never put this IP into the URL itself.
     *
     * NULL means "resolve normally", which is the default and the state we fall
     * back to on every rotation.
     *
     * @var string|null
     */
    public $dns_resolve_ip;

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

            $failed_urls = $this->describeCurrentTarget();
            if ( ! empty($this->work_url) ) {
                $this->downServers[] = $this->getCurrentTargetKey();
            }

            // Pick a recovery strategy that actually matches the failure.
            //
            // 'getaddrinfo_error' means cURL itself cannot resolve ANY hostname, so
            // moving to another moderate host would hit the very same wall. The only
            // cure is to feed cURL a ready IP while keeping the hostname in the URL.
            //
            // Everything else ('connection_timeout', 'unknown') is a per-server
            // problem: plain rotation to another moderate host fixes it and needs no
            // IP pinning at all.
            if ( $type_error === 'getaddrinfo_error' ) {
                $this->rotateModerateWithDnsOverride();
            } else {
                $this->rotateModerate();
            }
            $attempt++;

            // Rotation can legitimately come back empty (no DNS records, every
            // candidate already marked as down). There is no URL left to try, so
            // stop instead of calling sendRequest() with a null URL.
            if ( empty($this->work_url) ) {
                $result = false;
                break;
            }

            $result = $this->sendRequest($msg, $this->work_url, $this->server_timeout);
            /** @psalm-suppress PossiblyInvalidPropertyFetch */
            if ( $result !== false && $result->errno === 0 ) {
                $this->server_change = true;
                break;
            }

            $failed_urls .= ', ' . $this->describeCurrentTarget();
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
     * Plain moderate rotation: move to another moderate host by its hostname.
     *
     * FIRES ON: 'connection_timeout' (cURL error 28) and 'unknown' errors, i.e.
     * when DNS works fine but the current moderate server is slow or down.
     *
     * HOW: resolves 'moderate.cleantalk.org' to its A records, then maps each IP
     * back to a verified PTR hostname ('moderate3.cleantalk.org') and uses THAT
     * hostname in $work_url. The hostname is covered by the '*.cleantalk.org'
     * certificate, so TLS verification keeps working.
     *
     * DOES NOT set $dns_resolve_ip -- it explicitly clears it and raises
     * $server_state_dirty, so a previously pinned IP never outlives a rotation even
     * if no replacement server is found. The one exception is an early return on a
     * dead resolver (getServersIp() empty): there is nothing to rotate to, and the
     * pin is then the only remaining way to reach a server, so it is left alone and
     * expires through the TTL instead.
     *
     * DOES NOT set $server_change: picking a candidate is not proof it answers.
     * Only httpRequest() raises that flag, after a successful retry.
     *
     * REQUIRES: a working PHP-level resolver (dns_get_record / gethostbyaddr).
     * It cannot repair 'getaddrinfo_error', because cURL would still have to
     * resolve the new hostname through the very same broken resolver --
     * see rotateModerateWithDnsOverride() for that case.
     *
     * @return void
     *
     * @todo Refactor / fix logic errors
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

        // Back to normal name resolution: this strategy never pins an IP.
        // Dropping the pin is itself a state change that must reach the option,
        // otherwise a stale IP would be restored on the next request.
        $this->dns_resolve_ip     = null;
        $this->server_state_dirty = true;

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

            $this->server_ttl = $server['ttl'];
            break;
        }
    }

    /**
     * Moderate rotation with a DNS override: keep the hostname, pin the IP.
     *
     * FIRES ON: 'getaddrinfo_error' ONLY ('getaddrinfo() thread failed to start'),
     * i.e. when cURL's own resolver is broken while PHP's resolver still answers.
     * Plain rotation cannot help there, because any new hostname would be resolved
     * through the same broken path.
     *
     * HOW: $work_url keeps the ORIGINAL hostname ('https://moderate.cleantalk.org')
     * and the chosen IP goes into $dns_resolve_ip, which sendRequest() turns into a
     * CURLOPT_RESOLVE entry. cURL then skips getaddrinfo() but still presents the
     * hostname for SNI and certificate verification.
     *
     * WHY NOT AN IP IN THE URL: the CleanTalk certificate only carries
     * 'CN=*.cleantalk.org' / 'SAN: *.cleantalk.org, cleantalk.org' and no IP SANs,
     * so 'https://88.198.153.60/' fails with cURL error 60. $work_url is also reused
     * for the browser-facing pixel URL, which would expose that error to visitors.
     *
     * SIDE EFFECT: none here. The cURL transport is forced by sendRequest(), which
     * is the only place that knows a resolve override is actually being applied --
     * including on later requests that restore the pinned IP from the option.
     *
     * FALLBACK: if cURL or CURLOPT_RESOLVE is unavailable (cURL < 7.21.3), delegates
     * to rotateModerate().
     *
     * RECOVERY: the pinned IP lives in the 'cleantalk_server' option next to
     * $work_url and dies with it -- on TTL expiry, on the daily rotate_moderate cron,
     * or on any later rotateModerate(), which clears the pin and marks the state
     * dirty so the clearing actually reaches the option even when the rotation
     * itself finds nothing usable.
     *
     * @return void
     *
     * @todo Refactor / fix logic errors
     */
    public function rotateModerateWithDnsOverride()
    {
        // No cURL, no CURLOPT_RESOLVE - nothing to override with.
        if ( ! function_exists('curl_init') || ! defined('CURLOPT_RESOLVE') ) {
            $this->rotateModerate();

            return;
        }

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
            if ( empty($server['ip']) ) {
                continue;
            }

            // Hostname stays intact - only the resolution step is overridden.
            $this->work_url           = $url_protocol . $url_host . $url_suffix;
            $this->dns_resolve_ip     = $server['ip'];
            $this->server_state_dirty = true;

            // Do not checking previous down server
            if ( ! empty($this->downServers) && in_array($this->getCurrentTargetKey(), $this->downServers) ) {
                continue;
            }

            $this->server_ttl = $server['ttl'];
            break;
        }
    }

    /**
     * Identifier of the current target used to mark it as down.
     *
     * With a DNS override $work_url is identical for every candidate, so the pinned
     * IP has to be part of the key -- otherwise the first failed IP would blacklist
     * all the others.
     *
     * @return string
     */
    private function getCurrentTargetKey()
    {
        return empty($this->dns_resolve_ip)
            ? (string)$this->work_url
            : $this->work_url . '#' . $this->dns_resolve_ip;
    }

    /**
     * Human readable description of the current target for connection reports.
     *
     * @return string
     */
    private function describeCurrentTarget()
    {
        return empty($this->dns_resolve_ip)
            ? (string)$this->work_url
            : $this->work_url . ' (resolved to ' . $this->dns_resolve_ip . ')';
    }

    /**
     * Build the 'resolve' option (CURLOPT_RESOLVE format) for the given URL.
     *
     * Returns NULL when no override is active, which is the normal case -- the
     * request then goes out with ordinary name resolution and no cURL-specific
     * options at all.
     *
     * @param string $url
     *
     * @return array|null array('host:port:ip') or null
     */
    private function buildResolveOption($url)
    {
        if ( empty($this->dns_resolve_ip) || ! is_string($url) ) {
            return null;
        }

        $parsed = parse_url($url);

        if ( empty($parsed['host']) ) {
            return null;
        }

        $port = isset($parsed['port'])
            ? TT::getArrayValueAsString($parsed, 'port')
            : (isset($parsed['scheme']) && $parsed['scheme'] === 'http' ? '80' : '443');

        return array($parsed['host'] . ':' . $port . ':' . $this->dns_resolve_ip);
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
     *
     * @return boolean|CleantalkResponse
     * @throws \Exception
     */
    private function sendRequest($data, $url, $server_timeout = 3)
    {
        global $apbct;

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

        $options = ['timeout' => $server_timeout];

        // Only present while a DNS override is active (getaddrinfo_error recovery).
        // Without it the request goes out with plain name resolution.
        $resolve = $this->buildResolveOption($url);
        if ( $resolve !== null ) {
            $options['resolve'] = $resolve;

            // CURLOPT_RESOLVE exists only in the cURL transport. The WP HTTP API may
            // pick fsockopen/streams instead and would silently drop the override,
            // sending the request through the very resolver we are working around.
            // Forced here rather than at rotation time because $dns_resolve_ip is
            // persisted: every later request restores it from the option and must
            // force cURL again.
            $apbct->settings['wp__use_builtin_http_api'] = false;
        }

        $result = $http->setUrl($url)
                       ->setData($data)
                       ->setPresets($presets)
                       ->setOptions($options)
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

    /**
     * Classify a failed request so httpRequest() can pick a recovery strategy.
     *
     * 'connection_timeout' - cURL error 28. DNS is fine, this particular moderate
     *                        server is slow/down => rotateModerate().
     * 'getaddrinfo_error'  - cURL cannot resolve anything at all. Rotating to
     *                        another hostname changes nothing =>
     *                        rotateModerateWithDnsOverride().
     * 'unknown'            - anything else, treated as a per-server problem =>
     *                        rotateModerate().
     *
     * @param mixed $result
     *
     * @return string
     */
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
