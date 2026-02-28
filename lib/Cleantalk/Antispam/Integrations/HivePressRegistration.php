<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Antispam\Integrations\IntegrationBase;

class HivePressRegistration extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        if (
            ! apbct_is_plugin_active('hivepress/hivepress.php') ||
            ! isset($argument['email'])
        ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(hivepress registration integration):' . __LINE__, $_POST);
            return null;
        }

        $data['email'] = $argument['email'];
        $data['register'] = true;

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        wp_send_json(
            [
                'error' => ['message' => $message],
            ]
        );
        die();
    }
}
