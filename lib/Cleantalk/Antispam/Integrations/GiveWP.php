<?php

namespace Cleantalk\Antispam\Integrations;

class GiveWP extends IntegrationBase
{

    public function getDataForChecking($argument)
    {
        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        return ct_get_fields_any($input_array);
    }

    public function doBlock($message)
    {
        give_set_error('spam_donation', $message);
        add_action('give_ajax_donation_errors', function () use ($message) {
            return 'Error: ' . $message;
        });
    }
}
