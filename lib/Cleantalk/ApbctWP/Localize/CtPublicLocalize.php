<?php

namespace Cleantalk\ApbctWP\Localize;

use Cleantalk\ApbctWP\CleantalkRealPerson;

class CtPublicLocalize
{
    const NAME = 'ctPublic';
    const HANDLE = 'ct_public_functions';

    public static function getData()
    {
        global $apbct;

        $localize_array = array(
            '_ajax_nonce'                     => $apbct->ajax_service->getPublicNonce(), // !!! For WP-Rocket minification preventing !!!
            'settings__forms__check_internal' => $apbct->settings['forms__check_internal'],
            'settings__forms__check_external' => $apbct->settings['forms__check_external'],
            'settings__forms__force_protection' => $apbct->settings['forms__force_protection'],
            'settings__forms__search_test'    => $apbct->settings['forms__search_test'],
            'settings__data__bot_detector_enabled' => $apbct->settings['data__bot_detector_enabled'],
            'settings__sfw__anti_crawler'     => $apbct->settings['sfw__anti_crawler'],
            'blog_home'                       => get_home_url() . '/',
            'pixel__setting'                  => $apbct->settings['data__pixel'],
            'pixel__enabled'                  => $apbct->settings['data__pixel'] === '2' ||
                                                 ($apbct->settings['data__pixel'] === '3' && apbct_is_cache_plugins_exists()),
            'pixel__url'                      => $apbct->pixel_url,
            'data__email_check_before_post'   => $apbct->settings['data__email_check_before_post'],
            'data__email_check_exist_post'    => $apbct->settings['data__email_check_exist_post'],
            'data__cookies_type'              => $apbct->data['cookies_type'],
            'data__key_is_ok'                 => $apbct->data['key_is_ok'],
            'data__visible_fields_required'   => ! apbct_is_user_logged_in() || $apbct->settings['data__protect_logged_in'] == 1,

            'wl_brandname'          => $apbct->data['wl_brandname'],
            'wl_brandname_short'    => $apbct->data['wl_brandname_short'],
            'ct_checkjs_key'        => ct_get_checkjs_value(),
            'emailEncoderPassKey'   => apbct_get_email_encoder_pass_key(),
            'bot_detector_forms_excluded'  => base64_encode(apbct__bot_detector_get_prepared_exclusion()),
            'advancedCacheExists' => apbct_is_advanced_cache_exists(),
            'varnishCacheExists' => apbct_is_varnish_cache_exists(),
            'wc_ajax_add_to_cart' => get_option('woocommerce_enable_ajax_add_to_cart') === 'yes',
        );
        if ( $apbct->settings['comments__the_real_person'] ) {
            $localize_array = array_merge($localize_array, CleantalkRealPerson::getLocalizingData());
        }
        return $localize_array;
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
