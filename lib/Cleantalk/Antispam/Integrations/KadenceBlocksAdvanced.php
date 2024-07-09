<?php

namespace Cleantalk\Antispam\Integrations;

class KadenceBlocksAdvanced extends IntegrationBase
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
            'success' => false,
            'show_message' => true
        ];
        wp_send_json_error($data);
    }
}
