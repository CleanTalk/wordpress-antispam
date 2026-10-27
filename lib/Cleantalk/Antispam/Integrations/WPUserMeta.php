<?php

namespace Cleantalk\Antispam\Integrations;

use WP_Error;

class WPUserMeta extends IntegrationBase
{
    private $return_argument = true;

    public function getDataForChecking($argument)
    {
        $this->return_argument = $argument;
        if (
            (
                apbct_is_plugin_active('user-meta/user-meta.php')
                ||
                apbct_is_plugin_active('user-meta-pro/user-meta.php')
            )
            && is_array($argument)
        ) {
            // Check the user data the plugin is going to save
            $email = isset($argument['user_email']) && is_string($argument['user_email']) ? $argument['user_email'] : '';
            $nickname = isset($argument['user_login']) && is_string($argument['user_login']) ? $argument['user_login'] : '';

            /**
             * Filter for POST
             */
            $input_array = apply_filters('apbct__filter_post', $argument);
            $data = ct_get_fields_any($input_array, $email, $nickname);
            $data['register'] = true;

            return $data;
        }

        return null;
    }

    /**
     * @param $message
     *
     * @return WP_Error
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        return new WP_Error('invalid_email', $message);
    }

    public function allow()
    {
        return $this->return_argument;
    }
}
