<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class NewUserApprove extends IntegrationBase
{
    private $return_argument = true;

    public function getDataForChecking($argument)
    {
        $this->return_argument = $argument;

        if (
            (
                apbct_is_plugin_active('new-user-approve/new-user-approve.php')
                ||
                apbct_is_plugin_active('new-user-approve-premium/new-user-approve.php')
            )
            && !empty($_POST)
            && Post::get('user_email')
            && Post::get('user_login')
        ) {
            $input_array = apply_filters('apbct__filter_post', $_POST);
            $data = ct_get_fields_any($input_array);
            $data['register'] = true;

            return $data;
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
        wp_die($message);
    }

    public function allow()
    {
        return $this->return_argument;
    }
}
