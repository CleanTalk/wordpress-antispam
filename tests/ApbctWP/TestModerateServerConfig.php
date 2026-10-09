<?php

use Cleantalk\Antispam\Cleantalk;
use Cleantalk\ApbctWP\ModerateServerConfig;

/**
 * Covers the cached moderate server record.
 *
 * Two fields are security relevant: 'ct_work_url' must never be a bare IP (it is
 * reused for the browser-facing pixel URL and would break TLS), and 'ct_resolve_ip'
 * must never outlive the record it belongs to.
 */
class TestModerateServerConfig extends ApbctTestCase
{
    public function setUp(): void
    {
        delete_option(ModerateServerConfig::OPTION_NAME);
    }

    public static function tearDownAfterClass(): void
    {
        delete_option(ModerateServerConfig::OPTION_NAME);
        parent::tearDownAfterClass();
    }

    /**
     * @param array $record
     *
     * @return Cleantalk
     */
    private function hydrateFrom($record)
    {
        update_option(ModerateServerConfig::OPTION_NAME, $record);
        $ct = new Cleantalk();
        ModerateServerConfig::set($ct);

        return $ct;
    }

    public function testHttpsUrlAndPinnedIpAreRestored()
    {
        $ct = $this->hydrateFrom(array(
            'ct_work_url'       => 'https://moderate.cleantalk.org',
            'ct_server_ttl'     => 10,
            'ct_server_changed' => time(),
            'ct_resolve_ip'     => '88.198.153.60',
        ));

        $this->assertSame('https://moderate.cleantalk.org', $ct->work_url);
        $this->assertSame('88.198.153.60', $ct->dns_resolve_ip);
        $this->assertSame(APBCT_MODERATE_URL, $ct->server_url);
    }

    public function testRecordWithoutPinLeavesResolutionAlone()
    {
        $ct = $this->hydrateFrom(array(
            'ct_work_url'       => 'https://moderate3.cleantalk.org',
            'ct_server_ttl'     => 10,
            'ct_server_changed' => time(),
        ));

        $this->assertSame('https://moderate3.cleantalk.org', $ct->work_url);
        $this->assertNull($ct->dns_resolve_ip);
    }

    /**
     * Older versions wrote a bare IP into the URL, which fails certificate
     * verification. Such records must be discarded so a fresh rotation happens.
     */
    public function testLegacyIpUrlIsRejected()
    {
        $ct = $this->hydrateFrom(array(
            'ct_work_url'       => 'https://88.198.153.60',
            'ct_server_ttl'     => 10,
            'ct_server_changed' => time(),
            'ct_resolve_ip'     => '88.198.153.60',
        ));

        $this->assertNull($ct->work_url);
        $this->assertNull($ct->dns_resolve_ip);
    }

    public function testNonHttpsUrlIsRejected()
    {
        $ct = $this->hydrateFrom(array(
            'ct_work_url'       => 'http://moderate.cleantalk.org',
            'ct_server_ttl'     => 10,
            'ct_server_changed' => time(),
        ));

        $this->assertNull($ct->work_url);
    }

    public function testForeignHostIsRejected()
    {
        $ct = $this->hydrateFrom(array(
            'ct_work_url'       => 'https://moderate.cleantalk.org.scum.com',
            'ct_server_ttl'     => 10,
            'ct_server_changed' => time(),
        ));

        $this->assertNull($ct->work_url);
    }

    public function testInvalidPinnedIpIsDropped()
    {
        $ct = $this->hydrateFrom(array(
            'ct_work_url'       => 'https://moderate.cleantalk.org',
            'ct_server_ttl'     => 10,
            'ct_server_changed' => time(),
            'ct_resolve_ip'     => 'not-an-ip',
        ));

        $this->assertSame('https://moderate.cleantalk.org', $ct->work_url);
        $this->assertNull($ct->dns_resolve_ip);
    }

    public function testMissingOptionYieldsEmptyState()
    {
        $ct = new Cleantalk();
        ModerateServerConfig::set($ct);

        $this->assertNull($ct->work_url);
        $this->assertNull($ct->dns_resolve_ip);
        $this->assertSame(APBCT_MODERATE_URL, $ct->server_url);
    }

    public function testSaveSkippedWhenNothingRotated()
    {
        $ct = new Cleantalk();
        $ct->work_url = 'https://moderate4.cleantalk.org';

        ModerateServerConfig::save($ct, false);

        // Nothing rotated -> the option must stay untouched, so a later successful
        // rotation is not shadowed by a stale record.
        $this->assertFalse(get_option(ModerateServerConfig::OPTION_NAME));
    }

    /**
     * Picking a candidate is not proof that it answers. Caching an unverified server
     * would aim every request for the next 24h at a host already known to fail, so a
     * dead-end rotation must clear the record instead of storing a guess.
     */
    public function testUnverifiedRotationClearsRecordInsteadOfCachingIt()
    {
        $ct = new Cleantalk();
        $ct->work_url           = 'https://moderate4.cleantalk.org';
        $ct->server_ttl         = 7;
        $ct->server_state_dirty = true;
        $ct->server_change      = false;

        ModerateServerConfig::save($ct, false);

        $stored = get_option(ModerateServerConfig::OPTION_NAME);
        $this->assertIsArray($stored);
        $this->assertNull($stored['ct_work_url'], 'An unverified server must never be cached');
        $this->assertSame(0, $stored['ct_server_changed'], 'The cache window must not reopen');
    }

    /**
     * rotateModerate() drops the pin before it starts looking for a server. If that
     * rotation ends up finding nothing, the drop must still reach the option --
     * otherwise the stale IP is restored on the next request and keeps forcing
     * CURLOPT_RESOLVE towards a host that may be gone.
     */
    public function testClearedPinDoesNotSurviveDeadEndRotation()
    {
        $pinned = new Cleantalk();
        $pinned->work_url       = 'https://moderate.cleantalk.org';
        $pinned->dns_resolve_ip = '88.198.153.60';
        $pinned->server_change  = true;
        ModerateServerConfig::save($pinned, false);

        $stored = get_option(ModerateServerConfig::OPTION_NAME);
        $this->assertSame('88.198.153.60', $stored['ct_resolve_ip'], 'Precondition');

        // Rotation cleared the pin but never confirmed a replacement.
        $rotated = new Cleantalk();
        $rotated->dns_resolve_ip     = null;
        $rotated->server_state_dirty = true;
        $rotated->server_change      = false;
        ModerateServerConfig::save($rotated, false);

        $this->assertNull(
            get_option(ModerateServerConfig::OPTION_NAME)['ct_resolve_ip'],
            'A dropped pin must not be restorable from the option'
        );
    }

    public function testSaveStoresRotatedState()
    {
        $ct = new Cleantalk();
        $ct->work_url       = 'https://moderate4.cleantalk.org';
        $ct->server_ttl     = 7;
        $ct->dns_resolve_ip = '159.69.51.30';
        $ct->server_change  = true;

        ModerateServerConfig::save($ct, false);

        $stored = get_option(ModerateServerConfig::OPTION_NAME);
        $this->assertIsArray($stored);
        $this->assertSame('https://moderate4.cleantalk.org', $stored['ct_work_url']);
        $this->assertSame(7, $stored['ct_server_ttl']);
        $this->assertSame('159.69.51.30', $stored['ct_resolve_ip']);
    }

    /**
     * A pinned IP must survive a save/load round trip, otherwise the override would
     * be lost on the very next request and the broken resolver hit again.
     */
    public function testPinnedIpSurvivesRoundTrip()
    {
        $saved = new Cleantalk();
        $saved->work_url       = 'https://moderate.cleantalk.org';
        $saved->server_ttl     = 5;
        $saved->dns_resolve_ip = '159.69.51.30';
        $saved->server_change  = true;
        ModerateServerConfig::save($saved, false);

        $loaded = new Cleantalk();
        ModerateServerConfig::set($loaded);

        $this->assertSame('https://moderate.cleantalk.org', $loaded->work_url);
        $this->assertSame('159.69.51.30', $loaded->dns_resolve_ip);
    }

    /**
     * Clearing the pin is how the plugin returns to ordinary name resolution.
     */
    public function testSaveClearsPreviouslyPinnedIp()
    {
        update_option(ModerateServerConfig::OPTION_NAME, array(
            'ct_work_url'       => 'https://moderate.cleantalk.org',
            'ct_server_ttl'     => 5,
            'ct_server_changed' => time(),
            'ct_resolve_ip'     => '88.198.153.60',
        ));

        $ct = new Cleantalk();
        $ct->work_url       = 'https://moderate3.cleantalk.org';
        $ct->server_ttl     = 5;
        $ct->dns_resolve_ip = null;
        $ct->server_change  = true;
        ModerateServerConfig::save($ct, false);

        $reloaded = new Cleantalk();
        ModerateServerConfig::set($reloaded);

        $this->assertNull($reloaded->dns_resolve_ip);
    }

    public function testResetDropsEveryField()
    {
        update_option(ModerateServerConfig::OPTION_NAME, array(
            'ct_work_url'       => 'https://moderate.cleantalk.org',
            'ct_server_ttl'     => 5,
            'ct_server_changed' => time(),
            'ct_resolve_ip'     => '88.198.153.60',
        ));

        ModerateServerConfig::reset();

        $ct = new Cleantalk();
        ModerateServerConfig::set($ct);

        $this->assertNull($ct->work_url);
        $this->assertNull($ct->dns_resolve_ip);
    }

    /**
     * Legacy records were stored without a scheme. getCurrentConfig() must keep them
     * readable -- the https requirement belongs to set(), not to the raw accessor.
     */
    public function testGetCurrentConfigKeepsSchemelessLegacyUrl()
    {
        update_option(ModerateServerConfig::OPTION_NAME, array(
            'ct_work_url'       => 'moderate.cleantalk.org',
            'ct_server_ttl'     => 100,
            'ct_server_changed' => time(),
        ));

        $config = ModerateServerConfig::getCurrentConfig();

        $this->assertSame('moderate.cleantalk.org', $config['ct_work_url']);
    }

    public function testGetCurrentConfigAlwaysReturnsAllKeys()
    {
        $config = ModerateServerConfig::getCurrentConfig();

        $this->assertIsArray($config);
        foreach ( ['ct_work_url', 'ct_server_ttl', 'ct_server_changed', 'ct_resolve_ip'] as $key ) {
            $this->assertArrayHasKey($key, $config);
        }
    }
}
