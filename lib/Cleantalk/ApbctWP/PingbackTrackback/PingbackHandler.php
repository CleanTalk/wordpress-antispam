<?php

namespace Cleantalk\ApbctWP\PingbackTrackback;

/**
 * Blocks incoming Pingback requests.
 *
 * Pingbacks are XML-RPC notifications sent when one website links to another.
 * They are commonly abused to generate spam comments and consume server
 * resources. This handler keeps XML-RPC enabled while disabling only
 * Pingback-related methods.
 */
class PingbackHandler
{
    /**
     * Registers XML-RPC filters required to block Pingbacks.
     */
    public function __construct()
    {
        add_filter('xmlrpc_methods', array($this, 'registerPingbackBlock'));
    }

    /**
     * Replaces Pingback XML-RPC methods with a custom callback
     * that always returns an error response.
     *
     * Other XML-RPC methods remain untouched to preserve compatibility
     * with Jetpack and other third-party integrations.
     *
     * @param array $methods Registered XML-RPC methods.
     *
     * @return array Modified XML-RPC methods list.
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function registerPingbackBlock($methods)
    {
        // Override Pingback-related XML-RPC methods to prevent WordPress from processing incoming Pingback requests.
        if (isset($methods['pingback.ping'])) {
            $methods['pingback.ping'] = array($this, 'blockPingback');
        }

        if (isset($methods['pingback.extensions.getPingbacks'])) {
            $methods['pingback.extensions.getPingbacks'] = array($this, 'blockPingback');
        }

        return $methods;
    }

    /**
     * Returns an XML-RPC error for all incoming Pingback requests.
     *
     * Error code 0x0013 (19) is returned together with a descriptive
     * message indicating that Pingbacks are disabled.
     *
     * @return \IXR_Error|null XML-RPC error object or null if IXR_Error
     *                         is unavailable.
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function blockPingback(...$_args)
    {
        if (class_exists('\IXR_Error')) {
            return new \IXR_Error(
                0x0013,
                'Pingbacks are disabled'
            );
        }
        return null;
    }
}
