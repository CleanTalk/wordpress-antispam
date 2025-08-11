<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class MemberPress extends IntegrationBase
{
    private $return_argument = true;

    public function doPrepareActions($argument)
    {
        if (Post::get('action') === 'mepr_stripe_create_checkout_session') {
            return false;
        }

        return true;
    }

    public function getDataForChecking($argument)
    {
        $this->return_argument = $argument;
        if (
            apbct_is_plugin_active('memberpress/memberpress.php')
            && ! empty($_POST)
            && Post::get('user_email')
            && Post::get('user_login')
        ) {
            /**
             * Filter for POST
             */
            $input_array      = apply_filters('apbct__filter_post', $_POST);
            $data             = ct_get_fields_any($input_array);
            $data['register'] = true;

            return $data;
        }

        return null;
    }

    /**
     * @param $message
     *
     * @return array
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = ! empty($ct_comment) ?: 'Forbidden. Spam registration detected.';

        return array('user_email' => $ct_comment);
    }

    public function allow()
    {
        return $this->return_argument;
    }
}
