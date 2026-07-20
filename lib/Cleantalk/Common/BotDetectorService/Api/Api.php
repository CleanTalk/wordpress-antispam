<?php

namespace Cleantalk\Common\BotDetectorService\Api;

use Cleantalk\Common\BotDetectorService\BotDetectorService;

class Api extends \Cleantalk\Common\Api\Api
{
    const METHOD_NAME = "get_bot_detector_wrapper_url";

    public static function methodGetBotDetectorWrapperUrl($api_key)
    {
        return false;

        /*$request = array(
            'method_name' => self::METHOD_NAME,
            'auth_key'    => $api_key,
        );

        return self::sendRequest($request);*/
    }
}
