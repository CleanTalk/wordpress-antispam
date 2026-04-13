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
            ! $this->isThePluginActive() ||
            ! isset($argument['email'])
        ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(hivepress registration integration):' . __LINE__, $_POST);
            return null;
        }

        return [
            'email' => $argument['email'],
            'register' => true
        ];
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

    protected function isThePluginActive()
    {
        return apbct_is_plugin_active('hivepress/hivepress.php');
    }
}
