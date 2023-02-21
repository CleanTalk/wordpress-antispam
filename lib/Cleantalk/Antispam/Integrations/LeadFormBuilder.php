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

        // The string was created by the http_build_query function
        if (!empty($_POST['fdata'])) {
            $fdata = $_POST['fdata'];
            parse_str($fdata, $query_array);
            foreach ($query_array as $param => $param_value) {
                if (strpos((string)$param, 'ct_no_cookie_hidden_field') !== false || strpos($param_value, '_ct_no_cookie_data_') !== false) {
                    \Cleantalk\ApbctWP\Variables\NoCookie::setDataFromHiddenField($query_array[$param]);
                    $apbct->stats['no_cookie_data_taken'] = true;
                    $apbct->save('stats');
                    unset($query_array[$param]);
                }
            }
            $_POST['fdata'] = http_build_query($query_array, null, null, PHP_QUERY_RFC3986);
        }

        return ct_gfa($_POST);
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
