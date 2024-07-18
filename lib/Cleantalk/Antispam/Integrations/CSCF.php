<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class CSCF extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if (!Post::hasString('action', 'cscf-submitform')) {
            return null;
        }

        $data = apply_filters('apbct__filter_post', $_POST);
        $gfa_checked_data = ct_get_fields_any($data);

        if (isset($data['ct_bot_detector_event_token'])) {
            $gfa_checked_data['event_token'] = $data['ct_bot_detector_event_token'];
        }

        return $gfa_checked_data;
    }

    public function doBlock($message)
    {
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
}
