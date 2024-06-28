<?php

namespace Cleantalk\Antispam\Integrations;

class CleantalkExternalFormsForceAjax extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        return ct_gfa(apply_filters('apbct__filter_post', $_POST));
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
