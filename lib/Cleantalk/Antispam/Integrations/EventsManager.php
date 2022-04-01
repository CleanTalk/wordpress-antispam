<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Variables\Get;

class EventsManager extends IntegrationBase
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
        if ( Get::get('callback') ) {
            $callback = htmlspecialchars(Get::get('callback'));
            $output = array(
                'result' => false,
                'message' => '',
                'errors' => $message,
            );
            die($callback . '(' . json_encode($output) . ')');
        }
        return false;
    }

    public function allow()
    {
        return true;
    }
}
