<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Get;

class GiveWP extends IntegrationBase
{
    private $is_rest = false;
    public function getDataForChecking($argument)
    {
        $this->is_rest = Get::equal('givewp-route', 'validate');
        if (
            Post::get('action') === 'give_process_donation' ||
            (
                in_array(Post::get('give_action'), ['donation', 'purchase']) &&
                Post::get('give_ajax')
            ) ||
            $this->is_rest
        ) {
            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $_POST);
            $nickname = '';
            $email = '';
            if ($this->is_rest) {
                $nickname = Post::getString('firstName');
                $nickname = empty($nickname)
                    ? Post::getString('lastName')
                    : $nickname . ' ' . Post::getString('lastName');
                $email = Post::getString('email');
            }

            //multipage form exclusion, first page of donation amount does not provide email data
            $gfa_dto = ct_gfa_dto($input_array, $email, $nickname);
            if (!empty($gfa_dto->email)) {
                return $gfa_dto->getArray();
            }
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
        if ($this->is_rest) {
            $error = array(
                'success' => false,
                'data' => array(
                    "type" => "validation_error",
                    'errors' => array(
                        'errors' => array(
                            'email' => [$message]
                        ),
                        'error_data' => [],
                    ),
                ),
            );
            wp_send_json($error);
        }
        if ( function_exists('give_set_error') ) {
            give_set_error('spam_donation', $message);
        }
        add_action('give_ajax_donation_errors', function () use ($message) {
            return 'Error: ' . $message;
        });
    }
}
