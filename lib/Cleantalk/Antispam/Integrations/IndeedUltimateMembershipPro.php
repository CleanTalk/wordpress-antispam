<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class IndeedUltimateMembershipPro extends IntegrationBase
{
    private $return_argument;

    public function getDataForChecking($argument)
    {
        $this->return_argument = $argument;

        $input_array = apply_filters('apbct__filter_post', $_POST);

        $user_email = TT::toString(Post::get('user_email'));

        $data = ct_gfa($input_array, $user_email);

        $data['register'] = true;

        return $data;
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }

    public function allow()
    {
        return $this->return_argument;
    }
}
