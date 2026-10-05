<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class Pagelayer extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $event_token = Post::getString('ct_bot_detector_event_token');

        /**
         * Filter for POST
         */
        $input_array = apply_filters('apbct__filter_post', $_POST);

        $service_fields = array(
            'apbct_visible_fields',
            'ct_bot_detector_event_token',
            'ct_no_cookie_hidden_field',
            'action',
            'pagelayer_nonce',
            'pagelayer-contact-submit',
            'cfa-pagelayer-id',
            'cfa-post-id',
            'cfa-custom-template',
            'cfa-redirect',
        );

        foreach ( $service_fields as $service_field ) {
            unset($input_array[$service_field]);
        }

        $base_call_data = ct_gfa_dto($input_array)->getArray();

        $base_call_data['event_token'] = $event_token;

        return $base_call_data;
    }

    public function doBlock($message)
    {
        // Pagelayer frontend renders the 'failed' key as the form error message
        echo json_encode(array('failed' => $message));
        wp_die();
    }

    public function allow()
    {
        return 1;
    }

    /**
     * Pagelayer copies $_POST into its own $formdata right after this filter returns,
     * so service fields have to be dropped here to keep them out of the email body.
     */
    public function doFinalActions($argument)
    {
        apbct_clear_post_service_data_after_base_call();

        return $argument;
    }
}
