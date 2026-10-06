<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Antispam\Cleantalk;
use Cleantalk\Common\TT;

/**
 * Single source of truth for the cached moderate server state.
 *
 * The 'cleantalk_server' option keeps the result of the last successful moderate
 * rotation: which URL to talk to, how long the record is valid and -- only after a
 * 'getaddrinfo_error' -- which IP the hostname must be pinned to.
 *
 * Before this class every caller hydrated the Cleantalk client by hand, which
 * produced six slightly different copies of the same six lines: two of them used
 * '/http:\/\/.+/' and therefore never matched the https moderate URL, one fell back
 * to '' instead of null, and every new option key had to be added in four places.
 *
 * Use set() before the call and save() after it. Nothing else should read or
 * write the option directly -- use getCurrentConfig() if the raw record is needed.
 */
class ModerateServerConfig
{
    /**
     * Name of the WP option holding the state.
     */
    const OPTION_NAME = 'cleantalk_server';

    /**
     * How often the moderate server is rotated, in seconds.
     */
    const ROTATION_PERIOD = 86400;

    /**
     * Hydrate a Cleantalk client with the cached moderate server state.
     *
     * $work_url is accepted only when it survived Sanitize::sanitizeCleantalkServerUrl()
     * AND carries an https scheme. Anything else becomes null, which makes
     * Cleantalk::httpRequest() fall through to a fresh rotation.
     *
     * @param Cleantalk $ct
     *
     * @return void
     */
    public static function set(Cleantalk $ct)
    {
        $config = self::getCurrentConfig();

        $work_url = $config['ct_work_url'] ?? null;

        $ct->server_url     = APBCT_MODERATE_URL;
        $ct->work_url       = is_string($work_url) && preg_match('/^https:\/\/.+/', $work_url)
            ? $work_url
            : null;
        $ct->server_ttl     = $config['ct_server_ttl'] ?? null;
        $ct->server_changed = $config['ct_server_changed'] ?? null;
        $ct->dns_resolve_ip = $config['ct_resolve_ip'] ?? null;
    }

    /**
     * Persist the state back after a call.
     *
     * Three outcomes, driven by the two flags the client raises:
     *
     *  - $server_change: a rotation happened AND the retry succeeded. Only this
     *    state is worth caching, so it is written and the cache window reopens.
     *
     *  - $server_state_dirty alone: a rotation happened but nothing answered. The
     *    candidate is unverified, possibly dead, and caching it would pin every
     *    request for the next day onto a server already known to fail. The record is
     *    cleared instead, so the next call starts from a clean rotation. Clearing
     *    also guarantees a pinned 'ct_resolve_ip' cannot outlive the rotation that
     *    dropped it.
     *
     *  - neither: the cached server answered on the first try. Nothing changed, so
     *    no write happens and the TTL keeps running.
     *
     * @param Cleantalk $ct
     * @param bool $schedule_rotation Re-arm the daily rotation cron. Disabled when
     *                                called from the rotation cron itself.
     *
     * @return void
     */
    public static function save(Cleantalk $ct, $schedule_rotation = true)
    {
        if ( ! $ct->server_change ) {
            // Rotated into a dead end: drop the cache rather than store a guess.
            if ( $ct->server_state_dirty ) {
                self::reset();
            }

            return;
        }

        update_option(
            self::OPTION_NAME,
            array(
                'ct_work_url'       => $ct->work_url,
                'ct_server_ttl'     => $ct->server_ttl,
                'ct_server_changed' => time(),
                'ct_resolve_ip'     => $ct->dns_resolve_ip,
            )
        );

        if ( $schedule_rotation ) {
            $cron = new Cron();
            $cron->updateTask('rotate_moderate', 'apbct_rotate_moderate', self::ROTATION_PERIOD);
        }
    }

    /**
     * Drop the cached state, forcing a fresh rotation on the next call.
     *
     * @return void
     */
    public static function reset()
    {
        update_option(
            self::OPTION_NAME,
            array(
                'ct_work_url'       => null,
                'ct_server_ttl'     => 0,
                'ct_server_changed' => 0,
                'ct_resolve_ip'     => null,
            )
        );
    }

    /**
     * Read the raw cached record, normalising the two fields that must never be
     * trusted blindly.
     *
     * 'ct_work_url' is passed through Sanitize::sanitizeCleantalkServerUrl(), so only
     * genuine moderate/api cleantalk.org hosts survive -- this is what rejects a bare
     * IP left over in the DB by older versions. 'ct_resolve_ip' must be a valid IP and
     * is dropped whenever the work URL itself was rejected, so a stale pin can never
     * outlive the record it belongs to.
     *
     * Scheme is NOT checked here: legacy records were stored without one. The https
     * requirement lives in set(), where the value is actually used.
     *
     * @return array
     */
    public static function getCurrentConfig()
    {
        $ct_server = get_option(self::OPTION_NAME);
        if ( ! is_array($ct_server) ) {
            $ct_server = array(
                'ct_work_url'       => null,
                'ct_server_ttl'     => null,
                'ct_server_changed' => null,
                'ct_resolve_ip'     => null
            );
        }

        $ct_server['ct_work_url'] = Sanitize::sanitizeCleantalkServerUrl(TT::getArrayValueAsString($ct_server, 'ct_work_url'));

        // Pinned IP for CURLOPT_RESOLVE. Dropped together with the work URL, so a stale
        // override can never survive a rotation or an invalidated server record.
        $resolve_ip = TT::getArrayValueAsString($ct_server, 'ct_resolve_ip');
        $ct_server['ct_resolve_ip'] = $ct_server['ct_work_url'] && filter_var($resolve_ip, FILTER_VALIDATE_IP)
            ? $resolve_ip
            : null;

        return $ct_server;
    }
}
