<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class BravePopUpPro extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        $formData = json_decode(stripslashes(Post::getString('formData')), true);
        if ($formData) {
            return ct_gfa_dto(apply_filters('apbct__filter_post', $formData))->getArray();
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        print_r(wp_json_encode(array('sent' => false, 'error' => $message)));
        wp_die();
    }
}
