<?php

namespace Cleantalk\Antispam\Integrations;

class SureForms extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if (
            !apbct_is_plugin_active('sureforms/sureforms.php') ||
            empty($argument['sureforms_form_submit'])
        ) {
            return null;
        }

        $data = ct_gfa_dto(apply_filters('apbct__filter_post', $argument))->getArray();
        return $data;
    }

    public function allow()
    {
        return null;
    }

    public function doBlock($message)
    {
        wp_send_json_error(
            array(
                'message' => $message
            )
        );
        die();
    }
}
