<?php

namespace Cleantalk\Antispam\Integrations;

class CalculatedFieldsForm extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( ! empty($_POST) ) {
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST);

            return ct_get_fields_any($input_array);
        }

        return null;
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }

    public function allow()
    {
        return true;
    }
}
