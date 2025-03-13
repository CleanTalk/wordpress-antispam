<?php

namespace Cleantalk\Antispam;

use Cleantalk\ApbctWP\Variables\Server;

class ProtectByShortcode
{
    public function __construct()
    {
        add_filter('apbct_wordpress_protect_from_spam', array($this, 'protectByShortcode'), 10, 2);
    }

    /**
     * A function to protect the data of $_POST, $_GET custom forms by the apbct_wordpress_protect_from_spam hook.
     * Returns an array with the result of the check. Also, if $options['redirect_to_block_page'] = 1 is passed,
     * a redirect will be made to the blocking page. Additionaly, if $options['is_register'] = 1 is passed,
     * the function will process the data as a registration form.
     * @param array $data
     * @param array $options
     * @return array
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function protectByShortcode($data, $options = [])
    {
        $output = [
            'is_spam' => false,
            'message' => '',
        ];

        $input_array = apply_filters('apbct__filter_post', $data);
        $data = ct_gfa($input_array);

        $base_call_data = array(
            'message'         => ! empty($data['message']) ? json_encode($data['message']) : '',
            'sender_email'    => ! empty($data['email']) ? $data['email'] : '',
            'sender_nickname' => ! empty($data['nickname']) ? $data['nickname'] : '',
            'event_token'     => ! empty($data['event_token']) ? $data['event_token'] : '',
            'post_info'       => array(
                'post_url'     => Server::get('HTTP_REFERER'),
            ),
        );
        $is_register = isset($options['is_register']) && $options['is_register'] === true ? true : false;
        $result = apbct_base_call($base_call_data, $is_register);
        $ct_result = isset($result['ct_result']) ? $result['ct_result'] : null;

        $is_spam = $ct_result !== null && $ct_result->allow !== 1;

        if (isset($options['redirect_to_block_page']) && $options['redirect_to_block_page'] && $is_spam) {
            wp_die(isset($ct_result->comment) ? $ct_result->comment : __('Blocked by CleanTalk'), __('Forbidden'), array('response' => 403));
        }

        if ($is_spam) {
            $output = [
                'is_spam' => true,
                'message' => $ct_result->comment,
            ];
        }

        return $output;
    }
}
