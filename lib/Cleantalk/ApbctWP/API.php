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
     * @return array|bool|string[]
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
     * GET method for getting information
     * whether an email exists
     *
     * @param $email
     * @param $api_key
     *
     * @return array|bool
     */
    public static function methodEmailCheckExist($email, $api_key = '')
    {
        $request = array(
            'method_name' => 'email_check_cms',
            'auth_key' => $api_key,
            'email' => $email,
        );

        return self::sendRequest($request);
    }

    /**
     * Function sends raw request to API server.
     * May use built in WordPress HTTP-API
     *
     * @param array $data Data to send
     * @param int $timeout
     *
     * @return array|bool
     */
    public static function sendRequest($data, $timeout = 10)
    {
        // Possibility to switch API url
        $url = defined('CLEANTALK_API_URL') ? CLEANTALK_API_URL : self::$api_url;

        // Adding agent version to data
        $data['agent'] = defined('APBCT_AGENT') ? APBCT_AGENT : '';

        $http = new Request();

        $request = $http->setUrl($url)
                    ->setData($data)
                    ->setPresets(['retry_with_socket'])
                    ->setOptions(['timeout' => $timeout]);
        if ( isset($data['method_name']) ) {
            $request->addCallback(
                __CLASS__ . '::checkResponse',
                [$data['method_name']]
            );
        }

        return $request->request();
    }
}
