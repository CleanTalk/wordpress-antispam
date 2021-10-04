<?php

namespace Cleantalk\Antispam\Integrations;

class EstimationForm extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( isset($_POST['customerInfos']) ) {
            return ct_get_fields_any($_POST['customerInfos']);
        }

        return null;
    }

    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $message,
                        'stop_script' => 1
                    )
                )
            )
        );
    }
}
