<?php

namespace Cleantalk\Antispam\Integrations;

class HivePress extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        if (
            ! $this->isThePluginActive() ||
            ! $this->isUserLoggedIn() ||
            ! $apbct->settings['data__protect_logged_in']
        ) {
            do_action('apbct_skipped_request', __FILE__ . ' -> ' . __FUNCTION__ . '(hivepress theme listing integration):' . __LINE__, $_POST);
            return null;
        }

        $sender_email = '';
        $current_user = $this->getCurrentUser();
        if ( ! empty($current_user->data->user_email) ) {
            $sender_email = $current_user->data->user_email;
        }

        $data = ct_gfa_dto(apply_filters('apbct__filter_post', $argument), $sender_email)->getArray();
        return $data;
    }

    public function allow()
    {
        return null;
    }

    public function doBlock($message) # pragma: no cover
    {
        wp_send_json(
            [
                'error' => ['message' => $message],
            ]
        );
        die();
    }

    protected function isThePluginActive() # pragma: no cover
    {
        return apbct_is_plugin_active('hivepress/hivepress.php');
    }

    protected function isUserLoggedIn() # pragma: no cover
    {
        return apbct_is_user_logged_in();
    }

    protected function getCurrentUser() # pragma: no cover
    {
        return wp_get_current_user();
    }
}
