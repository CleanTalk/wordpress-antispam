<?php

namespace Cleantalk\Antispam\Integrations;

class Wpdiscuz extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        return ct_get_fields_any($_POST);
    }

    public function doBlock($message)
    {
        wp_send_json_error('wc_error_email_text');
    }
}
