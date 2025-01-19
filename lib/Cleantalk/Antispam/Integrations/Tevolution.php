<?php

namespace Cleantalk\Antispam\Integrations;

class Tevolution extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        $username = isset($_POST['user_fname']) && is_string($_POST['user_fname']) ? sanitize_text_field($_POST['user_fname']) : '';
        $email = isset($_POST['user_email']) && is_string($_POST['user_email']) ? sanitize_text_field($_POST['user_email']) : '';
        $data = ct_gfa($_POST, $email, $username);
        $data['register'] = true;
        return $data;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
