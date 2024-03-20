<?php

namespace Cleantalk\Antispam\Integrations;

class JobstackThemeRegistration extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( isset($_POST['new_user_submit']) ) {
            return null;
        }

        $form_data['email'] = isset($_POST['new_user_email']) ? $_POST['new_user_email'] : '';

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $form_data);

        $input_array['register'] = true;

        return $input_array;
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
