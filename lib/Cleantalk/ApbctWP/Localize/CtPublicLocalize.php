<?php

namespace Cleantalk\ApbctWP\Localize;

class CtPublicLocalize
{
    const NAME = 'ctPublic';
    const HANDLE = 'ct_public_functions';

    public static function getData()
    {
        global $apbct;

        return array(
            '_ajax_nonce'                     => wp_create_nonce('ct_secret_stuff'), // !!! For WP-Rocket minification preventing !!!
            'settings__forms__check_internal' => $apbct->settings['forms__check_internal'],
            'settings__forms__check_external' => $apbct->settings['forms__check_external'],
            'settings__forms__search_test'    => $apbct->settings['forms__search_test'],
            'settings__data__bot_detector_enabled' => $apbct->settings['data__bot_detector_enabled'],
            'blog_home'                       => get_home_url() . '/',
            'pixel__setting'                  => $apbct->settings['data__pixel'],
            'pixel__enabled'                  => $apbct->settings['data__pixel'] === '2' ||
                                                 ($apbct->settings['data__pixel'] === '3' && apbct_is_cache_plugins_exists()),
            'pixel__url'                      => $apbct->pixel_url,
            'data__email_check_before_post'   => $apbct->settings['data__email_check_before_post'],
            'data__cookies_type'              => $apbct->data['cookies_type'],
            'data__key_is_ok'                 => $apbct->data['key_is_ok'],
            'data__visible_fields_required'   => ! apbct_is_user_logged_in() || $apbct->settings['data__protect_logged_in'] == 1,
            'data__to_local_storage' => \Cleantalk\ApbctWP\Variables\NoCookie::preloadForScripts(),

            'wl_brandname'          => $apbct->data['wl_brandname'],
            'wl_brandname_short'    => $apbct->data['wl_brandname_short'],
            'ct_checkjs_key'        => ct_get_checkjs_value(),
            'emailEncoderPassKey'   => apbct_get_email_encoder_pass_key(),
            'bot_detector_forms_excluded'  => base64_encode(apbct__bot_detector_get_prepared_exclusion()),
            'advancedCacheExists' => apbct_is_advanced_cache_exists(),
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
