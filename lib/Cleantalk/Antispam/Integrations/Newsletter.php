<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class Newsletter extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        if (
            $argument === 's' ||
            $argument === 'subscribe' ||
            $argument === 'sa' ||
            $argument === 'ajaxsub'
        ) {
            $posted = stripslashes_deep($_REQUEST);
            $email = $posted['ne'];
            $name = '';
            if (isset($posted['nn'])) {
                $name = $posted['nn'];
                unset($posted['nn']);
            }
            if (isset($posted['ns'])) {
                $name .=  ' ' . $posted['ns'];
                unset($posted['ns']);
            }
            $data = ct_gfa_dto(apply_filters('apbct__filter_post', $posted), $email, $name)->getArray();
            if ( Post::getString('ct_bot_detector_event_token') ) {
                $data['event_token'] = Post::get('ct_bot_detector_event_token');
            }
            return $data;
        }
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die($message, 403);
    }
}
