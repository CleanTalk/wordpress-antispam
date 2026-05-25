<?php

namespace Cleantalk\Antispam\Integrations;

/**
 * Plugin: User Registration & Membership
 */
class UserRegistrationPro extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        $ct_post_temp = apply_filters('apbct__filter_post', $_POST);
        if (isset($ct_post_temp['form_data']) && is_string($ct_post_temp['form_data'])) {
            $decoded = json_decode(stripslashes($ct_post_temp['form_data']), true);
            $ct_post_temp['form_data'] = $decoded !== null ? $decoded : $ct_post_temp['form_data'];
        }

        // Extract email and username from form_data
        $email = '';
        $nickname = '';
        if (isset($ct_post_temp['form_data']) && is_array($ct_post_temp['form_data'])) {
            foreach ($ct_post_temp['form_data'] as $field) {
                if (isset($field['field_name'], $field['value'])) {
                    if ($field['field_name'] === 'user_email') {
                        $email = $field['value'];
                    } elseif ($field['field_name'] === 'user_login') {
                        $nickname = $field['value'];
                    }
                }
            }
        }

        $data             = ct_gfa_dto($ct_post_temp, $email, $nickname)->getArray();
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
