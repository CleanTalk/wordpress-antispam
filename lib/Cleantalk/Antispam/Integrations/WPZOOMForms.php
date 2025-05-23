<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;

/**
 * WPZOOM Froms integration.
 * Note: the integration hook is of admin-post.php - "admin_post_nopriv_wpzf_submit".
 */
class WPZOOMForms extends IntegrationBase
{
    /**
     * Legacy old way to collect data.
     *
     * @param $argument
     *
     * @return array
     */
    public function getDataForChecking($argument)
    {
        $input_array = apply_filters('apbct__filter_post', $_POST);
        $input_array['event_token'] = Post::getString('ct_bot_detector_event_token');
        $email = isset($input_array['wpzf_input_email']) ? $input_array['wpzf_input_email'] : '';
        $message = isset($input_array['wpzf_input_message']) ? $input_array['wpzf_input_message'] : '';
        $name = isset($input_array['wpzf_input_name']) ? $input_array['wpzf_input_name'] : '';
        $data = ct_gfa_dto($input_array, $email, $name)->getArray();
        $data['message'] = $message;
        return $data;
    }

    /**
     * How to handle CleanTalk forbidden result.
     * @param $message
     * @return void
     */
    public function doBlock($message)
    {
        ct_die_extended($message);
    }
}
