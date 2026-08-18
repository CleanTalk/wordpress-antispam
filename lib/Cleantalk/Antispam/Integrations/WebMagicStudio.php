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

        $email   = isset($input_array['wms_email']) ? $input_array['wms_email'] : '';
        $name    = isset($input_array['wms_name']) ? $input_array['wms_name'] : '';
        $message = isset($input_array['wms_message']) ? $input_array['wms_message'] : '';
        $need    = isset($input_array['wms_need']) ? $input_array['wms_need'] : '';

        $data = ct_gfa_dto($input_array, $email, $name)->getArray();

        if ( $need !== '' ) {
            $data['subject'] = $need;
        }

        $data['message'] = $message;
        $data['event_token'] = isset($input_array['event_token']) ? $input_array['event_token'] : '';

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
