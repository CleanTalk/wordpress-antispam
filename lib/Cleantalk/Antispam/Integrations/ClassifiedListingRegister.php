<?php

namespace Cleantalk\Antispam\Integrations;

class ClassifiedListingRegister extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $form_data = array();
        $nonce_value = isset($_POST['rtcl-register-nonce']) ? $_POST['rtcl-register-nonce'] : null;
        if (
            ! apbct_is_plugin_active('classified-listing/classified-listing.php') ||
            empty($_POST['rtcl-register']) ||
            (is_string($nonce_value) &&
            ! wp_verify_nonce($nonce_value, 'rtcl-register'))
        ) {
            return null;
        }

        $form_data['username'] = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
        $form_data['email']    = isset($_POST['email']) ? $_POST['email'] : '';
        if ( ! empty($_POST['first_name']) ) {
            $form_data['first_name'] = $_POST['first_name'];
        }
        if ( ! empty($_POST['last_name']) ) {
            $form_data['last_name'] = $_POST['last_name'];
        }

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $form_data);

        $output = ct_gfa($input_array);
        $output['register'] = true;

        return $output;
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
