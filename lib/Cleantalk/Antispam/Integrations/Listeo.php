<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Common\TT;
use Cleantalk\Variables\Post;

class Listeo extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $data = ct_gfa_dto(apply_filters('apbct__filter_post', $_POST))->getArray();
        $data['register'] = true;
        return $data;
    }

    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'registered' => false,
                    'message' => $message,
                )
            )
        );
    }
}
