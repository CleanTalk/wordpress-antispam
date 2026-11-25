<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class SimpleMembership extends IntegrationBase
{
    public function doPrepareActions($argument)
    {
        return empty($argument);
    }

    /**
     * @psalm-suppress UnusedParam
     */
    public function getDataForChecking($argument)
    {
        return (
            array(
                'email'    => Post::getString('email'),
                'nickname' => Post::getString('user_name'),
            )
        );
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
}
