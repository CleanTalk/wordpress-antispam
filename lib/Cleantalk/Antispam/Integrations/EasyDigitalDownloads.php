<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class EasyDigitalDownloads extends IntegrationBase
{
    private $user_data;

    public function getDataForChecking($argument)
    {
        $this->user_data = $argument;

        if (
            Post::get('edd_action') === "user_register" ||
            !empty($argument['user_email'])
        ) {
            /**
             * Filter for POST
             */
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
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }

    public function allow()
    {
        return $this->user_data;
    }
}
