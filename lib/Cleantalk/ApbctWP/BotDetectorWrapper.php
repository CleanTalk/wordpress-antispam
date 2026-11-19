<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Common\BotDetectorService\BotDetectorService;

class BotDetectorWrapper extends BotDetectorService
{
    private const OPTION_NAME = 'cleantalk_bot_detector_wrapper_url';

    /** @var string */
    private $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    public static function usesMainSiteWrapper(): bool
    {
        global $apbct;

        if ( ! APBCT_WPMS || is_main_site() || $apbct->allow_custom_key || $apbct->white_label ) {
            return false;
        }

        return apbct_is_plugin_active_for_network(APBCT_PLUGIN_BASE_NAME)
            || in_array(APBCT_PLUGIN_BASE_NAME, (array) get_blog_option(get_main_site_id(), 'active_plugins'), true);
    }

    protected function callAPIMethod(): string
    {
        return API::methodGetBotDetectorWrapperUrl($this->api_key);
    }

    protected function isWrapperAvailable(string $url): bool
    {
        $response = wp_safe_remote_get($url, array(
            'timeout' => 5,
            'redirection' => 0,
            'limit_response_size' => 65536,
        ));

        return ! is_wp_error($response) &&
            (int) wp_remote_retrieve_response_code($response) === 200 &&
            self::isWrapperContentValid(wp_remote_retrieve_body($response));
    }

    protected function loadWrapperURL(): string
    {
        if ( $this->api_key === '' ) {
            return '';
        }

        $stored = self::usesMainSiteWrapper()
            ? get_blog_option(get_main_site_id(), self::OPTION_NAME)
            : get_option(self::OPTION_NAME);

        if ( ! is_array($stored) || ($stored['key_hash'] ?? '') !== hash('sha256', $this->api_key) ) {
            return '';
        }

        $url = $stored['url'] ?? '';

        return is_string($url) ? $url : '';
    }

    protected function saveWrapperURL(string $url): void
    {
        if ( $url === '' ) {
            delete_option(self::OPTION_NAME);
            return;
        }

        update_option(self::OPTION_NAME, array(
            'url' => $url,
            'key_hash' => hash('sha256', $this->api_key),
        ));
    }
}
