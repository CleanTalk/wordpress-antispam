<?php

namespace Cleantalk\Antispam\Integrations;

class WooCommerceWholesaleLeadCapture extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        $processed_post = apply_filters('apbct__filter_post', $_POST);
        $data = ct_gfa_dto($processed_post)->getArray();

        if ( isset($_REQUEST['data']['ct_bot_detector_event_token']) ) {
            $data['event_token'] = $_REQUEST['data']['ct_bot_detector_event_token'];
        }
        if ( isset($_REQUEST['data']['ct_no_cookie_hidden_field']) ) {
            $data['ct_no_cookie_hidden_field'] = $_REQUEST['data']['ct_no_cookie_hidden_field'];
        }

        unset($_REQUEST['data']);
        $data['register'] = true;

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        header('Content-Type: application/json'); // specify we return json.
        echo wp_json_encode(
            array(
                'status'        => 'fail',
                'error_message' => $message,
            )
        );
        die();
    }
}
