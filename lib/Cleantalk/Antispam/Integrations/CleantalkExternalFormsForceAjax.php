<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class CleantalkExternalFormsForceAjax extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if (Post::getString('action') === 'cleantalk_force_ajax_check') {
            unset($_POST['action']);
        }
        if (Post::getString('no_cache')) {
            unset($_POST['no_cache']);
        }
        $data = ct_gfa_dto(apply_filters('apbct__filter_post', $_POST))->getArray();

        if (isset($data['message']['name']) && !empty($data['message']['name'])) {
            $data['nickname'] = $data['message']['name'];
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

    public function allow()
    {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked' => false,
                        'allow'   => true,
                    )
                )
            )
        );
    }
}
