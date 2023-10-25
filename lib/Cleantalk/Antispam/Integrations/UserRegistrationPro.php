<?php

namespace Cleantalk\Antispam\Integrations;

class UserRegistrationPro extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $ct_post_temp = $_POST;
        if (is_string($ct_post_temp['form_data'])) {
            $decoded = json_decode($ct_post_temp['form_data'], true);
            $ct_post_temp['form_data'] = $decoded !== null ? $decoded : $ct_post_temp['form_data'];
        }
        $data             = ct_gfa($ct_post_temp);
        $data['register'] = true;

        return $data;
    }

    /**
     * @param $message
     *
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        wp_send_json_error(
            array(
                'message' => $message
            )
        );
        die();
    }
}
