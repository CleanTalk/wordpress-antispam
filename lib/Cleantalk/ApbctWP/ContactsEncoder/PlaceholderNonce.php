<?php

namespace Cleantalk\ApbctWP\ContactsEncoder;

/**
 * Unguessable nonce for temporary content placeholders.
 * Placeholders without it could be planted in user content and replaced on restore.
 */
class PlaceholderNonce
{
    /**
     * @var string Nonce shared by placeholders of the current request.
     */
    private static $nonce = '';

    /**
     * @return string
     */
    public static function get()
    {
        if (self::$nonce === '') {
            self::$nonce = self::generate();
        }

        return self::$nonce;
    }

    /**
     * @return string
     */
    public static function generate()
    {
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes(16));
            } catch (\Exception $e) {
                // fall through to WP fallback
            }
        }
        if (function_exists('wp_generate_password')) {
            return strtolower(wp_generate_password(32, false));
        }
        return substr(hash('sha256', uniqid((string)mt_rand(), true)), 0, 32);
    }
}
