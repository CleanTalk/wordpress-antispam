<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\ExternalForms\Submission;

class CleantalkExternalFormsForceAjax extends IntegrationBase
{
    public function doPrepareActions($argument)
    {
        return Submission::validate(true);
    }

    public function getDataForChecking($argument)
    {
        if ( ! Submission::validate(true) ) {
            return null;
        }

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
