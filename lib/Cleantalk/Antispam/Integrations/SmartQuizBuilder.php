<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;

class SmartQuizBuilder extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $input_array = apply_filters('apbct__filter_post', $_POST);
        $data = ct_gfa_dto($input_array, $input_array['email'])->getArray();
        $data['message'] = '';
        return $data;
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
