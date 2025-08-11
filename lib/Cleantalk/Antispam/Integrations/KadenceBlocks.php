<?php

namespace Cleantalk\Antispam\Integrations;

class KadenceBlocks extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        return ct_gfa(apply_filters('apbct__filter_post', $_POST));
    }

    public function doBlock($message)
    {
        $data = [
            'redirect' => false,
            'html' => '<div class="kadence-blocks-form-message kadence-blocks-form-error">' . $message . '</div>',
            'headers_sent' => false,
            'success' => true,
            'show_message' => true
        ];
        wp_send_json($data);
    }
}
