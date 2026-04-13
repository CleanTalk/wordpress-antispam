<?php

namespace Cleantalk\Antispam\Integrations;

class ElementorUltimateAddonsRegister extends IntegrationBase
{
    public function getDataForChecking($argument)
    {

        $input_array = apply_filters('apbct__filter_post', $_POST);
        $data = ct_gfa_dto($input_array)->getArray();

        $data['register'] = true;

        return $data;
    }

    public function doBlock($message)
    {
        wp_send_json(
            array('success' => false,
                'error'   => array(
                    'email' => $message
                )
            )
        );
        return false;
    }

    public function allow()
    {
        return true;
    }
}
