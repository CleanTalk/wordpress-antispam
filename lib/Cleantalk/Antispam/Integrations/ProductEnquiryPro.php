<?php

namespace Cleantalk\Antispam\Integrations;

class ProductEnquiryPro extends IntegrationBase
{
    private $receiver_email;

    public function getDataForChecking($argument)
    {
        $this->receiver_email = $argument;

        $input_array = apply_filters('apbct__filter_post', $_POST);
        return ct_gfa($input_array);
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }

    public function allow()
    {
        return $this->receiver_email;
    }
}
