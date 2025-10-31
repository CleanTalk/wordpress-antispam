<?php

namespace Cleantalk\Antispam\Integrations;

class WpForo extends IntegrationBase
{
    /**
     * @param $argument
     *
     * @return array|mixed
     * @psalm-suppress UndefinedFunction
     */
    public function getDataForChecking($argument)
    {
        $email    = '';
        $nickname = '';

        //try to get nick and email
        try {
            $current_wpf_userid = \WPF()->current_userid;
            $current_wpf_user   = wpforo_member($current_wpf_userid);
            $email              = isset($current_wpf_user['user_email']) ? $current_wpf_user['user_email'] : '';
            $nickname           = isset($current_wpf_user['user_login']) ? $current_wpf_user['user_login'] : '';
        } catch (\Exception $_e) {
            if ( function_exists('apbct_wp_get_current_user') ) {
                $current_user = apbct_wp_get_current_user();
                $email        = $current_user instanceof \WP_User ? $current_user->data->user_email : '';
            }
        }

        //main logic fires on not empty email
        if ( ! empty($email) ) {
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST);
            $message     = '';

            // get topics
            if ( isset($input_array['thread'], $input_array['thread']['body']) ) {
                $message = $input_array['thread']['body'];
            }

            // get posts
            if ( isset($input_array['post'], $input_array['post']['body']) ) {
                $message = $input_array['post']['body'];
            }

            //clear message
            if ( function_exists('wp_strip_all_tags') ) {
                $message = wp_strip_all_tags($message);
            } elseif ( function_exists('strip_tags') ) {
                $message = strip_tags($message);
            }

            //get GFA
            $gfa = ct_gfa_dto($input_array, $email, $nickname);
            $gfa_array = $gfa->getArray();
            //add msg
            if (!isset($gfa_array['message'])) {
                $gfa_array['message'] = [];
            }

            // thread/post['body'] is not visible in apbct visible fields, so do hardcode
            $gfa_array['message']['body'] = $message;
            $gfa_array['message'] = implode(' ', array_values($gfa_array['message']));

            return $gfa_array;
        }

        return array();
    }

    public function doBlock($message)
    {
        wp_die($message);
    }
}
