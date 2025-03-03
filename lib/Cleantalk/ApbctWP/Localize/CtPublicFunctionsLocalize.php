<?php

namespace Cleantalk\ApbctWP\Localize;

use Cleantalk\ApbctWP\Antispam\EmailEncoder;
use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\Variables\Server;

class CtPublicFunctionsLocalize
{
    const NAME = 'ctPublicFunctions';
    const HANDLE = 'ct_public_functions';

    public static function getData()
    {
        global $apbct;

        $data = array(
            '_ajax_nonce'                          => $apbct->ajax_service->getPublicNonce(),
            '_rest_nonce'                          => wp_create_nonce('wp_rest'),
            '_ajax_url'                            => admin_url('admin-ajax.php', 'relative'),
            '_rest_url'                            => Escape::escUrl(apbct_get_rest_url()),
            'data__cookies_type'                   => $apbct->data['cookies_type'],
            'data__ajax_type'                      => $apbct->data['ajax_type'],
            'data__bot_detector_enabled'           => $apbct->settings['data__bot_detector_enabled'],
            'data__frontend_data_log_enabled'      => defined('APBCT_DO_NOT_COLLECT_FRONTEND_DATA_LOGS') ? 0 : 1,
            'cookiePrefix'                         => apbct__get_cookie_prefix(),
            'wprocket_detected'                    => apbct_is_plugin_active('wp-rocket/wp-rocket.php'),
            'host_url'                             => Server::get('HTTP_HOST'),
        );
        $data = array_merge($data, EmailEncoder::getLocalizationText());

        return $data;
    }

    public static function getCode()
    {
        return '
            <script data-no-defer="1" data-ezscrex="false" data-cfasync="false" data-pagespeed-no-defer data-cookieconsent="ignore">
                var ' . self::NAME . ' = ' . json_encode(self::getData()) . '
            </script>
        ';
    }
}
