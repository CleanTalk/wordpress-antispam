<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class WpGeoDirectory extends IntegrationBase
{
    private $return_argument = true;

    public function getDataForChecking($argument)
    {
        $this->return_argument = $argument;

        if (
            (
                apbct_is_plugin_active('geodirectory/geodirectory.php') &&
                Post::get('action') === 'geodir_save_post'
            )
        ) {
            apbct_form__get_no_cookie_data($_POST);
            $gfa_checked_data = ct_gfa($_POST, Post::get('email'));
            if ( Post::get('ct_bot_detector_event_token') ) {
                $gfa_checked_data['event_token'] = Post::get('ct_bot_detector_event_token');
            }

            $gfa_checked_data['message'] = apbct__filter_form_data($gfa_checked_data['message']);
            if ( isset($gfa_checked_data['message']['apbct_visible_fields']) ) {
                unset($gfa_checked_data['message']['apbct_visible_fields']);
            }

            return $gfa_checked_data;
        }

        return null;
    }

    /**
     * @param $message
     *
     * @return void
     */
    public function doBlock($message)
    {
        wp_send_json_error($message);
    }

    public function allow()
    {
        return $this->return_argument;
    }
}
