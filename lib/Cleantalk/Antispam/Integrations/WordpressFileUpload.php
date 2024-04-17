<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class WordpressFileUpload extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        $front_data = '';
        if (Post::get('action') == 'cleantalk_wfu_ajax_check') {
            foreach ($_POST as $elem) {
                var_dump('CLASS WordpressFileUpload');
                var_dump($elem);
            }
        }

        return null;
        //return $_POST;
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
