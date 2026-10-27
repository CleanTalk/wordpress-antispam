<?php

namespace Cleantalk\Antispam\Integrations;

class MetForm extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( ! apbct_is_plugin_active('metform/metform.php') || ! is_array($argument) ) {
            return null;
        }

        return ct_gfa_dto(apply_filters('apbct__filter_post', $argument))->getArray();
    }

    public function allow()
    {
        return null;
    }

    public function doBlock($message)
    {
        wp_send_json(
            array(
                'status' => 0,
                'error' => $message,
                'data' => array(
                    'message' => '',
                ),
            )
        );
    }
}
