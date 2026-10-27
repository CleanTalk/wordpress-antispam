<?php

namespace Cleantalk\ApbctWP\Firewall;

use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;
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
 * Alternative entry point instead of steps 1-2: the control panel posts a signed token (JWT HS256)
 * to admin-post.php with the `ct_fw_bypass` action. The token is signed with the access key and bound
 * to the site, the visitor IP and a short lifetime. A valid token grants the same cookie secret as the link.
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
     * The admin-post.php action the control panel posts the signed token to. Shared by both plugins.
     */
    public const JWT_ACTION = 'ct_fw_bypass';

    /**
     * POST parameter carrying the signed token.
     */
    private const JWT_REQUEST_PARAM = 'ct_fw_bypass_jwt';

    /**
     * Service the token must be issued for to be processed by this plugin.
     */
    private const JWT_SERVICE = 'antispam';

    /**
     * Services of both plugins. A token for the other one is left to its own handler.
     */
    private const JWT_KNOWN_SERVICES = array('antispam', 'security');

    /**
     * Longer values are rejected before any decoding.
     */
    private const JWT_MAX_LENGTH = 2048;

    /**
     * Maximal lifetime of the token in seconds.
     */
    private const JWT_LIFETIME = 300;

    /**
     * Allowed clock difference between the control panel and the site in seconds.
     */
    private const JWT_CLOCK_SKEW = 60;

    /**
     * Prefix of the options claiming the used token IDs (jti). Makes every token single use.
     * The value is the timestamp the claim may be dropped after.
     */
    private const JWT_USED_ID_OPTION_PREFIX = self::PLUGIN_PREFIX . 'fw_bypass_jti_';

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
     * Handler of the `admin_post(_nopriv)_ct_fw_bypass` actions: the control panel posts the signed token here.
     *
     * @return void
     */
    public static function handleJwtRequest()
    {
        $redirect_url = self::processJwtRequest();

        // The token is issued for the other plugin, its handler is next on the same action.
        if ( $redirect_url === null ) {
            return;
        }

        // 303 makes the browser follow with GET, so a page reload does not resubmit the form.
        wp_safe_redirect($redirect_url, 303);
        exit;
    }

    /**
     * Validates the signed token from the request and grants the bypass to the current browser on success.
     *
     * @return string|null URL to send the browser to: the admin area on success, the home page on failure.
     *                     Null when the token is issued for the other plugin.
     */
    public static function processJwtRequest()
    {
        global $apbct;

        self::$last_error = null;
        $failure_url      = home_url('/');

        try {
            $jwt = Post::getString(self::JWT_REQUEST_PARAM);
            if ( $jwt === '' || strlen($jwt) > self::JWT_MAX_LENGTH ) {
                throw new \Exception('The token is missing or too long.');
            }

            $segments = explode('.', $jwt);
            if ( count($segments) !== 3 ) {
                throw new \Exception('The token is malformed.');
            }
            list($header_segment, $payload_segment, $signature_segment) = $segments;

            $payload = self::decodeJwtSegment($payload_segment);
            if (
                ! is_array($payload) ||
                ! isset($payload['svc']) ||
                ! in_array($payload['svc'], self::JWT_KNOWN_SERVICES, true)
            ) {
                throw new \Exception('The token service is unknown.');
            }

            // Routing only, nothing is granted before the signature is verified below.
            // Checking the signature first would reject the other plugin's token, it is signed with another key.
            if ( $payload['svc'] !== self::JWT_SERVICE ) {
                return null;
            }

            $key = TT::toString($apbct->api_key);
            self::verifyJwtSignature($header_segment, $payload_segment, $signature_segment, $key);
            $claims = self::validateJwtPayload($payload);

            // Burn the token before the bypass is granted, so a parallel request can not reuse it.
            self::claimJwtId($claims['jti'], $claims['exp']);

            $user_secret = self::generateToken();
            if ( ! is_string($user_secret) ) {
                throw new \Exception('Failed to generate bypass user secret.');
            }

            self::setUserToken($user_secret);

            // The secret is stored and visible to the current request, otherwise the admin area stays blocked.
            if ( ! self::bypassByUserToken() ) {
                return $failure_url;
            }

            return admin_url();
        } catch (\Exception $e) {
            self::$last_error = $e->getMessage();
            return $failure_url;
        }
    }

    /**
     * Checks the token header and the HMAC-SHA256 signature.
     *
     * @param string $header_segment
     * @param string $payload_segment
     * @param string $signature_segment
     * @param string $key Access key the token is signed with.
     *
     * @return void
     * @throws \Exception
     */
    private static function verifyJwtSignature($header_segment, $payload_segment, $signature_segment, $key)
    {
        if ( $key === '' ) {
            throw new \Exception('The access key is not set.');
        }

        // Strict header: any other algorithm, including "none", is rejected.
        $header = self::decodeJwtSegment($header_segment);
        if ( ! isset($header['alg'], $header['typ']) || $header['alg'] !== 'HS256' || $header['typ'] !== 'JWT' ) {
            throw new \Exception('The token header is not supported.');
        }

        $expected_signature = self::encodeJwtSegment(
            hash_hmac('sha256', $header_segment . '.' . $payload_segment, $key, true)
        );

        // Constant time comparison to exclude any timing side channel.
        if ( ! hash_equals($expected_signature, $signature_segment) ) {
            throw new \Exception('The token signature is invalid.');
        }
    }

    /**
     * Checks the claims of the token with the verified signature.
     *
     * @param array $payload
     *
     * @return array{jti: string, exp: int} The claims needed to burn the token.
     * @throws \Exception
     */
    private static function validateJwtPayload($payload)
    {
        $now        = time();
        $expiration = isset($payload['exp']) && is_int($payload['exp']) ? $payload['exp'] : 0;
        if (
            $expiration < $now - self::JWT_CLOCK_SKEW ||
            // There is no issue time in the token, so the lifetime is limited by the expiration itself.
            $expiration > $now + self::JWT_LIFETIME + self::JWT_CLOCK_SKEW
        ) {
            throw new \Exception('The token is expired or its lifetime is too long.');
        }

        // One key may serve several sites, the token must not work on another one.
        $site_audience = self::getSiteJwtAudience();
        if (
            ! isset($payload['aud']) ||
            ! is_string($payload['aud']) ||
            $site_audience === '' ||
            self::normalizeJwtAudience($payload['aud']) !== $site_audience
        ) {
            throw new \Exception('The token is issued for another site.');
        }

        // The same IP the firewall checks.
        $request_ip = self::canonicalizeJwtIp(TT::toString(Helper::ipGet('real', false)));
        if (
            ! isset($payload['ip']) ||
            ! is_string($payload['ip']) ||
            $request_ip === '' ||
            self::canonicalizeJwtIp($payload['ip']) !== $request_ip
        ) {
            throw new \Exception('The token is issued for another IP.');
        }

        $jti = isset($payload['jti']) && is_string($payload['jti']) ? $payload['jti'] : '';
        if ( ! preg_match('/^[a-f0-9]{32}$/', $jti) ) {
            throw new \Exception('The token ID is malformed.');
        }

        return array('jti' => $jti, 'exp' => $expiration);
    }

    /**
     * Claims the token ID, so the token can be used only once.
     *
     * The claim is a single INSERT IGNORE on the unique option name: of several parallel requests
     * with the same token exactly one inserts the row. Transients can not do this, a check and a write
     * there are separate queries. Any storage failure rejects the token.
     *
     * @param string $jti
     * @param int    $expiration Token expiration timestamp.
     *
     * @return void
     * @throws \Exception
     */
    private static function claimJwtId($jti, $expiration)
    {
        global $wpdb;

        // The expired claims are useless, drop them to keep the table clean.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d",
                $wpdb->esc_like(self::JWT_USED_ID_OPTION_PREFIX) . '%',
                time()
            )
        );

        $claimed = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
                self::JWT_USED_ID_OPTION_PREFIX . hash('sha256', $jti),
                (string)($expiration + self::JWT_CLOCK_SKEW)
            )
        );

        if ( $claimed === false ) {
            throw new \Exception('Failed to claim the token ID.');
        }

        if ( $claimed !== 1 ) {
            throw new \Exception('The token has already been used.');
        }
    }

    /**
     * Decodes a base64url encoded JSON segment of the token.
     *
     * @param string $segment
     *
     * @return array|null Null if the segment is not a valid base64url JSON object.
     */
    private static function decodeJwtSegment($segment)
    {
        if ( ! preg_match('/^[A-Za-z0-9_-]+$/', $segment) ) {
            return null;
        }

        $remainder = strlen($segment) % 4;
        if ( $remainder ) {
            $segment .= str_repeat('=', 4 - $remainder);
        }

        $json = base64_decode(strtr($segment, '-_', '+/'), true);
        if ( $json === false ) {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param string $data Raw binary data.
     *
     * @return string Base64url without padding.
     */
    private static function encodeJwtSegment($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * The audience the token must be issued for: the host with the path of the site home URL.
     *
     * The path matters: subsites of a subdirectory multisite share the host and the access key,
     * so a token for "example.com/site-a" must not work on "example.com/site-b".
     * A site in the root of the domain has no path, its audience is the host only.
     *
     * @return string Empty string if the home URL has no host.
     */
    private static function getSiteJwtAudience()
    {
        $home_url = home_url();
        $host     = parse_url($home_url, PHP_URL_HOST);
        $path     = parse_url($home_url, PHP_URL_PATH);

        if ( ! is_string($host) || $host === '' ) {
            return '';
        }

        return self::normalizeJwtAudience($host . (is_string($path) ? $path : ''));
    }

    /**
     * Brings the audience to the form the control panel puts into the token:
     * lower case, without "www." and the trailing slash, e.g. "example.com" or "example.com/site-a".
     *
     * @param string $audience
     *
     * @return string
     */
    private static function normalizeJwtAudience($audience)
    {
        $audience = strtolower(trim($audience, " \n\r\t\v\x00"));
        $audience = strpos($audience, 'www.') === 0 ? substr($audience, 4) : $audience;

        return rtrim($audience, '/');
    }

    /**
     * Brings the IP to the canonical comparable form: the packed binary address in hex.
     *
     * Equivalent notations ("2001:DB8::1", "2001:0db8:0:0:0:0:0:1") give the same result.
     * An IPv4-mapped IPv6 address ("::ffff:203.0.113.10") is the same client as the plain IPv4 one.
     *
     * @param string $ip
     *
     * @return string Empty string for an invalid IP.
     */
    private static function canonicalizeJwtIp($ip)
    {
        $ip = trim($ip, " \n\r\t\v\x00");
        if ( filter_var($ip, FILTER_VALIDATE_IP) === false ) {
            return '';
        }

        $packed = inet_pton($ip);
        if ( $packed === false ) {
            return '';
        }

        $ipv4_mapped_prefix = str_repeat("\x00", 10) . "\xff\xff";
        if ( strlen($packed) === 16 && strpos($packed, $ipv4_mapped_prefix) === 0 ) {
            $packed = substr($packed, 12);
        }

        return bin2hex($packed);
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
