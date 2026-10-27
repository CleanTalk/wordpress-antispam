<?php

namespace Cleantalk\Antispam\Integrations;

class LeakyPaywallRegister extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        if (
            ! apbct_is_plugin_active('leaky-paywall/leaky-paywall.php')
        ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(leaky paywall registration integration):' . __LINE__, $_POST);
            return null;
        }

        $event_token = $argument['ct_bot_detector_event_token'] ?? null;
        $email = $argument['email'];

        $dto = ct_gfa_dto($argument, $email);
        $dto->register = true;
        $data = $dto->getArray();
        $data['event_token'] = $event_token;

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        wp_die($message, '', ['response' => 403]);
    }
}
