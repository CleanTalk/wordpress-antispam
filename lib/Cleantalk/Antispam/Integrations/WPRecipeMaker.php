<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class WPRecipeMaker extends IntegrationBase
{
    public function getDataForChecking($argument)
    {

        if (
            !apbct_is_plugin_active('wp-recipe-maker-premium/wp-recipe-maker-premium.php')
        ) {
            return null;
        }
        $data = $argument;
        $event_token =  $argument['ct_bot_detector_event_token'] ? $argument['ct_bot_detector_event_token'] : Post::getString('ct_bot_detector_event_token');
        $data = ct_gfa_dto(apply_filters('apbct__filter_post', $data))->getArray();
        $data['event_token'] = $event_token;

        return $data;
    }

    public function allow()
    {
        return null;
    }

    public function doBlock($message)
    {
        echo json_encode(
            array(
                'apbct' => array(
                    'blocked'     => true,
                    'comment'     => $message,
                )
            )
        );
        die();
    }
}
