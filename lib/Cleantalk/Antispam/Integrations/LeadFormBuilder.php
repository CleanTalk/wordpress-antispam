<?php

namespace Cleantalk\Antispam\Integrations;

class LeadFormBuilder extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        if (!apbct_is_plugin_active('lead-form-builder/lead-form-builder.php')) {
            return null;
        }

        $data_for_test = array();
        // The string was created by the http_build_query function
        if (!empty($_POST['fdata'])) {
            $fdata = $_POST['fdata'];
            parse_str($fdata, $query_array);
            foreach ($query_array as $param => $param_value) {
                $nickname = empty($nickname) && strpos($param, 'name_') !== false ? $param_value : '';
                $email = empty($email) && strpos($param, 'email_') !== false ? $param_value : '';

                if ($param === 'ct_bot_detector_event_token') {
                    $event_token = $param_value;
                    unset($query_array[$param]);
                }

                if (strpos((string)$param, 'ct_no_cookie_hidden_field') !== false || strpos($param_value, '_ct_no_cookie_data_') !== false) {
                    if ($apbct->data['cookies_type'] === 'none') {
                        \Cleantalk\ApbctWP\Variables\NoCookie::setDataFromHiddenField($query_array[$param]);
                        $apbct->stats['no_cookie_data_taken'] = true;
                        $apbct->save('stats');
                    }

                    unset($query_array[$param]);
                } else {
                    $data_for_test[$param] = $param_value;
                }
            }
            // remove visible fields
            if (isset($query_array['apbct_visible_fields'])) {
                unset($query_array['apbct_visible_fields']);
            }
            $_POST['fdata'] = http_build_query($query_array, '', '&', PHP_QUERY_RFC3986);
        }

        $gfa_result = ct_gfa($data_for_test);
        $gfa_result['nickname']    =    !empty($nickname)    ? $nickname    : $gfa_result['nickname'];
        $gfa_result['email']       =    !empty($email)       ? $email       : $gfa_result['email'];
        $gfa_result['event_token'] =    !empty($event_token) ? $event_token : null;

        return $gfa_result;
    }

    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $message,
                        'stop_script' => 1
                    )
                )
            )
        );
    }
}
