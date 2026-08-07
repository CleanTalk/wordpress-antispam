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
            // First non-empty email-* — empty email-2/3 from hidden multi-step pages must not win.
            if (is_string($key) && strpos($key, 'email-') === 0) {
                $candidate = trim(str_replace(' ', '', TT::toString($value)));
                if ($candidate !== '' && $email === '') {
                    $email = $candidate;
                }
            }
        }

        $tmp_data = ct_gfa(apply_filters('apbct__filter_post', $data));

        if ($username !== '') {
            $tmp_data['nickname'] = $username;
        }

        if (
            $email === '' &&
            ! empty($tmp_data['emails_array']) &&
            is_array($tmp_data['emails_array'])
        ) {
            foreach ($tmp_data['emails_array'] as $emails_array_value) {
                $candidate = trim(str_replace(' ', '', TT::toString($emails_array_value)));
                if ($candidate !== '') {
                    $email = $candidate;
                    break;
                }
            }
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
