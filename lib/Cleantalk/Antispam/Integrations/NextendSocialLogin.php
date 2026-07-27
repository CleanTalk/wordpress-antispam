<?php

namespace Cleantalk\Antispam\Integrations;

class NextendSocialLogin extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        /**
         * @psalm-suppress UndefinedClass
         */
        if ( class_exists('\NextendSocialProviderDummy') && $argument instanceof \NextendSocialProviderDummy ) {
            return (
                array(
                    'email'    => $argument->getAuthUserData('email') ?: '',
                    'nickname' => $argument->getAuthUserData('name') ?: '',
                )
            );
        }
        return null;
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }

    /**
     * Checks if the OAuth provider is enabled by verifying the saved configuration.
     *
     * @param string $provider_id The ID of the OAuth provider.
     * @return bool Returns true if the OAuth provider is enabled and properly configured; otherwise, returns false.
     */
    public static function isOAuthProviderEnabled($provider_id)
    {
        if ( ! $provider_id || ! is_string($provider_id) ) {
            return false;
        }
        try {
            $option = get_option($provider_id);
            $object = maybe_unserialize($option);
            return is_array($object) && !empty($object['client_secret']);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
