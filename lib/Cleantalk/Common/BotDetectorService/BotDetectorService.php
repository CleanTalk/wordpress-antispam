<?php

namespace Cleantalk\Common\BotDetectorService;

use Cleantalk\Common\BotDetectorService\Api\Api;
use Cleantalk\Common\Mloader\Mloader;
use Cleantalk\Common\Templates\Singleton;

abstract class BotDetectorService
{
    use Singleton;

    const BD_DEFAULT_DOMAIN = "fd.cleantalk.org";

    const BD_DEFAULT_SCRIPT_NAME = "ct-bot-detector-wrapper.js";

    const CALL_PERIOD = 3600;

    const OPTION_NAME = "bot_detector_wrapper_url";

    /**
     * @param string $api_key
     * @return string|false
     */
    public function callAPIMethod($api_key)
    {
        return Api::methodGetBotDetectorWrapperUrl($api_key);
    }

    /**
     * @param string $wrapper_url
     * @return bool
     */
    public function isWrapperAvailable($wrapper_url)
    {
        $request_class = Mloader::get('Http\Request');
        $http = new $request_class();

        $response = $http->setUrl($wrapper_url)
            ->setPresets(['get_code'])
            ->request();

        return 200 === $response;
    }

    /**
     * @param string $wrapper_url
     * @return void
     */
    abstract public function saveWrapperURL($wrapper_url);

    /**
     * @return string|false
     */
    abstract public function loadWrapperURL();

    /**
     * @return string
     */
    public function getWrapperURL()
    {
        $url_from_bd = $this->loadWrapperURL();
        if ( $url_from_bd ) {
            return htmlspecialchars($url_from_bd, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return htmlspecialchars(
            sprintf("https://%s/%s", self::BD_DEFAULT_DOMAIN, self::BD_DEFAULT_SCRIPT_NAME),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    /**
     * @param string $api_key
     * @return void
     */
    public function updateWrapperURL($api_key)
    {
        $url_from_api = $this->callAPIMethod($api_key);
        if ( ! $this->validateWrapperURL($url_from_api) ) {
            return;
        }
        if ( ! $this->isWrapperAvailable($url_from_api) ) {
            return;
        }
        if ( ! $this->validateWrapperContent($url_from_api) ) {
            return;
        }

        $url_from_bd = $this->loadWrapperURL();

        if ( $url_from_api !== $url_from_bd ) {
            $this->saveWrapperURL($url_from_api);
        }
    }

    /**
     * @param string $wrapper_url
     * @return bool
     */
    private function validateWrapperURL($wrapper_url)
    {
        if ( ! is_string($wrapper_url) || $wrapper_url === '' ) {
            return false;
        }
        $parts = parse_url($wrapper_url);
        if ( empty($parts['scheme']) || empty($parts['host']) ) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        if ( $scheme !== 'https' && $scheme !== 'http' ) {
            return false;
        }
        $host = strtolower($parts['host']);

        // Allow cleantalk.<domain> and subdomains like fd.cleantalk.org
        return (bool) preg_match('/(^|\.)cleantalk\.[a-z0-9-]{2,}$/', $host);
    }

    /**
     * @param $wrapper_url
     * @return bool
     */
    private function validateWrapperContent($wrapper_url)
    {
        // 1) Get content from $wrapper_url
        // 2) Validate this
        return true;
    }
}
