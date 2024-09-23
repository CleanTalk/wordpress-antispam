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
                $email = trim(str_replace(' ', '', $value));
                continue;
            }
        }

        $tmp_data = ct_gfa(apply_filters('apbct__filter_post', $data));

        if ($username !== '') {
            $tmp_data['username'] = $username;
        }

        if ($email !== '') {
            $tmp_data['email'] = $email;
        }

        return $tmp_data;
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
