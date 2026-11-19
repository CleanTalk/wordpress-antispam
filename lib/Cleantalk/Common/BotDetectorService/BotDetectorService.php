<?php

namespace Cleantalk\Common\BotDetectorService;

abstract class BotDetectorService
{
    public const DEFAULT_WRAPPER_URL = 'https://fd.cleantalk.org/ct-bot-detector-wrapper.js';
    public const CALL_PERIOD = 3600;

    public function getWrapperURL(): string
    {
        $url = $this->loadWrapperURL();

        return self::isValidWrapperURL($url) ? $url : self::DEFAULT_WRAPPER_URL;
    }

    public function updateWrapperURL(): void
    {
        $url = $this->callAPIMethod();
        if ( ! self::isValidWrapperURL($url) || ! $this->isWrapperAvailable($url) ) {
            $url = '';
        }

        $this->saveWrapperURL($url);
    }

    protected static function isWrapperContentValid(string $content): bool
    {
        // A wrapper loads the main Bot Detector script. Reject empty bodies and HTTP error pages.
        return strpos($content, 'document.createElement') !== false &&
            preg_match('/\bct-bot-detector(?:-[a-z0-9-]+)?(?:\.min)?\.js\b/i', $content) === 1;
    }

    private static function isValidWrapperURL(string $url): bool
    {
        if ( ! filter_var($url, FILTER_VALIDATE_URL) ) {
            return false;
        }

        $parts = parse_url($url);

        return strtolower($parts['scheme'] ?? '') === 'https' &&
            ! isset($parts['user']) && ! isset($parts['pass']) &&
            ($parts['port'] ?? 443) === 443 &&
            preg_match('/(?:^|\.)cleantalk\.(?:org|ru)\z/i', $parts['host'] ?? '') === 1;
    }

    abstract protected function callAPIMethod(): string;

    abstract protected function isWrapperAvailable(string $url): bool;

    abstract protected function loadWrapperURL(): string;

    abstract protected function saveWrapperURL(string $url): void;
}
