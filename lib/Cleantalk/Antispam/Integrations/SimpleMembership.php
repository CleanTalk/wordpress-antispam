<?php

namespace Cleantalk\Antispam\Integrations;

class SimpleMembership extends IntegrationBase
{
    private $return_argument;

    public function getDataForChecking($argument)
    {
        $this->return_argument = $argument;

        // The registration is already rejected
        if ( ! empty($argument) ) {
            return null;
        }

        // The registration form fields
        return (
            array(
                'email'    => isset($_POST['email']) && is_string($_POST['email'])
                    ? sanitize_email(wp_unslash($_POST['email']))
                    : '',
                'nickname' => isset($_POST['user_name']) && is_string($_POST['user_name'])
                    ? sanitize_text_field(wp_unslash($_POST['user_name']))
                    : '',
            )
        );
    }

    /**
     * @param $message
     *
     * @psalm-suppress UnusedVariable
     */
    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;

        ct_die(null, null);
    }

    public function allow()
    {
        return $this->return_argument;
    }
}
