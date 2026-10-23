<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Common\TT;

class Validate extends \Cleantalk\Common\Validate
{
    public static function isEmail($variable)
    {
        return is_email($variable);
    }

    public static function isValidFilePath($variable)
    {
        return validate_file($variable);
    }

    /**
     * Whether the email is one of the recipients.
     * Compares complete addresses, so "min@example.com" is not found in "admin@example.com".
     *
     * @param string|array $recipients "Name <a@example.com>, b@example.com" or a list of such strings
     * @param string $email
     *
     * @return bool
     */
    public static function isEmailInRecipients($recipients, $email)
    {
        $email = strtolower(trim((string)$email, " \t\n\r\0\x0B"));
        if ( $email === '' ) {
            return false;
        }

        if ( is_array($recipients) ) {
            $recipients = implode(',', array_filter($recipients, 'is_scalar'));
        }

        preg_match_all('/[^\s<>,;"\']+@[^\s<>,;"\']+/', (string)$recipients, $matches);
        foreach ( TT::getArrayValueAsArray($matches, 0) as $address ) {
            if ( strtolower(TT::toString($address)) === $email ) {
                return true;
            }
        }

        return false;
    }
}
