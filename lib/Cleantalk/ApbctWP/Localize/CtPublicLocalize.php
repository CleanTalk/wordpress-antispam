<?php

namespace Cleantalk\ApbctWP\Localize;

class CtPublicLocalize
{
    const NAME = 'ctPublic';
    const HANDLE = 'ct_public_functions';

    /**
     * Get common localized data.
     * @return array
     */
    public static function getData()
    {
        global $apbct;

        return array(
            '_ajax_nonce'                     => wp_create_nonce('ct_secret_stuff'), // !!! For WP-Rocket minification preventing !!!
            'settings__forms__check_internal' => $apbct->settings['forms__check_internal'],
            'settings__forms__check_external' => $apbct->settings['forms__check_external'],
            'settings__forms__search_test'    => $apbct->settings['forms__search_test'],
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
        );
    }

    /**
     * Return merged custom data array with already gained data__to_local_storage array.
     * @param $data
     * @return array merged data to output
     */
    public static function getMergedLocalStorageCustomData($data)
    {

        $late_data =  array(
            'data__to_local_storage' => $data
        );

        return array_merge_recursive($late_data, self::getData());
    }

    /**
     * Output ctPublic localized data.
     * @param $data
     * @return string
     */
    public static function getCode($data = null)
    {
        if ( !empty($data) && is_array($data) ) {
            $source = self::getMergedLocalStorageCustomData($data);
        } else {
            $source = self::getData();
        }

        return '
            <script data-no-defer="1" data-ezscrex="false" data-cfasync="false" data-pagespeed-no-defer>
                ' . self::NAME . ' = ' . json_encode($source) . '
            </script>
        ';
    }
}
