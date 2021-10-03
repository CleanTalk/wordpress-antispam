<?php

namespace Cleantalk\Antispam\Integrations;

class HappyForm extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( isset($_POST['happyforms_form_id']) ) {
            $data = array();

            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST);

            foreach ( $input_array as $key => $value ) {
                if ( strpos($key, $_POST['happyforms_form_id']) !== false ) {
                    $data[$key] = $value;
                }
            }

            return ! empty($data) ? ct_get_fields_any($data) : null;
        }

        return null;
    }

    public function doBlock($message)
    {
        wp_send_json_error(array(
            'html' => '<div class="happyforms-form happyforms-styles">
							<h3 class="happyforms-form__title">Sample Form</h3>
							<form action="" method="post" novalidate="true">
							<div class="happyforms-flex"><div class="happyforms-message-notices">
							<div class="happyforms-message-notice error">
							<h2>' . $message . '</h2></div></div>
							</form></div>'
        ));
    }

    public function allow()
    {
        return true;
    }
}
