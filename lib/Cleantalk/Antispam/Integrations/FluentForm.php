<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\GetFieldsAny;
use Cleantalk\Common\TT;

class FluentForm extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;
        $event_token = '';

        $probably_skip_due_vendor = $this->skipDueVendorIntegration();

        if ($probably_skip_due_vendor) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '():' . TT::toString($probably_skip_due_vendor), $_POST);
            return null;
        }

        /**
         * Do not use Post:get() there - it uses sanitize_textarea and drops special symbols,
         * including whitespaces - this could concatenate parts of data in single string!
         **/
        if (
            isset($_POST['data']) && is_string($_POST['data']) &&
            (
                $apbct->settings['data__protect_logged_in'] == 1 ||
                ($apbct->settings['data__protect_logged_in'] == 0 && !is_user_logged_in())
            )
        ) {
            parse_str($_POST['data'], $form_data);
            foreach ($form_data as $param => $param_value) {
                if (strpos((string)$param, 'ct_no_cookie_hidden_field') !== false || (is_string($param_value) && strpos($param_value, '_ct_no_cookie_data_') !== false)) {
                    if ($apbct->data['cookies_type'] === 'none') {
                        \Cleantalk\ApbctWP\Variables\NoCookie::setDataFromHiddenField($param_value);
                        $apbct->stats['no_cookie_data_taken'] = true;
                        $apbct->save('stats');
                    }
                    unset($form_data[$param]);
                }
                if ($param === 'ct_bot_detector_event_token') {
                    $event_token = $param_value;
                    unset($form_data[$param]);
                }
            }

            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $form_data);

            $gfa_checked_data = ct_gfa_dto($input_array)->getArray();

            $gfa_checked_data['event_token'] = $event_token;

            $fields_visibility_data = GetFieldsAny::getVisibleFieldsData($input_array);
            $this->setVisibleFieldsData($fields_visibility_data);

            if (isset($gfa_checked_data['message'], $gfa_checked_data['message']['apbct_visible_fields'])) {
                unset($gfa_checked_data['message']['apbct_visible_fields']);
            }

            return $gfa_checked_data;
        }

        return null;
    }

    public function doBlock($message)
    {
        wp_send_json(
            array(
                'errors' => array(
                    'restricted' => array(
                        $message
                    )
                )
            ),
            422
        );
    }

    /**
     * Detect if vendor integration is active
     * @return false|string
     */
    public function skipDueVendorIntegration()
    {
        /*
         * This is global flag set up if vendor integration already executed
         */
        global $fluentformCleantalkExecuted;

        // if flag is set and true - already executed, skip
        if ( isset($fluentformCleantalkExecuted) ) {
            if (true === $fluentformCleantalkExecuted) {
                return 'FLUENTFORM_VENDOR_ACTIVE_INTEGRATION_EXECUTED';
            }
        } else {
            // if flag is not set, check state  of cleantalk integration,
            // this is useful if hooks order changed or global flag changed
            $vendor_integration_option = get_option('_fluentform_cleantalk_details', []);
            $vendor_integration_active = (
                is_array($vendor_integration_option) &&
                isset($vendor_integration_option['status']) &&
                true === $vendor_integration_option['status']
            );
            if ($vendor_integration_active) {
                return 'FLUENTFORM_VENDOR_ACTIVE_INTEGRATION_FOUND';
            }
        }
        return false;
    }
}
