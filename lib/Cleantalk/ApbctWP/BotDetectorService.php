<?php

namespace Cleantalk\ApbctWP;

class BotDetectorService
{
    /**
     * @var string
     */
    private $wrapper_script_url = 'https://fd.cleantalk.org/ct-bot-detector-wrapper.js';

    /**
     * @var string
     */
    private static $alternative_src_constant_name = 'APBCT_SERVICE__USE_ALTERNATIVE_BOT_DETECTOR_SERVER';

    public function __construct()
    {
        $this->setWrapperScriptUrl();
    }

    private function setWrapperScriptUrl()
    {
        if (defined(self::$alternative_src_constant_name)) {
            $this->wrapper_script_url = 'https://fd.cleantalk.ru/ct-bot-detector-wrapper-ru.js';
        }
        // place the API logic here in the future
    }

    public function getWrapperScriptUrl()
    {
        return $this->wrapper_script_url;
    }
}
