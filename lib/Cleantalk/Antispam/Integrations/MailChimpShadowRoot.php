<?php

namespace Cleantalk\Antispam\Integrations;

/**
 * This class handles the integration with Mailchimp forms embedded via ShadowRoot (external widget).
 * These forms send data directly to Mailchimp servers bypassing WordPress,
 * so we intercept fetch requests on the JS side and validate them via AJAX.
 */
class MailChimpShadowRoot extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        if (!empty($_POST)) {
            if (!$apbct->stats['no_cookie_data_taken']) {
                apbct_form__get_no_cookie_data();
            }

            $input_array = apply_filters('apbct__filter_post', $_POST);

            $data = ct_gfa_dto($input_array)->getArray();

            // Clean message field - keep only keys containing "message", remove everything else
            if (isset($data['message']) && is_array($data['message'])) {
                $filtered_message = array();
                foreach ($data['message'] as $key => $value) {
                    if (stripos($key, 'message') !== false) {
                        $filtered_message[$key] = $value;
                    }
                }
                $data['message'] = !empty($filtered_message) ? implode(' ', $filtered_message) : '';
            }

            return $data;
        }

        return null;
    }

    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $message,
                        'stop_script' => apbct__stop_script_after_ajax_checking()
                    )
                )
            )
        );
    }

    /**
     * Allow the request to proceed
     * @return void
     */
    public function allow()
    {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked' => false,
                        'allow'   => true,
                    )
                )
            )
        );
    }
}
