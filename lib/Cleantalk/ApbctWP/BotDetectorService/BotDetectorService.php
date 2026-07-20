<?php

namespace Cleantalk\ApbctWP\BotDetectorService;

class BotDetectorService extends \Cleantalk\Common\BotDetectorService\BotDetectorService
{
    /**
     * @inheritDoc
     */
    public function saveWrapperURL($wrapper_url)
    {
        global $apbct;
        $apbct->saveWrapperURL($wrapper_url);
    }

    /**
     * @inheritDoc
     */
    public function loadWrapperURL()
    {
        global $apbct;
        return $apbct->loadWrapperURL();
    }

    public static function updateWrapperURLCronHandler()
    {
        global $apbct;
        static::getInstance()->updateWrapperURL($apbct->api_key);
    }
}
