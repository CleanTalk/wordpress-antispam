<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Request;
use Cleantalk\Common\TT;

/**
 * Emergency firewall bypass.
 *
 * Flow:
 *  1. NOC fires the `send_fw_bypass_email` remote call. A random one-time ready token is stored
 *     in a transient and a link containing it is sent to the admin email.
 *  2. The admin opens the link. The ready token is consumed (single use) and a random secret is
 *     issued to the browser as an HttpOnly cookie, while only its SHA-256 hash is kept on the server.
 *  3. Every following request is matched by the cookie secret. The firewall is skipped on match.
 *
 * The bypass is bound to a secret known only to the browser that opened the link.
 * It is intentionally NOT bound to IP/User-Agent because both of them are spoofable.
 */
class FirewallBypass
{
    /**
     * Message of the last caught error. Used to report the remote call result back to the NOC.
     *
     * @var string|null
     */
    public static $last_error = null;

    /**
     * Prefix for all plugin-specific keys in the storage and request.
     */
    public const PLUGIN_PREFIX = 'apbct_';

    /**
     * Lifetime of the ready token (the emailed link) in seconds.
     */
    private const GENERATION_TOKEN_EXPIRATION = 600;

    /**
     * Transient key holding the ready token in plain text. It is a short-living one-time value.
     */
    private const GENERATION_TOKEN_TRANSIENT_KEY = self::PLUGIN_PREFIX . 'fw_ready_token';

    /**
     * GET/POST parameter carrying the ready token.
     */
    private const GENERATION_REQUEST_PARAM = self::PLUGIN_PREFIX . 'fw_bypass_token';

    /**
     * Transient key holding the SHA-256 hash of the issued user secret. The secret itself is never stored.
     */
    private const USER_TOKEN_TRANSIENT_KEY = self::PLUGIN_PREFIX . 'bypass_user';

    /**
     * Lifetime of the granted bypass in seconds.
     */
    private const USER_TOKEN_EXPIRATION = 3600;

    /**
     * Name of the cookie holding the user secret.
     */
    private const USER_COOKIE_NAME = self::PLUGIN_PREFIX . 'fw_bypass';

    /**
     * Entry point of the `send_fw_bypass_email` remote call.
     *
     * Generates a one-time ready token, stores it and emails the bypass link to the site admin.
     * Any failure is converted to a false result with the reason placed into self::$last_error.
     *
     * @return bool True if the link has been generated and sent.
     */
    public static function processGenerationRemoteCall()
    {
        // Tracks the ownership of the stored token to avoid dropping a token created by another call.
        $ready_token_saved = false;

        try {
            $ready_token = self::generateToken();
            if ( ! is_string($ready_token) ) {
                throw new \Exception('Failed to generate bypass token.');
            }

            $ready_token_saved = self::saveReadyToken($ready_token);
            if ( ! $ready_token_saved ) {
                throw new \Exception('Failed to save bypass token.');
            }

            if ( ! self::sendAdminEmail($ready_token) ) {
                throw new \Exception('Failed to send admin email.');
            }

            return true;
        } catch (\Exception $e) {
            // Drop the token only if it has been created by this very call.
            if ( $ready_token_saved ) {
                self::removeReadyToken();
            }
            self::$last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Checks whether the current visitor holds a valid bypass secret.
     *
     * Called on every request, so it must stay as cheap as possible: the storage is read
     * only when a well-formed cookie is present.
     *
     * @return bool True if the firewall has to be skipped for this visitor.
     */
    public static function bypassByUserToken()
    {
        $user_secret = self::getUserSecretFromRequest();
        if ( $user_secret === '' ) {
            return false;
        }

        $stored_hash = get_transient(self::USER_TOKEN_TRANSIENT_KEY);
        if ( ! is_string($stored_hash) || $stored_hash === '' ) {
            return false;
        }

        // Constant time comparison to exclude any timing side channel.
        return hash_equals($stored_hash, self::hashSecret($user_secret));
    }

    /**
     * Consumes the one-time ready token from the request and grants the bypass to the current browser.
     *
     * Called on every request right before the firewall check, so it bails out immediately
     * when the request carries no bypass parameter.
     *
     * @return void
     */
    public static function maybeSetUserToken()
    {
        $ready_token_from_request = Request::getString(self::GENERATION_REQUEST_PARAM);

        // Do not touch the storage on regular requests.
        if ( $ready_token_from_request === '' ) {
            return;
        }

        try {
            $ready_token = self::loadReadyToken();
            if ( $ready_token === false ) {
                return;
            }

            // Constant time comparison to exclude any timing side channel.
            if ( ! hash_equals($ready_token, $ready_token_from_request) ) {
                return;
            }

            // The link is single use: invalidate the token before the secret is issued.
            self::removeReadyToken();

            $user_secret = self::generateToken();
            if ( ! is_string($user_secret) ) {
                throw new \Exception('Failed to generate bypass user secret.');
            }

            self::setUserToken($user_secret);
        } catch (\Exception $e) {
            self::$last_error = $e->getMessage();
        }
    }

    /**
     * Grants the bypass: stores the hash of the secret on the server and gives the secret to the browser.
     *
     * Only one bypass may be active per site, a new one replaces the previous.
     *
     * @param string $user_secret Plain secret issued to the browser.
     *
     * @return void
     */
    private static function setUserToken($user_secret)
    {
        $hashed_secret = self::hashSecret($user_secret);
        // Only the hash is persisted, so the storage dump does not allow to reproduce the cookie.
        $transient_set = set_transient(
            self::USER_TOKEN_TRANSIENT_KEY,
            $hashed_secret,
            self::USER_TOKEN_EXPIRATION
        );

        // set transient may fall as update_option with the same value returns false, so we need to check the transient value to be sure that it was set.
        if (!$transient_set) {
            $exist_transient = get_transient(self::USER_TOKEN_TRANSIENT_KEY);
            if ( $exist_transient !== $hashed_secret) {
                self::$last_error = 'Failed to store the bypass user secret.';
                return;
            }
        }

        // The ready token is already burned at this point, so a failed cookie must not abort the grant.
        // The visitor keeps the bypass for the current request, but the browser gets nothing to reuse.
        // setcookie() does not populate the superglobal, fill it to let the very same request pass,
        // before the headers_sent() check, so a failed real cookie still leaves this request bypassed.
        $_COOKIE[self::USER_COOKIE_NAME] = $user_secret;

        // The handler caches the read values, drop the cached miss to make the new secret visible at once.
        unset(Cookie::getInstance()->variables[self::USER_COOKIE_NAME]);

        if ( headers_sent() ) {
            self::$last_error = 'Headers are already sent, the bypass cookie has not been set.';
            return;
        }

        // Cookie::set() routes the value to AltSessions/NoCookie storage depending on the plugin's
        // cookie settings (and no-ops entirely when the API key is not validated), so the browser
        // would never actually receive the secret. The bypass must not depend on any of that:
        // it always uses a real native cookie regardless of the current cookies_type/key_is_ok.
        Cookie::setNativeCookie(
            self::USER_COOKIE_NAME,
            $user_secret,
            time() + self::USER_TOKEN_EXPIRATION,
            '/',
            '',
            is_ssl(),
            true,
            'Lax'
        );
    }

    /**
     * Extracts and validates the bypass secret sent by the browser.
     *
     * @return string The secret or an empty string if it is missing or malformed.
     * @psalm-suppress RedundantCondition
     */
    private static function getUserSecretFromRequest()
    {
        //notice: do not use Cookie::getInstance()->get() here, because it depends on the plugin's cookie settings and may return null
        $secret = isset($_COOKIE[self::USER_COOKIE_NAME]) && is_string($_COOKIE[self::USER_COOKIE_NAME])
            ? $_COOKIE[self::USER_COOKIE_NAME]
            : '';

        // Strict shape check: the value is always a 64 chars hex string produced by self::generateToken().
        if ( ! preg_match('/^[a-f0-9]{64}$/', $secret) ) {
            return '';
        }

        return $secret;
    }

    /**
     * Calculates the server side representation of the user secret.
     *
     * A plain hash without salt is enough here: the secret is a 256-bit random value, so it is not brute forceable.
     *
     * @param string $secret
     *
     * @return string Hex SHA-256 hash.
     */
    private static function hashSecret($secret)
    {
        return hash('sha256', $secret);
    }

    /**
     * Generates a cryptographically secure 256-bit token.
     *
     * @return string|false 64 chars hex string or false if the system has no secure randomness source.
     */
    private static function generateToken()
    {
        try {
            $token = bin2hex(random_bytes(32));
        } catch (\Exception $e) {
            // Not enough entropy.
            return false;
        }

        return $token;
    }

    /**
     * Persists the ready token emailed to the admin.
     *
     * @param string $token
     *
     * @return bool
     */
    private static function saveReadyToken($token)
    {
        return (bool)set_transient(self::GENERATION_TOKEN_TRANSIENT_KEY, $token, self::GENERATION_TOKEN_EXPIRATION);
    }

    /**
     * Reads the currently active ready token.
     *
     * @return string|false The token or false if it is absent, expired or broken.
     */
    private static function loadReadyToken()
    {
        $token = get_transient(self::GENERATION_TOKEN_TRANSIENT_KEY);
        if ( ! is_string($token) || empty($token) ) {
            return false;
        }

        return $token;
    }

    /**
     * Invalidates the ready token.
     *
     * @return void
     */
    private static function removeReadyToken()
    {
        delete_transient(self::GENERATION_TOKEN_TRANSIENT_KEY);
    }

    /**
     * Sends the bypass link to the site admin.
     *
     * @param string $ready_token
     *
     * @return bool
     */
    private static function sendAdminEmail($ready_token)
    {
        $admin_email = self::getAdminEmail();
        if ( empty($admin_email) ) {
            return false;
        }

        return (bool)self::sendEmail($admin_email, self::getEmailSubject(), self::getEmailMessage($ready_token));
    }

    /**
     * Recipient of the bypass link.
     *
     * @return string
     */
    private static function getAdminEmail()
    {
        return ct_get_admin_email();
    }

    /**
     * Builds the plain text body of the notification.
     *
     * @param string $ready_token
     *
     * @return string
     */
    private static function getEmailMessage($ready_token)
    {
        // Raw escaping is used: the message is plain text, HTML entities would break the link.
        $bypass_url = Escape::escUrlRaw(
            add_query_arg(self::GENERATION_REQUEST_PARAM, $ready_token, get_site_url())
        );

        // A single translatable string with numbered placeholders: splitting it would
        // let a translation break sprintf() with a stray percent sign.
        $template = __(
            "A firewall bypass link has been generated for your site."
            . " Use the link below to bypass the firewall:\n\n%1\$s\n\n"
            . "The link is valid for %2\$d minutes and can be used only once."
            . " The bypass will last for %3\$d minutes in the browser that opens the link.",
            'cleantalk-spam-protect'
        );

        return sprintf(
            $template,
            $bypass_url,
            (self::GENERATION_TOKEN_EXPIRATION / 60),
            (self::USER_TOKEN_EXPIRATION / 60)
        );
    }

    /**
     * Subject of the notification.
     *
     * @return string
     */
    private static function getEmailSubject()
    {
        return __('Anti-Spam by CleanTalk plugin: Firewall Bypass Link Generated', 'cleantalk-spam-protect');
    }

    /**
     * Wrapper over wp_mail() to keep the mailer replaceable in tests.
     *
     * @param string $admin_email
     * @param string $subject
     * @param string $message
     *
     * @return bool
     */
    private static function sendEmail($admin_email, $subject, $message)
    {
        return wp_mail($admin_email, $subject, $message);
    }
}
