<?php

namespace Cleantalk\Antispam\Integrations;

class EmailSubscribers extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $post_data = apply_filters('apbct__filter_post', $_POST);
        $email = isset($post_data['esfpx_email']) ? $post_data['esfpx_email'] : '';
        $nickname = isset($post_data['esfpx_name']) ? $post_data['esfpx_name'] : '';
        $data = ct_gfa_dto($post_data, $email, $nickname)->getArray();

        return $data;
    }

    public function doBlock($message)
    {
        die(
            json_encode(
                array(
                    'apbct' => array(
                        'blocked'     => true,
                        'comment'     => $message,
                        'stop_script' => apbct__stop_script_after_ajax_checking()
                    )
                )
            )
        );
    }
}
