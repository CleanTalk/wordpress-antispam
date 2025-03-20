<?php

namespace Cleantalk\Antispam\Integrations;
use Cleantalk\ApbctWP\Variables\Post;

class SmartQuizBuilder extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( Post::get('action') === 'SQBSubmitQuizAjax') {
            $input_array = apply_filters('apbct__filter_post', $_POST);
            return ct_gfa_dto($input_array)->getArray();
        }
        return false;
    }

    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'status' => 'error',
                    'info' => 'form_failed',
                    'message' => $message,
                )
            )
        );
    }
}
