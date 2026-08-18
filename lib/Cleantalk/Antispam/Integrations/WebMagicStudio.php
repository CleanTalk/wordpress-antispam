<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

/**
 * Web Magic Studio custom contact form integration.
 * Form submits to admin-post.php?action=wms_contact (admin_post_nopriv_wms_contact).
 */
class WebMagicStudio extends IntegrationBase
{
    /**
     * @param $argument
     *
     * @return array
     */
    public function getDataForChecking($argument)
    {
        $input_array = apply_filters('apbct__filter_post', $_POST);
        $input_array['event_token'] = Post::getString('ct_bot_detector_event_token');

        $email   = Post::getString('wms_email');
        $name    = Post::getString('wms_name');
        $message = Post::getString('wms_message');
        $need    = Post::getString('wms_need');

        $data = ct_gfa_dto($input_array, $email, $name)->getArray();

        if ( $need !== '' ) {
            $data['subject'] = $need;
        }

        $data['message'] = $message;

        return $data;
    }

    /**
     * @param $message
     *
     * @return void
     */
    public function doBlock($message)
    {
        ct_die_extended($message);
    }
}
