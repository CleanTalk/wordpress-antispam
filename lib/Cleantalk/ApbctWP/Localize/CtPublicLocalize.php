<?php

namespace Cleantalk\ApbctWP\Localize;

class CtPublicLocalize implements GetDataInterface
{
    const name = 'ctPublic';
    const handle = 'ct_public_functions';

    public static function getData()
    {
        global $apbct;

        return array(
            'settings__forms__check_internal' => $apbct->settings['forms__check_internal'],
            'settings__forms__check_external' => $apbct->settings['forms__check_external'],
            'blog_home'                       => get_home_url() . '/',
            'pixel__setting'                  => $apbct->settings['data__pixel'],
            'pixel__enabled'                  => $apbct->settings['data__pixel'] === '2' ||
                                               ($apbct->settings['data__pixel'] === '3' && apbct_is_cache_plugins_exists()),
            'pixel__url'                      => $apbct->pixel_url,
            'data__email_check_before_post'   => $apbct->settings['data__email_check_before_post'],
            'data__cookies_type'              => $apbct->data['cookies_type'],
            'data__key_is_ok'                 => $apbct->data['key_is_ok'],
            'data__visible_fields_required'   => ! apbct_is_user_logged_in() || $apbct->settings['data__protect_logged_in'] == 1,
            'data__to_local_storage' => \Cleantalk\ApbctWP\Variables\NoCookie::preloadForScripts()
        );
    }
}
