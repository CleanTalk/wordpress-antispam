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
}
