<?php

namespace Cleantalk\ApbctWP;

class HooksRegistrar
{
    public static function registerAjaxHooks($ajax_service)
    {
        // Alt cookies
        \Cleantalk\ApbctWP\Variables\AltSessions::registerHooks($ajax_service);

        // JS keys
        $ajax_service->addPublicAction('apbct_js_keys__get', array($ajax_service, 'getJSKeys'));

        // Pixel URL
        $ajax_service->addPublicAction('apbct_get_pixel_url', 'apbct_get_pixel_url');

        // Checking email before POST
        $ajax_service->addPublicAction('apbct_email_check_before_post', 'apbct_email_check_before_post');

        // Checking email exist POST
        $ajax_service->addPublicAction('apbct_email_check_exist_post', 'apbct_email_check_exist_post');

        // Force Protection check bot
        $ajax_service->addPublicAction('apbct_force_protection_check_bot', 'apbct_force_protection_check_bot');
    }
}
