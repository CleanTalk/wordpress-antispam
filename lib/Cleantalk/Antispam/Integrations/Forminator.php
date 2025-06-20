<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Common\TT;

class Forminator extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $data = apply_filters('apbct__filter_post', $_POST);

        $username = '';
        $email = '';
        foreach ($data as $key => $value) {
            if (is_string($key) && strpos($key, 'name-') === 0) {
                $username = $value;
                continue;
            }
            if (is_string($key) && strpos($key, 'email-') === 0) {
                $email = trim(str_replace(' ', '', TT::toString($value)));
            }
        }

        $tmp_data = ct_gfa(apply_filters('apbct__filter_post', $data));

        if ($username !== '') {
            $tmp_data['nickname'] = $username;
        }

        if ($email !== '') {
            $tmp_data['email'] = $email;
        }

        return $tmp_data;
    }

    public function doBlock($message)
    {
        if ( current_filter() === 'forminator_spam_protection' ) {
            throw new \Exception($message);
        }
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
