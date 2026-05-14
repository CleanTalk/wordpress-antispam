<?php

namespace Cleantalk\ApbctWP;

class BotDetectorService
{
    /**
     * URL of the default wrapper script.
     * @var string
     */
    private static $default_wrapper_script_url = 'https://fd.cleantalk.org/ct-bot-detector-wrapper.js';

    /*
     * URL of the alternative wrapper script.
     * @var string
     */
    private static $alternative_wrapper_script_url = 'https://fd.cleantalk.ru/ct-bot-detector-wrapper-ru.js';

    /**
     * Name of the constant that determines whether to use the alternative URL.
     * @var string
     */
    private static $const_name__use_alt_url = 'APBCT_SERVICE__USE_ALTERNATIVE_BOT_DETECTOR_SERVER';

    /**
     * Retrieves the URL of the wrapper script or an alternative URL if a specific constant is defined.
     *
     * @return string The URL of the wrapper script or the alternative script URL.
     */
    public function getWrapperScriptUrl()
    {
        return defined(self::$const_name__use_alt_url)
            ? self::$alternative_wrapper_script_url
            : self::$default_wrapper_script_url;
    }
}
