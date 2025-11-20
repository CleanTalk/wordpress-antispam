<?php

namespace Cleantalk\Antispam\Integrations;

class NEXForms extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if (!empty($_POST) ) {
            return ct_gfa_dto(apply_filters('apbct__filter_post', $_POST))->getArray();
        }

        return $argument;
    }

    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $message,
                        'stop_script' => apbct__stop_script_after_ajax_checking(),
                        'integration' => 'NEXForms'
                    )
                )
            )
        );
    }
}
