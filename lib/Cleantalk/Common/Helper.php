<?php

namespace Cleantalk\Common;

use Cleantalk\ApbctWP\Constant;
use Cleantalk\Common\HTTP\Request;
use Cleantalk\Templates\Singleton;
use Cleantalk\ApbctWP\Variables\Server;

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
            'fc00:0:0:0:0:0:0:0/7', // unique local
            'fe80:0:0:0:0:0:0:0/10', // link-local
        ),
    );

    /**
     * @var array Published networks of the CDN providers whose headers are trusted
     */
    public static $cdn_networks = array(
        'cloud_flare' => array(
            'v4' => array(
                '173.245.48.0/20',
                '103.21.244.0/22',
                '103.22.200.0/22',
                '103.31.4.0/22',
                '141.101.64.0/18',
                '108.162.192.0/18',
                '190.93.240.0/20',
                '188.114.96.0/20',
                '197.234.240.0/22',
                '198.41.128.0/17',
                '162.158.0.0/15',
                '104.16.0.0/13',
                '104.24.0.0/14',
                '172.64.0.0/13',
                '131.0.72.0/22',
            ),
            'v6' => array(
                '2400:cb00:0:0:0:0:0:0/32',
                '2606:4700:0:0:0:0:0:0/32',
                '2803:f800:0:0:0:0:0:0/32',
                '2405:b500:0:0:0:0:0:0/32',
                '2405:8100:0:0:0:0:0:0/32',
                '2a06:98c0:0:0:0:0:0:0/29',
                '2c0f:f248:0:0:0:0:0:0/32',
            ),
        ),
        'sucury' => array(
            'v4' => array(
                '192.88.134.0/23',
                '185.93.228.0/22',
                '66.248.200.0/22',
                '208.109.0.0/22',
            ),
            'v6' => array(
                '2a02:fe80:0:0:0:0:0:0/29',
            ),
        ),
        'incapsula' => array(
            'v4' => array(
                '199.83.128.0/21',
                '198.143.32.0/19',
                '149.126.72.0/21',
                '103.28.248.0/22',
                '45.64.64.0/22',
                '185.11.124.0/22',
                '192.230.64.0/18',
                '107.154.0.0/16',
                '45.60.0.0/16',
                '45.223.0.0/16',
                '131.125.128.0/17',
            ),
            'v6' => array(
                '2a02:e980:0:0:0:0:0:0/29',
            ),
        ),
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
    private $headers     = array();

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
        if ( ! empty($ips_stored[ $ip_type_to_get ]) ) {
            return $ips_stored[ $ip_type_to_get ];
        }

        $out = null;

        switch ($ip_type_to_get) {
            // Cloud Flare
            case 'cloud_flare':
                $headers = $headers ?: self::httpGetHeaders();
                if (
                    isset($headers['Cf-Connecting-Ip']) &&
                    (isset($headers['Cf-Ray']) || isset($headers['X-Wpe-Request-Id']))
                ) {
                    $source = $headers['Cf-Connecting-Ip'];
                    $tmp = strpos($source, ',') !== false
                        ? explode(',', $source)
                        : (array)$source;
                    if ( isset($tmp[0]) ) {
                        $ip_version = self::ipValidate(trim($tmp[0], " \n\r\t\v\x00"));
                        if ($ip_version) {
                            $out = $ip_version === 'v6' && ! $v4_only
                                ? self::ipV6Normalize(trim($tmp[0], " \n\r\t\v\x00"))
                                : trim($tmp[0], " \n\r\t\v\x00");
                        }
                    }
                }
                break;

            // GTranslate
            case 'gtranslate':
                $headers = $headers ?: self::httpGetHeaders();
                if (isset($headers['X-Gt-Clientip'], $headers['X-Gt-Viewer-Ip'])) {
                    $ip_version = self::ipValidate($headers['X-Gt-Viewer-Ip']);
                    if ($ip_version) {
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ipV6Normalize(
                            $headers['X-Gt-Viewer-Ip']
                        ) : $headers['X-Gt-Viewer-Ip'];
                    }
                }
                break;

            // ezoic
            case 'ezoic':
                $headers = $headers ?: self::httpGetHeaders();
                if (isset($headers['X-Middleton'], $headers['X-Middleton-Ip'])) {
                    $ip_version = self::ipValidate($headers['X-Middleton-Ip']);
                    if ($ip_version) {
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ipV6Normalize(
                            $headers['X-Middleton-Ip']
                        ) : $headers['X-Middleton-Ip'];
                    }
                }
                break;

            // Sucury
            case 'sucury':
                $headers = $headers ?: self::httpGetHeaders();
                if (isset($headers['X-Sucuri-Clientip'])) {
                    $ip_version = self::ipValidate($headers['X-Sucuri-Clientip']);
                    if ($ip_version) {
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ipV6Normalize(
                            $headers['X-Sucuri-Clientip']
                        ) : $headers['X-Sucuri-Clientip'];
                    }
                }
                break;

            // X-Forwarded-By
            case 'x_forwarded_by':
                $headers = $headers ?: self::httpGetHeaders();
                if (isset($headers['X-Forwarded-By'], $headers['X-Client-Ip'])) {
                    $ip_version = self::ipValidate($headers['X-Client-Ip']);
                    if ($ip_version) {
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ipV6Normalize(
                            $headers['X-Client-Ip']
                        ) : $headers['X-Client-Ip'];
                    }
                }
                break;

            // Stackpath
            case 'stackpath':
                $headers = $headers ?: self::httpGetHeaders();
                if (isset($headers['X-Sp-Edge-Host'], $headers['X-Sp-Forwarded-Ip'])) {
                    $ip_version = self::ipValidate($headers['X-Sp-Forwarded-Ip']);
                    if ($ip_version) {
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ipV6Normalize(
                            $headers['X-Sp-Forwarded-Ip']
                        ) : $headers['X-Sp-Forwarded-Ip'];
                    }
                }
                break;

            // Ico-X-Forwarded-For
            case 'ico_x_forwarded_for':
                $headers = $headers ?: self::httpGetHeaders();
                if (isset($headers['Ico-X-Forwarded-For'], $headers['X-Forwarded-Host'])) {
                    $ip_version = self::ipValidate($headers['Ico-X-Forwarded-For']);
                    if ($ip_version) {
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ipV6Normalize(
                            $headers['Ico-X-Forwarded-For']
                        ) : $headers['Ico-X-Forwarded-For'];
                    }
                }
                break;

            // OVH
            case 'ovh':
                $headers = $headers ?: self::httpGetHeaders();
                if (isset($headers['X-Cdn-Any-Ip'], $headers['Remote-Ip'])) {
                    $ip_version = self::ipValidate($headers['Remote-Ip']);
                    if ($ip_version) {
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ipV6Normalize(
                            $headers['Remote-Ip']
                        ) : $headers['Remote-Ip'];
                    }
                }
                break;

            // Incapsula proxy
            case 'incapsula':
                $headers = $headers ?: self::httpGetHeaders();
                if (isset($headers['Incap-Client-Ip'], $headers['X-Forwarded-For'])) {
                    $ip_version = self::ipValidate($headers['Incap-Client-Ip']);
                    if ($ip_version) {
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ipV6Normalize(
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
                    if ($ip_version) {
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ipV6Normalize($matches[1]) : $matches[1];
                    }
                }
                break;

            // Remote addr
            case 'remote_addr':
                $remote_addr = self::ipV6MappedToV4(Server::getString('REMOTE_ADDR'));
                $ip_version  = self::ipValidate($remote_addr);
                if ($ip_version) {
                    $out = $ip_version === 'v6' && ! $v4_only ? self::ipV6Normalize(
                        $remote_addr
                    ) : $remote_addr;
                }
                break;

            // X-Forwarded-For
            case 'x_forwarded_for':
                $headers = $headers ?: self::httpGetHeaders();
                if (isset($headers['X-Forwarded-For'])) {
                    $tmp        = explode(',', trim($headers['X-Forwarded-For'], " \n\r\t\v\x00"));
                    $tmp        = trim($tmp[0], " \n\r\t\v\x00");
                    $ip_version = self::ipValidate($tmp);
                    if ($ip_version) {
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ipV6Normalize($tmp) : $tmp;
                    }
                }
                break;

            // X-Real-Ip
            case 'x_real_ip':
                $headers = $headers ?: self::httpGetHeaders();
                if (isset($headers['X-Real-Ip'])) {
                    $tmp        = explode(",", trim($headers['X-Real-Ip'], " \n\r\t\v\x00"));
                    $tmp        = trim($tmp[0], " \n\r\t\v\x00");
                    $ip_version = self::ipValidate($tmp);
                    if ($ip_version) {
                        $out = $ip_version === 'v6' && ! $v4_only ? self::ipV6Normalize($tmp) : $tmp;
                    }
                }
                break;

            // Real
            // Getting real IP from REMOTE_ADDR, or from the proxy headers if the request came from the CDN network
            // or from the trusted proxy (X-Forwarded-For, X-Real-Ip are used if REMOTE_ADDR is local).
            case 'real':
                $headers = $headers ?: self::httpGetHeaders();
                $peer_ip = self::ipGetPeer($headers);

                if ( self::ipIsCdnNetwork($peer_ip, 'cloud_flare') ) {
                    $out = self::ipGet('cloud_flare', false, $headers);
                } elseif ( self::ipIsCdnNetwork($peer_ip, 'sucury') ) {
                    $out = self::ipGet('sucury', false, $headers);
                } elseif ( self::ipIsCdnNetwork($peer_ip, 'incapsula') ) {
                    $out = self::ipGet('incapsula', false, $headers);
                    $out = $out ?: self::ipGet('clientside', false, $headers);
                } elseif ( self::ipIsTrustedProxy($peer_ip) ) {
                    $out = self::ipGet('cloud_flare', false, $headers);
                    $out = $out ?: self::ipGet('sucury', false, $headers);
                    $out = $out ?: self::ipGet('gtranslate', false, $headers);
                    $out = $out ?: self::ipGet('ezoic', false, $headers);
                    $out = $out ?: self::ipGet('stackpath', false, $headers);
                    $out = $out ?: self::ipGet('x_forwarded_by', false, $headers);
                    $out = $out ?: self::ipGet('ico_x_forwarded_for', false, $headers);
                    $out = $out ?: self::ipGet('ovh', false, $headers);
                    $out = $out ?: self::ipGet('incapsula', false, $headers);
                    $out = $out ?: self::ipGet('clientside', false, $headers);
                }

                // GTranslate proxy behind the CDN
                if ( $out && self::ipIsTrustedProxy($out) ) {
                    $out = self::ipGet('gtranslate', false, $headers) ?: $out;
                }

                if ( ! $out && self::ipValidate($peer_ip) ) {
                    $out = self::ipValidate($peer_ip) === 'v6' ? self::ipV6Normalize($peer_ip) : $peer_ip;
                }

                break;

            default:
                $out = self::ipGet('real', $v4_only, $headers);
        }

        if ( is_string($out) ) {
            $ip_version = self::ipValidate($out);

            if ( ! $ip_version ) {
                $out = null;
            }

            if ( $ip_version === 'v6' && $v4_only ) {
                $out = null;
            }
        }

        // Store the IP of the current type to skip the work next time
        self::getInstance()->ips_stored[ $ip_type_to_get ] = $out;

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
     * Checks if the IP belongs to the published networks of the CDN provider
     *
     * @param string $ip
     * @param string $cdn Key of self::$cdn_networks
     *
     * @return bool
     */
    public static function ipIsCdnNetwork($ip, $cdn)
    {
        $ip_version = self::ipValidate($ip);

        if ( ! $ip_version || ! isset(self::$cdn_networks[$cdn][$ip_version]) ) {
            return false;
        }

        $ip = $ip_version === 'v6' ? self::ipV6Normalize($ip) : $ip;

        return self::ipMaskMatch($ip, self::$cdn_networks[$cdn][$ip_version], $ip_version);
    }

    /**
     * Checks if the IP is a trusted proxy: the local network or the address set by
     * APBCT_SERVICE__TRUSTED_PROXIES constant like '192.0.2.1, 198.51.100.0/24'
     *
     * @param string $ip
     *
     * @return bool
     */
    public static function ipIsTrustedProxy($ip)
    {
        $ip_version = self::ipValidate($ip);

        if ( ! $ip_version ) {
            return false;
        }

        $ip = $ip_version === 'v6' ? self::ipV6Normalize($ip) : $ip;

        if ( self::ipIsPrivateNetwork($ip, $ip_version) ) {
            return true;
        }

        $trusted_proxies = explode(',', (string)Constant::getValue(Constant::APBCT_SERVICE__TRUSTED_PROXIES, ''));
        foreach ( $trusted_proxies as $trusted_proxy ) {
            $trusted_proxy = explode('/', trim($trusted_proxy, " \n\r\t\v\x00"), 2);
            if ( self::ipValidate($trusted_proxy[0]) !== $ip_version ) {
                continue;
            }

            $network = $ip_version === 'v6' ? self::ipV6Normalize($trusted_proxy[0]) : $trusted_proxy[0];
            $mask    = isset($trusted_proxy[1]) ? (int)$trusted_proxy[1] : ($ip_version === 'v6' ? 128 : 32);

            if ( self::ipMaskMatch($ip, $network . '/' . $mask, $ip_version) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the address of the visitor connection.
     * REMOTE_ADDR, or the nearest untrusted address from X-Forwarded-For (X-Real-Ip) if REMOTE_ADDR is a trusted proxy.
     *
     * @param array $headers
     *
     * @return string
     */
    public static function ipGetPeer($headers = array())
    {
        $headers = $headers ?: self::httpGetHeaders();
        $peer_ip = self::ipV6MappedToV4(Server::getString('REMOTE_ADDR'));

        if ( ! self::ipIsTrustedProxy($peer_ip) ) {
            return $peer_ip;
        }

        $forwarded_ips = '';
        if ( isset($headers['X-Forwarded-For']) ) {
            $forwarded_ips = $headers['X-Forwarded-For'];
        } elseif ( isset($headers['X-Real-Ip']) ) {
            $forwarded_ips = $headers['X-Real-Ip'];
        }

        // Take last 20 addresses, the nearest one is the last
        $forwarded_ips = array_slice(explode(',', (string)$forwarded_ips), -20);
        foreach ( array_reverse($forwarded_ips) as $forwarded_ip ) {
            $forwarded_ip = self::ipV6MappedToV4(trim($forwarded_ip, " \n\r\t\v\x00"));
            if ( ! self::ipValidate($forwarded_ip) ) {
                break;
            }

            $peer_ip = $forwarded_ip;
            if ( ! self::ipIsTrustedProxy($peer_ip) ) {
                break;
            }
        }

        return $peer_ip;
    }

    /**
     * Get IPv4 from IPv4-mapped IPv6 address like ::ffff:192.0.2.1
     *
     * @param string $ip
     *
     * @return string
     */
    public static function ipV6MappedToV4($ip)
    {
        $ip_packed = strpos($ip, ':') !== false ? @inet_pton($ip) : false;

        if (
            is_string($ip_packed) &&
            strlen($ip_packed) === 16 &&
            strpos($ip_packed, str_repeat("\x00", 10) . "\xff\xff") === 0
        ) {
            return (string)inet_ntop(substr($ip_packed, 12));
        }

        return $ip;
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
        if (is_array($cidr)) {
            foreach ($cidr as $curr_mask) {
                if (self::ipMaskMatch($ip, $curr_mask, $ip_type)) {
                    return true;
                }
            }

            return false;
        }

        if ( ! self::ipValidate($ip) || ! self::cidrValidate($cidr) ) {
            return false;
        }

        $xtet_base = ($ip_type === 'v4') ? 8 : 16;

        // Calculate mask
        $exploded = explode('/', $cidr);

        if ( ! isset($exploded[0], $exploded[1]) ) {
            return false;
        }

        $net_ip   = $exploded[0];
        $mask     = (int)$exploded[1];

        // Exit condition
        $xtet_end = ceil($mask / $xtet_base);
        if ($xtet_count == $xtet_end) {
            return true;
        }

        // Length of bits for comparison
        $mask = $mask - $xtet_base * $xtet_count >= $xtet_base ? $xtet_base : $mask - $xtet_base * $xtet_count;

        // Explode by octets/hextets from IP and Net
        $net_ip_xtets = explode($ip_type === 'v4' ? '.' : ':', $net_ip);
        $ip_xtets     = explode($ip_type === 'v4' ? '.' : ':', $ip);

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
        $ip_xtet  = str_pad(
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
        for ($i = 0, $result = true; $mask != 0; $mask--, $i++) {
            if ($ip_xtet[$i] != $net_xtet[$i]) {
                $result = false;
                break;
            }
        }

        // Recursing. Moving to next octet/hextet.
        if ($result) {
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
     * @param string|null|false $ip
     *
     * @return string|bool
     */
    public static function ipValidate($ip)
    {
        if ( ! $ip ) { // NULL || FALSE || '' || so on...
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $ip != '0.0.0.0') { // IPv4
            return 'v4';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && self::ipV6Reduce($ip) != '0::0') { // IPv6
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
        $ip = trim($ip, " \n\r\t\v\x00");
        // Searching for ::ffff:xx.xx.xx.xx patterns and turn it to IPv6
        if (preg_match('/^::ffff:([0-9]{1,3}\.?){4}$/', $ip)) {
            $ip = dechex((int)sprintf("%u", ip2long(substr($ip, 7))));
            $ip = '0:0:0:0:0:0:' . (strlen($ip) > 4 ? substr('abcde', 0, -4) : '0') . ':' . substr($ip, -4, 4);
            // Normalizing hextets number
        } elseif (strpos($ip, '::') !== false) {
            $ip = str_replace('::', str_repeat(':0', 8 - substr_count($ip, ':')) . ':', $ip);
            $ip = strpos($ip, ':') === 0 ? '0' . $ip : $ip;
            $ip = strpos(strrev($ip), ':') === 0 ? $ip . '0' : $ip;
        }
        // Simplifyng hextets
        if (preg_match('/:0(?=[a-z0-9]+)/', $ip)) {
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
        if (strpos($ip, ':') !== false) {
            $ip = preg_replace('/:0{1,4}/', ':', $ip);
            $ip = preg_replace('/:{2,}/', '::', $ip);
            $ip = strpos($ip, '0') === 0 && substr($ip, 1) !== false ? substr($ip, 1) : $ip;
        }

        return $ip;
    }

    /**
     * Resolve IP to hostname with FCrDNS (Forward-Confirmed reverse DNS) verification.
     * Protects against PTR spoofing by verifying the hostname resolves back to the same IP.
     *
     * @param string $ip IP address to resolve
     *
     * @return string|false Verified hostname or false on failure
     */
    public static function ipResolve($ip)
    {
        // Validate IP first
        $ip_version = self::ipValidate($ip);
        if (!$ip_version) {
            return false;
        }

        // Reverse DNS lookup (PTR record)
        $hostname = gethostbyaddr($ip);

        // If gethostbyaddr returns the IP itself, it means no PTR record exists
        if (!$hostname || $hostname === $ip) {
            return false;
        }

        // Forward DNS lookup - use dns_get_record() to support both IPv4 (A) and IPv6 (AAAA) records
        $record_type = ($ip_version === 'v6') ? DNS_AAAA : DNS_A;
        $ip_field = ($ip_version === 'v6') ? 'ipv6' : 'ip';

        $records = @dns_get_record($hostname, $record_type);

        // If forward lookup fails, we can't verify
        if (empty($records)) {
            return false;
        }

        // Extract IPs from DNS records
        $forward_ips = array();
        foreach ($records as $record) {
            if (isset($record[$ip_field])) {
                $forward_ips[] = $record[$ip_field];
            }
        }

        if (empty($forward_ips)) {
            return false;
        }

        // Check if the original IP is in the list of IPs the hostname resolves to
        if ($ip_version === 'v6') {
            $normalized_ip = self::ipV6Normalize($ip);
            foreach ($forward_ips as $forward_ip) {
                if (self::ipV6Normalize($forward_ip) === $normalized_ip) {
                    return $hostname;
                }
            }
        } elseif (in_array($ip, $forward_ips, true)) {
            return $hostname;
        }

        return false;
    }

    /**
     * Resolve DNS to IP.
     *
     * $host must be a full URL that passes FILTER_VALIDATE_URL (scheme required).
     * A bare hostname such as HTTP_HOST, or any non-string value, is rejected
     * and $out is returned.
     *
     * @param mixed $host Full URL (FILTER_VALIDATE_URL); other types are rejected
     * @param bool|string $out Fallback when $host is invalid or lookup fails
     *
     * @return bool|string First A-record IPv4 on success, otherwise $out
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function dnsResolve($host, $out = false)
    {
        // Check if the $url is set and it is an url
        if ( ! is_string($host) || $host === '' || ! filter_var($host, FILTER_VALIDATE_URL)) {
            return $out;
        }

        if ( strpos($host, '://') !== false ) {
            $parsed_host = parse_url($host, PHP_URL_HOST);
            if ( is_string($parsed_host) && $parsed_host !== '' ) {
                $host = $parsed_host;
            }
        }

        if ( strpos($host, ':') !== false && ! filter_var($host, FILTER_VALIDATE_IP) ) {
            $host = strstr($host, ':', true);
        }

        $is_ip = filter_var($host, FILTER_VALIDATE_IP);

        if ( ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
             && ! $is_ip ) {
            return false;
        }

        if ( $is_ip ) {
            return $host;
        }

        // Get DNS records about URL
        if (function_exists('dns_get_record')) {
            $records = dns_get_record($host, DNS_A);
            if ($records !== false && isset($records[0]['ip'])) {
                $out = $records[0]['ip'];
            }
        }

        // Another try if first failed
        if ( ! $out && function_exists('gethostbynamel')) {
            $records = gethostbynamel($host);
            if ($records !== false && isset($records[0])) {
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
     * @return array|bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function httpMultiRequest($urls, $write_to = '')
    {
        if ( ! is_array($urls) || empty($urls)) {
            return array('error' => 'CURL_MULTI: Parameter is not an array.');
        }

        foreach ($urls as $url) {
            if ( ! is_string($url)) {
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
     * Merging arrays without resetting numeric keys
     *
     * @param array $arr1 One-dimensional array
     * @param array $arr2 One-dimensional array
     *
     * @return array Merged array
     */
    public static function arrayMergeSaveNumericKeys($arr1, $arr2)
    {
        foreach ($arr2 as $key => $val) {
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
        foreach ($arr2 as $key => $val) {
            // Array | array => array
            if (isset($arr1[$key]) && is_array($arr1[$key]) && is_array($val)) {
                $arr1[$key] = self::arrayMergeSaveNumericKeysRecursive($arr1[$key], $val);
                // Scalar | array => array
            } elseif (isset($arr1[$key]) && ! is_array($arr1[$key]) && is_array($val)) {
                $tmp          = $arr1[$key] =
                $arr1[$key] = $val;
                $arr1[$key][] = $tmp;
                // array  | scalar => array
            } elseif (isset($arr1[$key]) && is_array($arr1[$key]) && ! is_array($val)) {
                $arr1[$key][] = $val;
                // scalar | scalar => scalar
            } else {
                $arr1[$key] = $val;
            }
        }

        return $arr1;
    }

    /**
     * Compare two arrays keys to find if there are difference. Recursive.
     * @param array $f_array First array
     * @param array $s_array Second array
     * @return bool True if arrays keys has difference, false otherwise.
     */
    public static function arraysHasKeysDifferenceRecursive($f_array, $s_array)
    {
        $difference = array_diff_key($f_array, $s_array);
        if ( !empty($difference) ) {
            return true;
        }
        foreach ( $f_array as $f_key => $f_value ) {
            if ( is_array($f_value) && is_array($s_array[$f_key]) ) {
                $new_difference = self::arraysHasKeysDifferenceRecursive($f_value, $s_array[$f_key]);
                if ( !empty($new_difference) ) {
                    return true;
                }
            }
        }
        return false;
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
        if (is_array($data) || is_object($data)) {
            foreach ($data as $_key => &$val) {
                $val = self::removeNonUTF8($val);
            }
            unset($val);
            //String
        } else {
            if ( ! preg_match('//u', $data)) {
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
        if (is_null($obj)) {
            return $obj;
        }
        // Array || object
        if (is_array($obj) || is_object($obj)) {
            foreach ($obj as $_key => &$val) {
                $val = self::toUTF8($val, $data_codepage);
            }
            unset($val);
            //String
        } else {
            if ( ! preg_match('//u', $obj) ) {
                if ( function_exists('mb_detect_encoding') ) {
                    $encoding = mb_detect_encoding($obj);
                    $encoding = $encoding ?: $data_codepage;
                } else {
                    $encoding = $data_codepage;
                }

                if ($encoding) {
                    if ( function_exists('mb_convert_encoding') ) {
                        $obj = mb_convert_encoding($obj, 'UTF-8', $encoding);
                    } elseif (version_compare(phpversion(), '8.3', '<')) {
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
        if ( ! empty($data) && @file_exists($data)) {
            $type = mime_content_type($data);
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $type  = finfo_buffer($finfo, $data);
            if (PHP_VERSION_ID < 80000) {
                finfo_close($finfo);
            }
        }

        // @ToDo the method must return comparison result: return $type ===  mime_content_type($data)
        return $type;
    }

    public static function bufferTrimAndClearFromEmptyLines($buffer)
    {
        $buffer = (array)$buffer;
        foreach ($buffer as $indx => &$line) {
            $line = trim($line, " \n\r\t\v\x00");
            if ($line === '') {
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
        foreach ($buffer as &$line) {
            $line = str_getcsv($line, ',', '\'', "\0");
        }

        return $buffer;
    }

    /**
     * Pops line from buffer without formatting
     *
     * @param string $csv
     *
     * @return string
     */
    public static function bufferCsvPopLine(&$csv)
    {
        $pos  = (int) strpos($csv, "\n");
        $line = substr($csv, 0, $pos);
        $csv  = substr_replace($csv, '', 0, $pos + 1);

        return $line;
    }

    /**
     * Pops line from the csv buffer and format it by map to array
     *
     * @param string $csv
     *
     * @return array
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
     * @return array
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function bufferCsvPopLineToArray(&$csv, $map = array())
    {
        $line = trim(static::bufferCsvPopLine($csv), " \n\r\t\v\x00");
        $line = strpos($line, '\'') === 0
            ? str_getcsv($line, ',', '\'', "\0")
            : explode(',', $line);
        if ($map) {
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
        global $wpdb;
        if (is_array($param)) {
            foreach ($param as &$par) {
                $par = self::dbPrepareParam($par);
            }
            unset($par);
        }
        switch (true) {
            case is_numeric($param):
                $param = (int)$param;
                break;
            case is_string($param) && strtolower($param) === 'null':
                $param = 'NULL';
                break;
            case is_string($param):
                $param = $quotes . $wpdb->_real_escape($param) . $quotes;
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
        if ( ! empty($headers) ) {
            return $headers;
        }
        foreach ($_SERVER as $key => $val) {
            if (0 === stripos($key, 'http_')) {
                $server_key = preg_replace('/^http_/i', '', $key);
                $key_parts  = explode('_', $server_key);
                if (strlen($server_key) > 2) {
                    foreach ($key_parts as $part_index => $part) {
                        if ($part === '') {
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
        $ip = [];
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

        if (count($date_arr) === 3) {
            if (checkdate((int)$date_arr[1], (int)$date_arr[2], (int)$date_arr[0])) {
                return true;
            }
        }

        return false;
    }
}
