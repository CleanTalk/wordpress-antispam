<?php

namespace Cleantalk\Antispam\Integrations;

class EaelLoginRegister extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        $data             = ct_get_fields_any($input_array);
        $data['register'] = true;

        return $data;
    }

    /**
     * @param $message
     *
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
