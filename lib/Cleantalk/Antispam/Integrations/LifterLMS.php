<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Common\TT;

/**
 * LifterLMS integration.
 * Note: the integration hook is the filter "lifterlms_user_registration_data", LifterLMS applies it
 * for the account registration and for the user created on the checkout.
 */
class LifterLMS extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        // Skip if the data is already invalid
        if ( is_wp_error($argument) ) {
            return null;
        }

        // The registration data validated by LifterLMS
        $posted_data = isset($this->hook_arguments[1]) ? TT::toArray($this->hook_arguments[1]) : $_POST;

        $processed_post = apply_filters('apbct__filter_post', $posted_data);
        $data = ct_gfa_dto($processed_post, TT::getArrayValueAsString($posted_data, 'email_address'))->getArray();

        $data['register'] = true;

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        return new \WP_Error('ct_error', $message);
    }
}
