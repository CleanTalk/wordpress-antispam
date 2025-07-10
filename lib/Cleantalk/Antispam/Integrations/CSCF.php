<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class CSCF extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $data = apply_filters('apbct__filter_post', $_POST);

        if (defined('DOING_AJAX') && DOING_AJAX) {
            if (!Post::hasString('action', 'cscf-submitform')) {
                return null;
            }
            $gfa_checked_data = ct_gfa_dto($data)->getArray();
        } else {
            if (is_object($argument) ) {
                $email_non_ajax = property_exists($argument, 'Email') ? $argument->Email : '';
                $name_non_ajax = property_exists($argument, 'Name') ? $argument->Name : '';
                $data = array();
                $data['message'] = property_exists($argument, 'Message') ? $argument->Message : '';
                $gfa_checked_data = ct_get_fields_any($data, $email_non_ajax, $name_non_ajax);
            } else {
                $gfa_checked_data = ct_get_fields_any($data);
            }
        }

        if (isset($data['ct_bot_detector_event_token'])) {
            $gfa_checked_data['event_token'] = $data['ct_bot_detector_event_token'];
        }
        if (isset($gfa_checked_data['message']) && empty($gfa_checked_data['nickname'])) {
            if (array_key_exists('cscf_name', $gfa_checked_data['message'])) {
                $gfa_checked_data['nickname'] = $gfa_checked_data['message']['cscf_name'];
            } elseif (array_key_exists('cscf[name]', $gfa_checked_data['message'])) {
                $gfa_checked_data['nickname'] = $gfa_checked_data['message']['cscf[name]'];
            }
        }

        if (isset($gfa_checked_data['message'])) {
            foreach ($gfa_checked_data['message'] as $key => $value) {
                if ($key == 'cscf_message' && !empty($value)) {
                    $gfa_checked_data['message'] = $value;
                }
            }
        }

        return $gfa_checked_data;
    }

    public function doBlock($message)
    {
        //for ajax calls
        if (defined('DOING_AJAX') && DOING_AJAX) {
            echo json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $message,
                        'stop_script' => apbct__stop_script_after_ajax_checking()
                    )
                )
            );
            die();
        }
        // other
        ct_die_extended($message);
    }
}
