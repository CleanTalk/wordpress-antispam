<?php

namespace Cleantalk\Antispam\Integrations;

use WP_Error;

class BitForm extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        return ct_gfa_dto($input_array)->getArray();
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        return new WP_Error(403, $message);
    }
}
