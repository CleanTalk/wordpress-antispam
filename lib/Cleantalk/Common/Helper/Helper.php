<?php

namespace Cleantalk\Common\Helper;

use Cleantalk\Common\Http\Request;
use Cleantalk\Common\Templates\Singleton;
use Cleantalk\Common\Variables\Server;

/**
 * CleanTalk Helper class.
 * Compatible with any CMS.
 *
 * @package       PHP Anti-Spam by CleanTalk
 * @subpackage    Helper
 * @Version       4.0
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/php-antispam
 */
class Helper
{
    use Singleton;

    /**
     * Default user agent for HTTP requests
     */
    const AGENT = 'Cleantalk-Helper/4.0';

    /**
     * @var array Set of private networks IPv4 and IPv6
     */
    public static $private_networks = array(
        'v4' => array(
            '10.0.0.0/8',
            '100.64.0.0/10',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.1/32',
        ),
        'v6' => array(
            '0:0:0:0:0:0:0:1/128', // localhost
            '0:0:0:0:0:0:a:1/128', // ::ffff:127.0.0.1
        ),
    );

    /**
     * @var array Set of CleanTalk servers
     */
    public static $cleantalks_servers = array(
        // MODERATE
        'https://moderate1.cleantalk.org' => '143.198.237.245',
        'https://moderate2.cleantalk.org' => '167.71.167.197',
        'https://moderate3.cleantalk.org' => '88.198.153.60',
        'https://moderate4.cleantalk.org' => '159.69.51.30',
        'https://moderate5.cleantalk.org' => '95.216.200.119',
        'https://moderate6.cleantalk.org' => '143.244.187.11',
        'https://moderate7.cleantalk.org' => '168.119.82.149',
        'https://moderate9.cleantalk.org' => '51.81.55.251',
        'https://moderate10.cleantalk.org' => '5.9.221.162',

        // APIX
        'https://apix1.cleantalk.org' => '35.158.52.161',
        'https://apix2.cleantalk.org' => '18.206.49.217',
        'https://apix3.cleantalk.org' => '3.18.23.246',
        'https://apix4.cleantalk.org' => '44.227.90.42',
        'https://apix5.cleantalk.org' => '15.188.198.212',
        'https://apix6.cleantalk.org' => '54.219.94.72',
        //ns
        'http://netserv2.cleantalk.org' => '178.63.60.214',
        'http://netserv3.cleantalk.org' => '188.40.14.173',
    );

    /**
     * @var array Stored IPs
     *            [
     *              [ type ] => IP,
     *              [ type ] => IP,
     *            ]
     */
    private $ips_stored = array();

    /**
     * @var array Stored HTTP headers
     */
    private $headers = array();

    /**
     * Getting arrays of IP (REMOTE_ADDR, X-Forwarded-For, X-Real-Ip, Cf_Connecting_Ip)
     *
     * @param string $ip_type_to_get Type of IP you want to receive
     * @param bool $v4_only
     *
     * @return string|null
     *
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress ComplexMethod
     * @psalm-suppress FalsableReturnStatement
     */
    public static function ipGet($ip_type_to_get = 'real', $v4_only = true, $headers = array())
    {
        // If  return the IP of the current type if it already has been detected
        $ips_stored = self::getInstance()->ips_stored;
        if ( !empty($ips_stored[$ip_type_to_get]) ) {
            return $ips_stored[$ip_type_to_get];
        }

        $out = null;

        switch ( $ip_type_to_get ) {
            // Cloud Flare
            case 'cloud_flare':
                $headers = $headers ?: self::httpGetHeaders();
                if (
                    isset($headers['Cf-Connecting-Ip']) &&
                    (isset($headers['Cf-Ray']) || isset($headers['X-Wpe-Request-Id'])) &&
                    !isset($headers['X-Gt-Clientip'])
                ) {
                    if ( isset($headers['Cf-Pseudo-Ipv4'], $headers['Cf-Pseudo-Ipv6']) ) {
                        $source = $headers['Cf-Pseudo-Ipv6'];
                    } else {
                        $source = $headers['Cf-Connecting-Ip'];
                    }
                    $tmp = strpos($source, ',') !== false
                        ? explode(',', $source)
                        : (array)$source;
                    $ip_version = self::ipValidate(trim($tmp[0]));
                    if ( $ip_version ) {
                        $out = $ip_version === 'v6' && !$v4_only ? self::ipV6Normalize(trim($tmp[0])) : trim(
                            $tmp[0]
                        );
                    }
                }
                break;

            // GTranslate
            case 'gtranslate':
                $headers = $headers ?: self::httpGetHeaders();
                if ( isset($headers['X-Gt-Clientip'], $headers['X-Gt-Viewer-Ip']) ) {
                    $ip_version = self::ipValidate($headers['X-Gt-Viewer-Ip']);
                    if ( $ip_version ) {
                        $out = $ip_version === 'v6' && !$v4_only ? self::ipV6Normalize(
                            $headers['X-Gt-Viewer-Ip']
                        ) : $headers['X-Gt-Viewer-Ip'];
                    }
                }
                break;

            // ezoic
            case 'ezoic':
                $headers = $headers ?: self::httpGetHeaders();
                if ( isset($headers['X-Middleton'], $headers['X-Middleton-Ip']) ) {
                    $ip_version = self::ipValidate($headers['X-Middleton-Ip']);
                    if ( $ip_version ) {
                        $out = $ip_version === 'v6' && !$v4_only ? self::ipV6Normalize(
                            $headers['X-Middleton-Ip']
                        ) : $headers['X-Middleton-Ip'];
                    }
                }
                break;

            // Sucury
            case 'sucury':
                $headers = $headers ?: self::httpGetHeaders();
                if ( isset($headers['X-Sucuri-Clientip']) ) {
                    $ip_version = self::ipValidate($headers['X-Sucuri-Clientip']);
                    if ( $ip_version ) {
                        $out = $ip_version === 'v6' && !$v4_only ? self::ipV6Normalize(
                            $headers['X-Sucuri-Clientip']
                        ) : $headers['X-Sucuri-Clientip'];
                    }
                }
                break;

            // X-Forwarded-By
            case 'x_forwarded_by':
                $headers = $headers ?: self::httpGetHeaders();
                if ( isset($headers['X-Forwarded-By'], $headers['X-Client-Ip']) ) {
                    $ip_version = self::ipValidate($headers['X-Client-Ip']);
                    if ( $ip_version ) {
                        $out = $ip_version === 'v6' && !$v4_only ? self::ipV6Normalize(
                            $headers['X-Client-Ip']
                        ) : $headers['X-Client-Ip'];
                    }
                }
                break;

            // Stackpath
            case 'stackpath':
                $headers = $headers ?: self::httpGetHeaders();
                if ( isset($headers['X-Sp-Edge-Host'], $headers['X-Sp-Forwarded-Ip']) ) {
                    $ip_version = self::ipValidate($headers['X-Sp-Forwarded-Ip']);
                    if ( $ip_version ) {
                        $out = $ip_version === 'v6' && !$v4_only ? self::ipV6Normalize(
                            $headers['X-Sp-Forwarded-Ip']
                        ) : $headers['X-Sp-Forwarded-Ip'];
                    }
                }
                break;

            // Ico-X-Forwarded-For
            case 'ico_x_forwarded_for':
                $headers = $headers ?: self::httpGetHeaders();
                if ( isset($headers['Ico-X-Forwarded-For'], $headers['X-Forwarded-Host']) ) {
                    $ip_version = self::ipValidate($headers['Ico-X-Forwarded-For']);
                    if ( $ip_version ) {
                        $out = $ip_version === 'v6' && !$v4_only ? self::ipV6Normalize(
                            $headers['Ico-X-Forwarded-For']
                        ) : $headers['Ico-X-Forwarded-For'];
                    }
                }
                break;

            // OVH
            case 'ovh':
                $headers = $headers ?: self::httpGetHeaders();
                if ( isset($headers['X-Cdn-Any-Ip'], $headers['Remote-Ip']) ) {
                    $ip_version = self::ipValidate($headers['Remote-Ip']);
                    if ( $ip_version ) {
                        $out = $ip_version === 'v6' && !$v4_only ? self::ipV6Normalize(
                            $headers['Remote-Ip']
                        ) : $headers['Remote-Ip'];
                    }
                }
                break;

            // Incapsula proxy
            case 'incapsula':
                $headers = $headers ?: self::httpGetHeaders();
                if ( isset($headers['Incap-Client-Ip'], $headers['X-Forwarded-For']) ) {
                    $ip_version = self::ipValidate($headers['Incap-Client-Ip']);
                    if ( $ip_version ) {
                        $out = $ip_version === 'v6' && !$v4_only ? self::ipV6Normalize(
                            $headers['Incap-Client-Ip']
                        ) : $headers['Incap-Client-Ip'];
                    }
                }
                break;

            // Incapsula proxy like "X-Clientside":"10.10.10.10:62967 -> 192.168.1.1:443"
            case 'clientside':
                $headers = $headers ?: self::httpGetHeaders();
                if (
                    isset($headers['X-Clientside'])
                    && (preg_match('/^([0-9a-f.:]+):\d+ -> ([0-9a-f.:]+):\d+$/', $headers['X-Clientside'], $matches)
                        && isset($matches[1]))
                ) {
                    $ip_version = self::ipValidate($matches[1]);
                    if ( $ip_version ) {
                        $out = $ip_version === 'v6' && !$v4_only ? self::ipV6Normalize($matches[1]) : $matches[1];
                    }
                }
                break;

            // Remote addr
            case 'remote_addr':
                $ip_version = self::ipValidate(Server::get('REMOTE_ADDR'));
                if ( $ip_version ) {
                    $out = $ip_version === 'v6' && !$v4_only ? self::ipV6Normalize(
                        Server::get('REMOTE_ADDR')
                    ) : Server::get('REMOTE_ADDR');
                }
                break;

            // X-Forwarded-For
            case 'x_forwarded_for':
                $headers = $headers ?: self::httpGetHeaders();
                if ( isset($headers['X-Forwarded-For']) ) {
                    $tmp = explode(',', trim($headers['X-Forwarded-For']));
                    $tmp = trim($tmp[0]);
                    $ip_version = self::ipValidate($tmp);
                    if ( $ip_version ) {
                        $out = $ip_version === 'v6' && !$v4_only ? self::ipV6Normalize($tmp) : $tmp;
                    }
                }
                break;

            // X-Real-Ip
            case 'x_real_ip':
                $headers = $headers ?: self::httpGetHeaders();
                if ( isset($headers['X-Real-Ip']) ) {
                    $tmp = explode(",", trim($headers['X-Real-Ip']));
                    $tmp = trim($tmp[0]);
                    $ip_version = self::ipValidate($tmp);
                    if ( $ip_version ) {
                        $out = $ip_version === 'v6' && !$v4_only ? self::ipV6Normalize($tmp) : $tmp;
                    }
                }
                break;

            // Real
            // Getting real IP from REMOTE_ADDR or Cf_Connecting_Ip if set or from (X-Forwarded-For, X-Real-Ip) if REMOTE_ADDR is local.
            case 'real':
                // Detect IP type
                $out = self::ipGet('cloud_flare', $v4_only, $headers);
                $out = $out ?: self::ipGet('sucury', $v4_only, $headers);
                $out = $out ?: self::ipGet('gtranslate', $v4_only, $headers);
                $out = $out ?: self::ipGet('ezoic', $v4_only, $headers);
                $out = $out ?: self::ipGet('stackpath', $v4_only, $headers);
                $out = $out ?: self::ipGet('x_forwarded_by', $v4_only, $headers);
                $out = $out ?: self::ipGet('ico_x_forwarded_for', $v4_only, $headers);
                $out = $out ?: self::ipGet('ovh', $v4_only, $headers);
                $out = $out ?: self::ipGet('incapsula', $v4_only, $headers);
                $out = $out ?: self::ipGet('clientside', $v4_only, $headers);

                $ip_version = self::ipValidate($out);

                // Is private network
                if (
                    !$out ||
                    (
                        is_string($ip_version) && (
                            self::ipIsPrivateNetwork($out, $ip_version) ||
                            self::ipMaskMatch(
                                $out,
                                Server::get('SERVER_ADDR') . '/24',
                                $ip_version
                            ))
                    )
                ) {
                    //@todo Remove local IP from x-forwarded-for and x-real-ip
                    $out = $out ?: self::ipGet('x_forwarded_for', $v4_only, $headers);
                    $out = $out ?: self::ipGet('x_real_ip', $v4_only, $headers);
                }

                $out = $out ?: self::ipGet('remote_addr', $v4_only, $headers);

                break;

            default:
                $out = self::ipGet('real', $v4_only, $headers);
        }

        $ip_version = self::ipValidate($out);

        if ( !$ip_version ) {
            $out = null;
        }

        if ( $ip_version === 'v6' && $v4_only ) {
            $out = null;
        }

        // Store the IP of the current type to skip the work next time
        self::getInstance()->ips_stored[$ip_type_to_get] = $out;

        return $out;
    }

    /**
     * Checks if the IP is in private range
     *
     * @param string $ip
     * @param string $ip_type
     *
     * @return bool
     */
    public static function ipIsPrivateNetwork($ip, $ip_type = 'v4')
    {
        return self::ipMaskMatch($ip, self::$private_networks[$ip_type], $ip_type);
    }

    /**
     * Check if the IP belong to mask.  Recursive.
     * Octet by octet for IPv4
     * Hextet by hextet for IPv6
     *
     * @param string $ip
     * @param string|array $cidr work to compare with
     * @param string $ip_type IPv6 or IPv4
     * @param int $xtet_count Recursive counter. Determs current part of address to check.
     *
     * @return bool
     * @psalm-suppress InvalidScalarArgument
     */
    public static function ipMaskMatch($ip, $cidr, $ip_type = 'v4', $xtet_count = 0)
    {
        if ( is_array($cidr) ) {
            foreach ( $cidr as $curr_mask ) {
                if ( self::ipMaskMatch($ip, $curr_mask, $ip_type) ) {
                    return true;
                }
            }

            return false;
        }

        if ( !self::ipValidate($ip) || !self::cidrValidate($cidr) ) {
            return false;
        }

        $xtet_base = ($ip_type === 'v4') ? 8 : 16;

        // Calculate mask
        $exploded = explode('/', $cidr);
        $net_ip = $exploded[0];
        $mask = (int)$exploded[1];

        // Exit condition
        $xtet_end = ceil($mask / $xtet_base);
        if ( $xtet_count == $xtet_end ) {
            return true;
        }

        // Length of bits for comparison
        $mask = $mask - $xtet_base * $xtet_count >= $xtet_base ? $xtet_base : $mask - $xtet_base * $xtet_count;

        // Explode by octets/hextets from IP and Net
        $net_ip_xtets = explode($ip_type === 'v4' ? '.' : ':', $net_ip);
        $ip_xtets = explode($ip_type === 'v4' ? '.' : ':', $ip);

        // Standartizing. Getting current octets/hextets. Adding leading zeros.
        $net_xtet = str_pad(
            decbin(
                ($ip_type === 'v4' && (int)$net_ip_xtets[$xtet_count]) ? $net_ip_xtets[$xtet_count] : @hexdec(
                    $net_ip_xtets[$xtet_count]
                )
            ),
            $xtet_base,
            0,
            STR_PAD_LEFT
        );
        $ip_xtet = str_pad(
            decbin(
                ($ip_type === 'v4' && (int)$ip_xtets[$xtet_count]) ? $ip_xtets[$xtet_count] : @hexdec(
                    $ip_xtets[$xtet_count]
                )
            ),
            $xtet_base,
            0,
            STR_PAD_LEFT
        );

        // Comparing bit by bit
        for ( $i = 0, $result = true; $mask != 0; $mask--, $i++ ) {
            if ( $ip_xtet[$i] != $net_xtet[$i] ) {
                $result = false;
                break;
            }
        }

        // Recursing. Moving to next octet/hextet.
        if ( $result ) {
            $result = self::ipMaskMatch($ip, $cidr, $ip_type, $xtet_count + 1);
        }

        return $result;
    }

    /**
     * Converts long mask like 4294967295 to number like 32
     *
     * @param int $long_mask
     *
     * @return int
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function ipMaskLongToNumber($long_mask)
    {
        $num_mask = strpos(decbin($long_mask), '0');

        return $num_mask === false ? 32 : $num_mask;
    }

    /**
     * Validating IPv4, IPv6
     *
     * @param string $ip
     *
     * @return string|bool
     */
    public static function ipValidate($ip)
    {
        if ( !$ip ) { // NULL || FALSE || '' || so on...
            return false;
        }
        if ( filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $ip != '0.0.0.0' ) { // IPv4
            return 'v4';
        }
        if ( filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && self::ipV6Reduce($ip) != '0::0' ) { // IPv6
            return 'v6';
        }

        return false; // Unknown
    }

    /**
     * Validate CIDR
     *
     * @param string $cidr expects string like 1.1.1.1/32
     *
     * @return bool
     */
    public static function cidrValidate($cidr)
    {
        $cidr = explode('/', $cidr);

        return isset($cidr[0], $cidr[1]) && self::ipValidate($cidr[0]) && preg_match('@\d{1,2}@', $cidr[1]);
    }

    /**
     * Expand IPv6
     *
     * @param string $ip
     *
     * @return string IPv6
     */
    public static function ipV6Normalize($ip)
    {
        $ip = trim($ip);
        // Searching for ::ffff:xx.xx.xx.xx patterns and turn it to IPv6
        if ( preg_match('/^::ffff:([0-9]{1,3}\.?){4}$/', $ip) ) {
            $ip = dechex((int)sprintf("%u", ip2long(substr($ip, 7))));
            $ip = '0:0:0:0:0:0:' . (strlen($ip) > 4 ? substr('abcde', 0, -4) : '0') . ':' . substr($ip, -4, 4);
            // Normalizing hextets number
        } elseif ( strpos($ip, '::') !== false ) {
            $ip = str_replace('::', str_repeat(':0', 8 - substr_count($ip, ':')) . ':', $ip);
            $ip = strpos($ip, ':') === 0 ? '0' . $ip : $ip;
            $ip = strpos(strrev($ip), ':') === 0 ? $ip . '0' : $ip;
        }
        // Simplifyng hextets
        if ( preg_match('/:0(?=[a-z0-9]+)/', $ip) ) {
            $ip = preg_replace('/:0(?=[a-z0-9]+)/', ':', strtolower($ip));
            $ip = self::ipV6Normalize($ip);
        }

        return $ip;
    }

    /**
     * Reduce IPv6
     *
     * @param string $ip
     *
     * @return string IPv6
     */
    public static function ipV6Reduce($ip)
    {
        if ( strpos($ip, ':') !== false ) {
            $ip = preg_replace('/:0{1,4}/', ':', $ip);
            $ip = preg_replace('/:{2,}/', '::', $ip);
            $ip = strpos($ip, '0') === 0 && substr($ip, 1) !== false ? substr($ip, 1) : $ip;
        }

        return $ip;
    }

    /**
     * Get URL form IP. Check if it's belong to cleantalk.
     *
     * @param string $ip
     *
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function ipIsCleantalks($ip)
    {
        if ( self::ipValidate($ip) ) {
            $url = array_search($ip, self::$cleantalks_servers);

            return (bool)$url;
        }

        return false;
    }

    /**
     * Get URL form IP. Check if it's belong to cleantalk.
     *
     * @param $ip
     *
     * @return false|int|string|bool
     */
    public static function ipResolveCleantalks($ip)
    {
        if ( self::ipValidate($ip) ) {
            $url = array_search($ip, self::$cleantalks_servers);

            return $url
                ? parse_url($url, PHP_URL_HOST)
                : self::ipResolve($ip);
        }

        return $ip;
    }

    /**
     * Get URL form IP
     *
     * @param $ip
     *
     * @return string
     */
    public static function ipResolve($ip)
    {
        if ( self::ipValidate($ip) ) {
            $url = gethostbyaddr($ip);
            if ( $url ) {
                return $url;
            }
        }

        return $ip;
    }

    /**
     * Resolve DNS to IP
     *
     * @param      $host
     * @param bool $out
     *
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function dnsResolve($host, $out = false)
    {
        // Get DNS records about URL
        if ( function_exists('dns_get_record') ) {
            $records = dns_get_record($host, DNS_A);
            if ( $records !== false ) {
                $out = $records[0]['ip'];
            }
        }

        // Another try if first failed
        if ( !$out && function_exists('gethostbynamel') ) {
            $records = gethostbynamel($host);
            if ( $records !== false ) {
                $out = $records[0];
            }
        }

        return $out;
    }

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
    public static function httpRequest($url, $data = array(), $presets = array(), $opts = array())
    {
        $http = new Request();

        return $http->setUrl($url)
            ->setData($data)
            ->setPresets($presets)
            ->setOptions($opts)
            ->request();
    }

    /**
     * Do multi curl requests.
     *
     * @param array $urls Array of URLs to requests
     *
     * @return array|bool|string
     */
    public static function httpMultiRequest($urls, $write_to = '')
    {
        if ( !is_array($urls) || empty($urls) ) {
            return array('error' => 'CURL_MULTI: Parameter is not an array.');
        }

        foreach ( $urls as $url ) {
            if ( !is_string($url) ) {
                return array('error' => 'CURL_MULTI: Parameter elements must be strings.');
            }
        }

        $http = new Request();

        $http->setUrl($urls)
            ->setPresets('get');

        if ( $write_to ) {
            $http->addCallback(
                static function ($content, $url) use ($write_to) {
                    if ( is_dir($write_to) && is_writable($write_to) ) {
                        return file_put_contents($write_to . self::getFilenameFromUrl($url), $content)
                            ? 'success'
                            : 'error';
                    }

                    return $content;
                }
            );
        }

        return $http->request();
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

        if ( $response_code === 200 ) { // Check if it's there
            $data = static::httpRequestGetContent($url);

            if ( empty($data['error']) ) {
                if ( static::getMimeType($data, 'application/x-gzip') ) {
                    if ( function_exists('gzdecode') ) {
                        $data = gzdecode($data);

                        if ( $data !== false ) {
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

    /**
     * Merging arrays without resetting numeric keys
     *
     * @param array $arr1 One-dimensional array
     * @param array $arr2 One-dimensional array
     *
     * @return array Merged array
     */
    public static function arrayMergeSaveNumericKeys($arr1, $arr2)
    {
        foreach ( $arr2 as $key => $val ) {
            $arr1[$key] = $val;
        }

        return $arr1;
    }

    /**
     * Merging arrays without reseting numeric keys recursive
     *
     * @param array $arr1 One-dimentional array
     * @param array $arr2 One-dimentional array
     *
     * @return array Merged array
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function arrayMergeSaveNumericKeysRecursive($arr1, $arr2)
    {
        foreach ( $arr2 as $key => $val ) {
            // Array | array => array
            if ( isset($arr1[$key]) && is_array($arr1[$key]) && is_array($val) ) {
                $arr1[$key] = self::arrayMergeSaveNumericKeysRecursive($arr1[$key], $val);
                // Scalar | array => array
            } elseif ( isset($arr1[$key]) && !is_array($arr1[$key]) && is_array($val) ) {
                $tmp = $arr1[$key] =
                $arr1[$key] = $val;
                $arr1[$key][] = $tmp;
                // array  | scalar => array
            } elseif ( isset($arr1[$key]) && is_array($arr1[$key]) && !is_array($val) ) {
                $arr1[$key][] = $val;
                // scalar | scalar => scalar
            } else {
                $arr1[$key] = $val;
            }
        }

        return $arr1;
    }

    /**
     * Function removing non UTF8 characters from array|string|object
     *
     * @param array|object|string $data
     *
     * @return array|object|string
     */
    public static function removeNonUTF8($data)
    {
        // Array || object
        if ( is_array($data) || is_object($data) ) {
            foreach ( $data as $_key => &$val ) {
                $val = self::removeNonUTF8($val);
            }
            unset($val);
            //String
        } else {
            if ( !preg_match('//u', $data) ) {
                $data = 'Nulled. Not UTF8 encoded or malformed.';
            }
        }

        return $data;
    }

    /**
     * Function convert anything to UTF8 and removes non UTF8 characters
     *
     * @param array|object|string $obj
     * @param null|string $data_codepage
     *
     * @return array|false|mixed|string|string[]|null
     */
    public static function toUTF8($obj, $data_codepage = null)
    {
        // Array || object
        if (is_array($obj) || is_object($obj)) {
            foreach ($obj as $_key => &$val) {
                $val = self::toUTF8($val, $data_codepage);
            }
            unset($val);
        //String
        } else {
            if ( !preg_match('//u', $obj) ) {
                if ( function_exists('mb_detect_encoding') ) {
                    $encoding = mb_detect_encoding($obj);
                    $encoding = $encoding ?: $data_codepage;
                } else {
                    $encoding = $data_codepage;
                }

                if ( $encoding ) {
                    if ( function_exists('mb_convert_encoding') ) {
                        $obj = mb_convert_encoding($obj, 'UTF-8', $encoding);
                    } elseif ( version_compare(phpversion(), '8.3', '<') ) {
                        $obj = @utf8_encode($obj);
                    }
                }
            }
        }

        return $obj;
    }

    /**
     * Function convert from UTF8
     *
     * @param array|object|string $obj
     * @param string $data_codepage
     *
     * @return mixed (array|object|string)
     */
    public static function fromUTF8($obj, $data_codepage = null)
    {
        // Array || object
        if (is_array($obj) || is_object($obj)) {
            foreach ($obj as $_key => &$val) {
                $val = self::fromUTF8($val, $data_codepage);
            }
            unset($val);
        //String
        } else {
            if ($data_codepage !== null && preg_match('//u', $obj)) {
                if ( function_exists('mb_convert_encoding') ) {
                    $obj = mb_convert_encoding($obj, $data_codepage, 'UTF-8');
                } elseif (version_compare(phpversion(), '8.3', '<')) {
                    $obj = @utf8_decode($obj);
                }
            }
        }

        return $obj;
    }

    /**
     * Checks if the string is JSON type
     *
     * @param string $string
     *
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function isJson($string)
    {
        return is_string($string) && is_array(json_decode($string, true));
    }

    /**
     * @param int $interval
     *
     * @return int
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function timeGetIntervalStart($interval = 300)
    {
        return time() - ((time() - strtotime(date('d F Y'))) % $interval);
    }

    /**
     * Get mime type from file or data
     *
     * @param string $data Path to file or data
     * @param string $type Default mime type. Returns if we failed to detect type
     *
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function getMimeType($data, $type = '')
    {
        $data = str_replace(chr(0), '', $data); // Clean input of null bytes
        if ( !empty($data) && @file_exists($data) ) {
            $type = mime_content_type($data);
        } elseif ( function_exists('finfo_open') ) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $type = finfo_buffer($finfo, $data);
            finfo_close($finfo);
        }

        // @ToDo the method must return comparison result: return $type ===  mime_content_type($data)
        return $type;
    }

    public static function bufferTrimAndClearFromEmptyLines($buffer)
    {
        $buffer = (array)$buffer;
        foreach ( $buffer as $indx => &$line ) {
            $line = trim($line);
            if ( $line === '' ) {
                unset($buffer[$indx]);
            }
        }

        return $buffer;
    }

    /**
     * @param $buffer
     *
     * @return array
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function bufferParseCsv($buffer)
    {
        $buffer = explode("\n", $buffer);
        $buffer = self::bufferTrimAndClearFromEmptyLines($buffer);
        foreach ( $buffer as &$line ) {
            $line = str_getcsv($line, ',', '\'');
        }

        return $buffer;
    }

    /**
     * Pops line from buffer without formatting
     *
     * @param $csv
     *
     * @return false|string
     */
    public static function bufferCsvPopLine(&$csv)
    {
        $pos = strpos($csv, "\n");
        $line = substr($csv, 0, $pos);
        $csv = substr_replace($csv, '', 0, $pos + 1);

        return $line;
    }

    /**
     * Pops line from the csv buffer and format it by map to array
     *
     * @param $csv
     * @param array $map
     *
     * @return array|false
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function bufferCsvGetMap(&$csv)
    {
        $line = static::bufferCsvPopLine($csv);

        return explode(',', $line);
    }

    /**
     * Pops line from the csv buffer and format it by map to array
     *
     * @param $csv
     * @param array $map
     *
     * @return array|false
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function bufferCsvPopLineToArray(&$csv, $map = array())
    {
        $line = trim(static::bufferCsvPopLine($csv));
        $line = strpos($line, '\'') === 0
            ? str_getcsv($line, ',', '\'')
            : explode(',', $line);
        if ( $map ) {
            $line = array_combine($map, $line);
        }

        return $line;
    }

    /**
     * Escapes MySQL params
     *
     * @param string|int|array $param
     * @param string $quotes
     *
     * @return int|string|array
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function dbPrepareParam($param, $quotes = '\'')
    {
        if ( is_array($param) ) {
            foreach ( $param as &$par ) {
                $par = self::dbPrepareParam($par);
            }
            unset($par);
        }
        switch ( true ) {
            case is_numeric($param):
                $param = (int)$param;
                break;
            case is_string($param) && strtolower($param) === 'null':
                $param = 'NULL';
                break;
            case is_string($param):
                $param = $quotes . addslashes($param) . $quotes;
                break;
        }

        return $param;
    }

    /**
     * Gets every HTTP_ headers from $_SERVER
     *
     * If Apache web server is missing then making
     * Patch for apache_request_headers()
     *
     * returns array
     */
    public static function httpGetHeaders()
    {
        // If headers already return them
        $headers = self::getInstance()->headers;
        if ( !empty($headers) ) {
            return $headers;
        }
        foreach ( $_SERVER as $key => $val ) {
            if ( 0 === stripos($key, 'http_') ) {
                $server_key = preg_replace('/^http_/i', '', $key);
                $key_parts = explode('_', $server_key);
                if ( strlen($server_key) > 2 ) {
                    foreach ( $key_parts as $part_index => $part ) {
                        if ( $part === '' ) {
                            continue;
                        }

                        $key_parts[$part_index] = function_exists('mb_strtolower') ? mb_strtolower(
                            $part
                        ) : strtolower(
                            $part
                        );
                        $key_parts[$part_index][0] = strtoupper($key_parts[$part_index][0]);
                    }
                    $server_key = implode('-', $key_parts);
                }
                $headers[$server_key] = $val;
            }
        }

        // Store headers to skip the work next time
        self::getInstance()->headers = $headers;

        return $headers;
    }

    /**
     * Its own implementation of the native method long2ip()
     *
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function ipLong2ip($ipl32)
    {
        $ip[0] = ($ipl32 >> 24) & 255;
        $ip[1] = ($ipl32 >> 16) & 255;
        $ip[2] = ($ipl32 >> 8) & 255;
        $ip[3] = $ipl32 & 255;

        return implode('.', $ip);
    }

    /**
     * @param $url string
     *
     * @return string
     */
    protected static function getFilenameFromUrl($url)
    {
        $array = explode('/', $url);

        return end($array);
    }

    /**
     * Validate date format Y-m-d
     *
     * @return boolean
     */
    public static function dateValidate($date)
    {
        $date_arr = explode('-', $date);

        if ( count($date_arr) === 3 ) {
            if ( checkdate((int)$date_arr[1], (int)$date_arr[2], (int)$date_arr[0]) ) {
                return true;
            }
        }

        return false;
    }

    public static function isApikeyCorrect($api_key)
    {
        return (bool)preg_match('/^[a-z\d]{3,30}$/', $api_key);
    }
}
