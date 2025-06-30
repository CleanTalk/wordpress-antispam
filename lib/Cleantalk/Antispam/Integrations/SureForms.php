<?php

namespace Cleantalk\Antispam\Integrations;

class SureForms extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $data = ct_gfa_dto(apply_filters('apbct__filter_post', $argument))->getArray();
        return $data;
    }

    public function doBlock($message)
    {
        return new \WP_REST_Response([
            'registered' => false,
            'message' => $message,
        ], 200);
    }
}
