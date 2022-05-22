<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\HTTP\Request;

/**
 * Class API.
 * Compatible only with WordPress.
 *
 * @depends       \Cleantalk\Common\API
 *
 * @version       1.0
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/wordpress-antispam
 */
class API extends \Cleantalk\Common\API
{
	/**
	 * @var array Set of CleanTalk servers
	 */
	public static $cleantalks_servers = array(
		// MODERATE
		'https://moderate1.cleantalk.org' => '162.243.144.175',
		'https://moderate2.cleantalk.org' => '159.203.121.181',
		'https://moderate3.cleantalk.org' => '88.198.153.60',
		'https://moderate4.cleantalk.org' => '159.69.51.30',
		'https://moderate5.cleantalk.org' => '95.216.200.119',
		'https://moderate6.cleantalk.org' => '138.68.234.8',
		'https://moderate8.cleantalk.org' => '188.34.154.26',
		'https://moderate9.cleantalk.org' => '51.81.55.251',

		// APIX
		'https://apix1.cleantalk.org'     => '35.158.52.161',
		'https://apix2.cleantalk.org'     => '18.206.49.217',
		'https://apix3.cleantalk.org'     => '3.18.23.246',
		'https://apix4.cleantalk.org'     => '44.227.90.42',
		'https://apix5.cleantalk.org'     => '15.188.198.212',
		'https://apix6.cleantalk.org'     => '54.219.94.72',
		//ns
		'http://netserv2.cleantalk.org'  => '178.63.60.214',
		'http://netserv3.cleantalk.org'  => '188.40.14.173',
	);

    /**
     * @param $user_token
     * @param $service_id
     * @param $ip
     * @param $servie_type
     * @param $product_id
     * @param $record_type
     * @param $note
     * @param $status
     *
     * @return array|bool|mixed|string[]
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function methodPrivateListAddSfwWl($user_token, $service_id, $ip)
    {
        return static::methodPrivateListAdd(
            $user_token,
            $service_id,
            $ip,
            'spamfirewall',
            1,
            6,
            'Website admin IP. Added automatically.',
            'allow',
            date('Y-m-d H:i:s', time() + 86400 * 30)
        );
    }

    /**
     * Function sends raw request to API server.
     * May use built in WordPress HTTP-API
     *
     * @param array Data to send
     * @param string API server URL
     * @param int $timeout
     * @param bool Do we need to use SSL
     *
     * @return array|bool
     */
    public static function sendRequest($data, $url = self::URL, $timeout = 10, $ssl = false, $ssl_path = '')
    {
        // Possibility to switch API url
        $url = defined('CLEANTALK_API_URL') ? CLEANTALK_API_URL : $url;

        // Adding agent version to data
        $data['agent'] = defined('APBCT_AGENT') ? APBCT_AGENT : '';

        $http = new Request();

        return $http->setUrl($url)
                    ->setData($data)
                    ->setPresets(['retry_with_socket'])
                    ->addCallback(
                        __CLASS__ . '::checkResponse',
                        [$data['method_name']]
                    )
                    ->request();
    }

	/**
	 * Check connection to the API servers
	 *
	 * Example:
	 * [
	 *     [server_url_1] => [
	 *         'result' => string
	 *         'exec_time' => float
	 *     ],
	 *     [server_url_2] => [
	 *         'result' => string
	 *         'exec_time' => float
	 *     ],
	 *     ...
	 * ]
	 *
	 * @return array
	 */
	public static function checkingConnectionWithApiServers()
	{
		$result_connection = array();
		$server_urls = array_keys(self::$cleantalks_servers);

		foreach ( $server_urls as $url ) {
			$connection_time_start  = microtime(true);
			$connection_status = Helper::httpRequestGetResponseCode($url);

			$result_connection[$url] = array(
				'result'    => ! empty($connection_status['error']) ? $connection_status['error'] : 'OK',
				'exec_time' => microtime(true) - $connection_time_start,
			);
		}

		return $result_connection;
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
		if (\Cleantalk\Common\Helper::ipValidate($ip)) {
			$url = array_search( $ip, self::$cleantalks_servers, true );

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
		if (\Cleantalk\Common\Helper::ipValidate($ip)) {
			$url = array_search( $ip, self::$cleantalks_servers, true );

			return $url
				? parse_url($url, PHP_URL_HOST)
				: \Cleantalk\Common\Helper::ipResolve($ip);
		}

		return $ip;
	}
}
