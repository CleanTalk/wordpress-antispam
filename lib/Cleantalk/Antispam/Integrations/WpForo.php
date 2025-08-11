<?php

namespace Cleantalk\Antispam\Integrations;

class WpForo extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if (function_exists('apbct_wp_get_current_user')) {
            $user = apbct_wp_get_current_user();

            if (!empty($user->data->user_email)) {
                /**
                 * Filter for POST
                 */
                $input_array = apply_filters('apbct__filter_post', $_POST);

                return ct_get_fields_any($input_array, $user->data->user_email);
            }
        }

        return array();
    }

    public function doBlock($message)
    {
        wp_die($message);
    }
}
