<?php

namespace Cleantalk\Antispam\Integrations;

class LoginSignupPopup extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        if ( isset($_POST['_xoo_el_form']) && $_POST['_xoo_el_form'] === 'register' ) {
            $input_array = apply_filters('apbct__filter_post', $_POST);
            $username = isset($input_array['xoo_el_reg_username'])
                ? sanitize_user($input_array['xoo_el_reg_username'])
                : '';
            $email = isset($input_array['xoo_el_reg_email'])
                ? sanitize_email($input_array['xoo_el_reg_email'])
                : '';
            $data = ct_gfa_dto($input_array, $email, $username)->getArray();
            $data['register'] = true;

            return $data;
        }
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        header('Content-Type: application/json; charset=utf-8');
        $result = array(
            'error'      => 1,
            'error_code' => 'registration-error',
            'notice'     => "<div class='xoo-el-notice-error registration-error-email-exists'>$message</div>",
        );
        die(json_encode($result));
    }
}
