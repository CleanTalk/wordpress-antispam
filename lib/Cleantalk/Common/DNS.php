<?php

namespace Cleantalk\Common;

class DNS
{
    private static $min_server_timeout = 500;
    private static $max_server_timeout = 1500;
    private static $default_server_ttl;

    /**
     * Function DNS request
     *
     * @param string $host URL
     * @param bool $return_first returns only first found IP, HOST, TTL
     * @param null|int $type DNS type name
     *
     * @return array
     * @psalm-suppress NullableReturnStatement
     */
    public static function getRecord($host, $return_first = false, $type = null)
    {
        $servers = array(
            "ip"   => null,
            "host" => $host,
            "ttl"  => static::$default_server_ttl
        );

        // Get DNS records about URL
        if (function_exists('dns_get_record')) {
            // Localhosts generates errors. block these by @
            $records = $type
                ? @dns_get_record($host, $type)
                : @dns_get_record($host);
            if ($records !== false) {
                $servers = array();
                foreach ($records as $server) {
                    $servers[] = $server;
                }
            }
        }

        // Another try if first failed
        if (function_exists('gethostbynamel') && empty($records)) {
            $records = gethostbynamel($host);
            if ($records !== false) {
                $servers = array();
                foreach ($records as $server) {
                    $servers[] = array(
                        "ip"   => $server,
                        "host" => $host,
                        "ttl"  => static::$default_server_ttl
                    );
                }
            }
        }

        return $return_first
            ? reset($servers)
            : $servers;
    }

    /**
     * @param $servers
     *
     * @return array|null
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function findFastestServer($servers)
    {
        $tmp               = array();
        $fast_server_found = false;

        foreach ($servers as $server) {
            if ($fast_server_found) {
                $ping = static::$max_server_timeout;
            } else {
                $ping = static::getResponseTime($server['ip']);
                $ping *= 1000;
            }

            $tmp[$ping] = $server;

            $fast_server_found = $ping < static::$min_server_timeout;
        }

        if (count($tmp)) {
            ksort($tmp);
            $response = $tmp;
        }

        return $response ?: null;
    }

    /**
     * Function to check response time
     *
     * @param string URL
     *
     * @return int|float Response time
     */
    public static function getResponseTime($host)
    {
        // Skip localhost ping cause it raise error at fsockopen.
        // And return minimum value
        if ($host === 'localhost') {
            return 0.001;
        }

        $starttime = microtime(true);
        $file      = @fsockopen($host, 80, $errno, $errstr, static::$max_server_timeout / 1000);
        $stoptime  = microtime(true);

        if ( ! $file) {
            $status = static::$max_server_timeout / 1000;  // Site is down
        } else {
            fclose($file);
            $status = ($stoptime - $starttime);
            $status = round($status, 4);
        }

        return $status;
    }

    /**
     * Get server TTL
     * Wrapper for self::getRecord()
     *
     * @param $host
     *
     * @return int|false
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function getServerTTL($host)
    {
        $server = static::getRecord($host, true);

        return $server['ttl'];
    }
}
