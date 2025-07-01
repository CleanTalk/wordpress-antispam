<?php

namespace Cleantalk\Antispam\Integrations;

class RegistrationMagic extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        if ( isset($_POST['rm_cond_hidden_fields']) ) {
            $input_array = apply_filters('apbct__filter_post', $_POST);
            $data = ct_gfa_dto($input_array)->getArray();
            $data['register'] = true;
            foreach ($_POST as $field => $_value) {
                if (
                    is_string($field) && (
                        strpos($field, 'extbox_') !== false ||
                        strpos($field, 'extarea_') !== false
                    )
                ) {
                    $data['register'] = false;
                    break;
                }
            }

            return $data;
        }
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        return array($message);
    }
}
