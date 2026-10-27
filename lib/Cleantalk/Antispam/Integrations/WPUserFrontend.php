<?php

namespace Cleantalk\Antispam\Integrations;

use WP_Error;

class WPUserFrontend extends IntegrationBase
{
    private $return_argument;

    public function getDataForChecking($argument)
    {
        $this->return_argument = $argument;

        if ( ! ($argument instanceof WP_Error) || $argument->get_error_code() ) {
            return null;
        }

        // The registration fields
        $form_data = array();
        $form_data['email'] = isset($_POST['reg_email']) && is_string($_POST['reg_email'])
            ? sanitize_email(wp_unslash($_POST['reg_email']))
            : '';
        $form_data['username'] = isset($_POST['log']) && is_string($_POST['log'])
            ? sanitize_text_field(wp_unslash($_POST['log']))
            : '';
        if ( ! empty($_POST['reg_fname']) && is_string($_POST['reg_fname']) ) {
            $form_data['first_name'] = sanitize_text_field(wp_unslash($_POST['reg_fname']));
        }
        if ( ! empty($_POST['reg_lname']) && is_string($_POST['reg_lname']) ) {
            $form_data['last_name'] = sanitize_text_field(wp_unslash($_POST['reg_lname']));
        }

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $form_data);

        $output = ct_gfa($input_array, $form_data['email']);
        $output['register'] = true;

        return $output;
    }

    /**
     * @param $message
     *
     * @return WP_Error
     */
    public function doBlock($message)
    {
        $this->return_argument->add('apbct_spam', $message);

        return $this->return_argument;
    }

    public function allow()
    {
        return $this->return_argument;
    }
}
