<?php

namespace Cleantalk\ApbctWP\ExternalForms;

use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\Variables\Server;

/**
 * Verify Capture Buffer destinations and request sources, independently of legacy JS forms.
 *
 * A signature authorizes an action/method pair, not the submitter; source checks are separate.
 */
class BufferFormSecurity
{
    /**
     * Sign the destination used by the relay, independently of page-cache lifetime.
     *
     * The versioned payload binds the normalized action and method to this site.
     * Visitor binding and expiration are omitted for shared page caches; changing
     * the home URL or WordPress salts invalidates existing signatures.
     *
     * @param string $action
     * @param string $method
     * @return string Lowercase SHA-256 HMAC in hexadecimal form.
     */
    public static function getSignature($action, $method)
    {
        $data = serialize(array(
            'apbct_buffer_form_v1',
            get_home_url(),
            Escape::escUrlRaw($action),
            strtoupper($method),
        ));

        return hash_hmac('sha256', $data, wp_salt('auth'));
    }

    /**
     * Reject malformed signatures before comparing the expected HMAC in constant time.
     *
     * @param string $action
     * @param string $method
     * @param mixed $signature
     * @return bool
     */
    public static function hasValidSignature($action, $method, $signature)
    {
        return is_string($signature)
            && preg_match('/^[a-f0-9]{64}\z/', $signature) === 1
            && hash_equals(self::getSignature($action, $method), $signature);
    }

    /**
     * A present Origin is authoritative, even when it is empty or null.
     *
     * Referer is a fallback only when Origin is absent. Neither missing headers
     * nor a same-site sibling origin establish the required same-origin source.
     *
     * @return bool
     */
    public static function hasTrustedSource()
    {
        $expected = self::getOrigin(get_home_url());
        if ( $expected === false ) {
            return false;
        }

        if ( Server::has('HTTP_ORIGIN') ) {
            return self::getOrigin(Server::getString('HTTP_ORIGIN'), true) === $expected;
        }

        return self::getOrigin(Server::getString('HTTP_REFERER')) === $expected;
    }

    /**
     * Normalize an HTTP(S) URL to scheme, lowercase host and effective port.
     *
     * Referer and home URLs may contain paths; an Origin header must not.
     * Credentials, whitespace and backslashes are rejected instead of repaired.
     *
     * @param mixed $url
     * @param bool $origin_header Whether the value must contain only an origin.
     * @return array{0: string, 1: string, 2: int}|false
     */
    private static function getOrigin($url, $origin_header = false)
    {
        if ( ! is_string($url) || $url === '' || preg_match('/[\x00-\x20\x7F\\\\]/', $url) ) {
            return false;
        }

        $parts = wp_parse_url($url, -1);
        if ( ! is_array($parts)
            || empty($parts['scheme'])
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        if ( ! in_array($scheme, array('http', 'https'), true)
            || ($origin_header && (isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])))
        ) {
            return false;
        }

        return array(
            $scheme,
            strtolower($parts['host']),
            isset($parts['port']) ? $parts['port'] : ($scheme === 'https' ? 443 : 80),
        );
    }
}
