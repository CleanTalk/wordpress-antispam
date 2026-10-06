<?php

use Cleantalk\Antispam\Cleantalk;

/**
 * Covers the DNS override recovery path.
 *
 * The moderate hostname must always stay in the URL: the CleanTalk certificate
 * carries only 'CN=*.cleantalk.org' / 'SAN: *.cleantalk.org, cleantalk.org' and no
 * IP SANs, so a bare IP in the URL fails verification with cURL error 60. The IP is
 * therefore carried separately and applied through CURLOPT_RESOLVE.
 */
class CleantalkDnsOverrideTest extends ApbctTestCase
{
    /**
     * @param Cleantalk $ct
     * @param string $method
     * @param array $args
     *
     * @return mixed
     */
    private function callPrivate($ct, $method, $args = array())
    {
        $ref = new ReflectionMethod(Cleantalk::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($ct, $args);
    }

    public function testNoResolveOptionWithoutOverride()
    {
        $ct = new Cleantalk();

        $this->assertNull($ct->dns_resolve_ip, 'Override must be off by default');
        $this->assertNull(
            $this->callPrivate($ct, 'buildResolveOption', ['https://moderate.cleantalk.org/api2.0']),
            'Without a pinned IP the request must go out with plain name resolution'
        );
    }

    public function testResolveEntryUsesImplicitHttpsPort()
    {
        $ct = new Cleantalk();
        $ct->dns_resolve_ip = '88.198.153.60';

        $this->assertSame(
            ['moderate.cleantalk.org:443:88.198.153.60'],
            $this->callPrivate($ct, 'buildResolveOption', ['https://moderate.cleantalk.org/api2.0'])
        );
    }

    public function testResolveEntryUsesImplicitHttpPort()
    {
        $ct = new Cleantalk();
        $ct->dns_resolve_ip = '88.198.153.60';

        $this->assertSame(
            ['moderate.cleantalk.org:80:88.198.153.60'],
            $this->callPrivate($ct, 'buildResolveOption', ['http://moderate.cleantalk.org/api2.0'])
        );
    }

    public function testResolveEntryKeepsExplicitPort()
    {
        $ct = new Cleantalk();
        $ct->dns_resolve_ip = '88.198.153.60';

        $this->assertSame(
            ['moderate.cleantalk.org:8443:88.198.153.60'],
            $this->callPrivate($ct, 'buildResolveOption', ['https://moderate.cleantalk.org:8443/api2.0'])
        );
    }

    public function testResolveEntryIsNullForUrlWithoutHost()
    {
        $ct = new Cleantalk();
        $ct->dns_resolve_ip = '88.198.153.60';

        $this->assertNull(
            $this->callPrivate($ct, 'buildResolveOption', ['moderate.cleantalk.org/api2.0'])
        );
    }

    /**
     * Candidates share one URL under an override, so the pinned IP has to be part of
     * the down-server key. Otherwise the first failed IP would blacklist all the rest.
     */
    public function testDownServerKeyIncludesPinnedIp()
    {
        $ct = new Cleantalk();
        $ct->work_url = 'https://moderate.cleantalk.org';

        $this->assertSame(
            'https://moderate.cleantalk.org',
            $this->callPrivate($ct, 'getCurrentTargetKey')
        );

        $ct->dns_resolve_ip = '88.198.153.60';
        $first = $this->callPrivate($ct, 'getCurrentTargetKey');

        $ct->dns_resolve_ip = '159.69.51.30';
        $second = $this->callPrivate($ct, 'getCurrentTargetKey');

        $this->assertNotSame($first, $second, 'Each pinned IP must be a distinct target');
    }

    public function testTargetDescriptionMentionsPinnedIp()
    {
        $ct = new Cleantalk();
        $ct->work_url = 'https://moderate.cleantalk.org';

        $this->assertSame(
            'https://moderate.cleantalk.org',
            $this->callPrivate($ct, 'describeCurrentTarget')
        );

        $ct->dns_resolve_ip = '88.198.153.60';

        $this->assertStringContainsString(
            '88.198.153.60',
            $this->callPrivate($ct, 'describeCurrentTarget'),
            'Connection reports must show which IP was actually used'
        );
    }

    /**
     * Error classification drives the recovery strategy, so the mapping is load-bearing:
     * only 'getaddrinfo_error' may trigger the DNS override.
     */
    public function testGetTypeErrorClassifiesGetaddrinfoFailure()
    {
        $ct = new Cleantalk();
        $result = (object)['errstr' => 'cURL error 6: getaddrinfo() thread failed to start'];

        $this->assertSame('getaddrinfo_error', $this->callPrivate($ct, 'getTypeError', [$result]));
    }

    public function testGetTypeErrorClassifiesTimeout()
    {
        $ct = new Cleantalk();
        $result = (object)['errstr' => 'cURL error 28: Operation timed out after 15000 milliseconds'];

        $this->assertSame('connection_timeout', $this->callPrivate($ct, 'getTypeError', [$result]));
    }

    public function testGetTypeErrorFallsBackToUnknown()
    {
        $ct = new Cleantalk();

        $this->assertSame(
            'unknown',
            $this->callPrivate($ct, 'getTypeError', [(object)['errstr' => 'cURL error 60: SSL certificate problem']])
        );
        $this->assertSame('unknown', $this->callPrivate($ct, 'getTypeError', [false]));
    }

    /**
     * Rotation must never claim success on its own: it only picks a candidate, and
     * whether that candidate answers is decided later by httpRequest(). An
     * unresolvable host is used so the method runs its whole body and still ends
     * without a pick.
     */
    public function testRotationMarksStateDirtyWithoutClaimingSuccess()
    {
        $ct = new Cleantalk();
        $ct->server_url = 'https://moderate.cleantalk.org';

        if ( empty($ct->getServersIp('moderate.cleantalk.org')) ) {
            $this->markTestSkipped('No DNS in this environment, rotation cannot run');
        }

        $ct->dns_resolve_ip = '88.198.153.60';

        $ct->rotateModerate();

        $this->assertNull($ct->dns_resolve_ip, 'Rotation must drop any pinned IP');
        $this->assertTrue($ct->server_state_dirty, 'The dropped pin has to reach the option');
        $this->assertFalse(
            $ct->server_change,
            'Only a confirmed response may mark the server as cacheable'
        );
    }

    /**
     * Same contract for the DNS override path: pinning an IP is a state change, not
     * a confirmation. Without this the dead server would be cached for a full day.
     */
    public function testDnsOverrideMarksStateDirtyWithoutClaimingSuccess()
    {
        $ct = new Cleantalk();
        $ct->server_url = 'https://moderate.cleantalk.org';

        if ( empty($ct->getServersIp('moderate.cleantalk.org')) ) {
            $this->markTestSkipped('No DNS in this environment, rotation cannot run');
        }

        $ct->rotateModerateWithDnsOverride();

        $this->assertTrue($ct->server_state_dirty, 'Pinning an IP must reach the option');
        $this->assertFalse(
            $ct->server_change,
            'Only a confirmed response may mark the server as cacheable'
        );

        if ( defined('CURLOPT_RESOLVE') ) {
            $this->assertNotEmpty($ct->dns_resolve_ip, 'The override must actually pin an IP');
            $this->assertStringNotContainsString(
                (string)$ct->dns_resolve_ip,
                (string)$ct->work_url,
                'The IP must never leak into the URL, it breaks certificate verification'
            );
        }
    }

    /**
     * A refused port, so sendRequest() fails immediately instead of waiting for a
     * timeout. Only the transport decision is under test here, not the response.
     */
    const DEAD_URL = 'https://127.0.0.1:1';

    /**
     * The pinned IP is persisted, so it comes back on every later request without
     * any rotation happening. CURLOPT_RESOLVE exists only in the cURL transport --
     * if the WP HTTP API picked fsockopen/streams it would silently drop the
     * override and resolve the hostname through the very resolver being worked
     * around. The transport must therefore be forced wherever the override is
     * applied, not where it was created.
     */
    public function testPinnedIpForcesCurlTransport()
    {
        global $apbct;

        $apbct->settings['wp__use_builtin_http_api'] = 1;

        $ct = new Cleantalk();
        $ct->dns_resolve_ip = '88.198.153.60';

        $this->callPrivate($ct, 'sendRequest', [['method_name' => 'check_message'], self::DEAD_URL, 1]);

        $this->assertFalse(
            $apbct->settings['wp__use_builtin_http_api'],
            'A request carrying CURLOPT_RESOLVE must not be sent through the WP HTTP API'
        );
    }

    /**
     * Without an override there is nothing a non-cURL transport could lose, so the
     * user's own transport preference must be left untouched.
     */
    public function testPlainRequestKeepsTransportPreference()
    {
        global $apbct;

        $apbct->settings['wp__use_builtin_http_api'] = 0;

        $ct = new Cleantalk();

        $this->callPrivate($ct, 'sendRequest', [['method_name' => 'check_message'], self::DEAD_URL, 1]);

        $this->assertSame(
            0,
            $apbct->settings['wp__use_builtin_http_api'],
            'Transport preference must only change when an override is actually applied'
        );
    }
}
