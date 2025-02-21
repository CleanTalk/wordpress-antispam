<?php

namespace Cleantalk\Antispam\Integrations;

class AwesomeSupportRegistration extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $data = ct_gfa_dto(apply_filters('apbct__filter_post', $_POST))->getArray();
        $data['register'] = true;
        return $data;
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
