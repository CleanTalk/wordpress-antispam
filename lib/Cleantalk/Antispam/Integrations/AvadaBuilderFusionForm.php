<?php

namespace Cleantalk\Antispam\Integrations;

class AvadaBuilderFusionForm extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        if ( isset($_POST['formData']) ) {
            parse_str($_POST['formData'], $data);
            $input_array = apply_filters('apbct__filter_post', $data);

            if ( ! $apbct->stats['no_cookie_data_taken'] ) {
                apbct_form__get_no_cookie_data($data);
            }

            $data_to_spam_check = ct_gfa($input_array);

            if ( isset($data['ct_bot_detector_event_token']) ) {
                $data_to_spam_check['event_token'] = $data['ct_bot_detector_event_token'];
            }

            // It is a service field. Need to be deleted before the processing.
            if ( isset($input_array['apbct_visible_fields']) ) {
                unset($input_array['apbct_visible_fields']);
            }

            $_POST['formData'] = http_build_query($input_array);

            return $data_to_spam_check;
        }

        return null;
    }

    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'status' => 'error',
                    'info' => 'form_failed',
                    'message' => $message,
                )
            )
        );
    }
}
