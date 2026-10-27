<?php

namespace Cleantalk\Antispam\Integrations;

class SureForms extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if (
            !apbct_is_plugin_active('sureforms/sureforms.php') ||
            !is_array($argument)
        ) {
            return null;
        }

        // Take the email from the email field of the form
        $email = '';
        foreach ($argument as $key => $value) {
            if (strpos((string)$key, 'srfm-email') === 0 && is_string($value) && $value !== '') {
                $email = $value;
                break;
            }
        }

        $data = ct_gfa_dto(apply_filters('apbct__filter_post', $argument), $email)->getArray();
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
    }
}
