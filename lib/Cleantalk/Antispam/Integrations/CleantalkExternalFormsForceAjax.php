<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\ExternalForms\Submission;

/**
 * Check direct integrations through JSON responses without creating an HTML relay.
 */
class CleantalkExternalFormsForceAjax extends IntegrationBase
{
    /**
     * Reject relay fields before the direct AJAX integration consumes its action.
     *
     * @param mixed $argument
     * @return bool
     */
    public function doPrepareActions($argument)
    {
        return Submission::validate(true);
    }

    /**
     * Remove AJAX transport fields and extract the original form payload.
     *
     * @param mixed $argument
     * @return array|null
     */
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
