<?php

namespace Cleantalk\Antispam\Integrations;

class LifterLMS extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        $processed_post = apply_filters('apbct__filter_post', $_POST);
        $data = ct_gfa_dto($processed_post)->getArray();

        $data['register'] = true;

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        ct_die_extended($message);
    }
}
