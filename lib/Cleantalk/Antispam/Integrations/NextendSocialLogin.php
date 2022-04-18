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
    }

    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
