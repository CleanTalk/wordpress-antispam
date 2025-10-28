<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Variables\Post;

class FLBuilderForms extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $data = ct_gfa_dto(apply_filters('apbct__filter_post', $_POST))->getArray();

        if (isset($_POST['data']['ct_bot_detector_event_token'])) {
            $data['event_token'] = $_POST['data']['ct_bot_detector_event_token'];
        }
        if (!isset($data['name'])) {
            $data['nickname'] = Post::getString('name');
        }

        return $data;
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
}
