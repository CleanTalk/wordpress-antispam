<?php

namespace Cleantalk\Antispam\Integrations;

class Forminator extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $data = $_POST;

        $username = '';
        $email = '';
        foreach ($_POST as $key => $value) {
            if (is_string($key) && strpos($key, 'name-') === 0) {
                $username = $value;
                continue;
            }
            if (is_string($key) && strpos($key, 'email-') === 0) {
                $email = $value;
                continue;
            }
        }
        $data['username'] = $username;

        return ct_gfa(apply_filters('apbct__filter_post', $data), $email);
    }

    public function doBlock($message)
    {
        wp_send_json_error(
            array(
                'message' => $message,
                'success' => false,
                'errors'  => array(),
                'behav'   => 'behaviour-thankyou',
            )
        );
    }
}
