<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class WpGeoDirectory extends IntegrationBase
{
    private $return_argument = true;

    public function getDataForChecking($argument)
    {
        $this->return_argument = $argument;

        $is_auto_save = current_filter() === 'geodir_validate_auto_save_post_data';
        if ( $is_auto_save ) {
            // The auto save writes into the listing itself if it is not a revision and not a new draft
            $post_data = isset($this->hook_arguments[1]) ? TT::toArray($this->hook_arguments[1]) : array();
            $post_id = isset($post_data['ID']) ? absint($post_data['ID']) : 0;
            if ( ! empty($post_data['post_parent']) || ! $post_id || get_post_status($post_id) === 'auto-draft' ) {
                return null;
            }
        }

        if (
            (
                apbct_is_plugin_active('geodirectory/geodirectory.php') &&
                (Post::get('action') === 'geodir_save_post' || $is_auto_save)
            )
        ) {
            $post_data = $is_auto_save ? $post_data : $_POST;
            apbct_form__get_no_cookie_data($post_data);
            // The guest account is created with user_email
            $email = Post::getString('user_email') !== '' ? Post::getString('user_email') : TT::toString(Post::get('email'));
            $input_array = apply_filters('apbct__filter_post', $post_data);
            $gfa_checked_data = ct_gfa($input_array, $email);
            if ( Post::get('ct_bot_detector_event_token') ) {
                $gfa_checked_data['event_token'] = Post::get('ct_bot_detector_event_token');
            }

            $gfa_checked_data['message'] = isset($gfa_checked_data['message']) ? apbct__filter_form_data($gfa_checked_data['message']) : '';
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
