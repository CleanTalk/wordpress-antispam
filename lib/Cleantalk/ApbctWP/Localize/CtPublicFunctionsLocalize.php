<?php

namespace Cleantalk\ApbctWP\Localize;

use Cleantalk\ApbctWP\Escape;

class CtPublicFunctionsLocalize
{
    const NAME = 'ctPublicFunctions';
    const HANDLE = 'ct_public_functions';

    public static function getData()
    {
        global $apbct;

        return array(
            '_ajax_nonce'                          => wp_create_nonce('ct_secret_stuff'),
            '_rest_nonce'                          => wp_create_nonce('wp_rest'),
            '_ajax_url'                            => admin_url('admin-ajax.php', 'relative'),
            '_rest_url'                            => Escape::escUrl(apbct_get_rest_url()),
            'data__cookies_type'                   => $apbct->data['cookies_type'],
            'data__ajax_type'                      => $apbct->data['ajax_type'],
            'text__wait_for_decoding'              => esc_html__('Decoding the contact data, let us a few seconds to finish. ' . $apbct->data['wl_brandname'], 'cleantalk-spam-protect'),
            'cookiePrefix'                         => apbct__get_cookie_prefix(),
            'wprocket_detected'                    => apbct_is_plugin_active('wp-rocket/wp-rocket.php'),
        );
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
