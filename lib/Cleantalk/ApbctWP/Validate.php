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
     * Compares complete mailboxes: "min@example.com" is not found in "admin@example.com",
     * and an email-like display name is not a recipient.
     *
     * @param string|array $recipients "Name <a@example.com>, b@example.com" or a list of such strings
     * @param string $email
     *
     * @return bool
     */
    public static function isEmailInRecipients($recipients, $email)
    {
        $whitespace = " \t\n\r\0\x0B";
        $email = strtolower(trim((string)$email, $whitespace));
        if ( $email === '' ) {
            return false;
        }

        if ( is_array($recipients) ) {
            $recipients = implode(',', array_filter($recipients, 'is_scalar'));
        }

        // Split by "," or ";" outside of quoted display names, \" inside quotes is not a delimiter
        $entries = TT::toArray(
            preg_split('/[,;](?=(?:[^"\\\\]|\\\\.|"(?:[^"\\\\]|\\\\.)*")*$)/', (string)$recipients)
        );
        foreach ( $entries as $entry ) {
            $entry = trim(TT::toString($entry), $whitespace);
            // "Display name <mailbox>" - only the part in angle brackets is the address
            $address = preg_match('/<([^<>]*)>$/', $entry, $matches)
                ? TT::getArrayValueAsString($matches, 1)
                : $entry;
            if ( strtolower(trim($address, $whitespace)) === $email ) {
                return true;
            }
        }

        return false;
    }
}
