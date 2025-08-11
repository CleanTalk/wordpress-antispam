<?php

namespace Cleantalk\Antispam\Integrations;

class ContactFormPlugin extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        return ct_gfa(apply_filters('apbct__filter_post', $_POST));
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
