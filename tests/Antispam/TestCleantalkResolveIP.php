<?php

use Cleantalk\Antispam\Cleantalk;
use Cleantalk\Common\HTTP\Request as CommonRequest;

/**
 * Replaces the two DNS-dependent seams of the fallback with fixtures.
 */
class ApbctRotateModerateStub extends Cleantalk
{
    /**
     * @var array Value returned instead of a real A-record lookup.
     */
    public $servers_fixture = array();

    /**
     * @var array IP => PTR hostname. Missing key means the IP has no usable PTR.
     */
    public $ptr_map = array();

    public function getServersIp($host)
    {
        return $this->servers_fixture;
    }

    protected function resolvePtr($ip)
    {
        return isset($this->ptr_map[$ip]) ? $this->ptr_map[$ip] : false;
    }
}

/**
 * Exposes prepared cURL options without performing a network request.
 */
class ApbctResolveOptionsHarness extends CommonRequest
{
    public function prepareOptionsForTest()
    {
        $convert = new \ReflectionMethod(CommonRequest::class, 'convertOptionsTocURLFormat');
        $convert->setAccessible(true);
        $convert->invoke($this);

        $this->appendOptionsObligatory();
        $this->processPresets();

        return $this->options;
    }
}

/**
 * Regression guard for the DNS-failure fallback.
 *
 * When moderate*.cleantalk.org can not be resolved, the plugin must keep the hostname
 * in the URL and pin it to the selected IP via CURLOPT_RESOLVE, so SNI and the TLS
 * certificate hostname check keep working.
 *
 * Historical bugs covered here:
 *  - the request was sent to https://<IP>, which fails with
 *    "SSL: no alternative certificate subject name matches target host name";
 *  - the literal IP request was made with TLS verification switched off;
 *  - CURLOPT_RESOLVE was never set, because the guard required an explicit port
 *    that a normal API URL does not have, so the fallback was dead code;
 *  - an explicit non-default port was overwritten with 443;
 *  - an IP without a PTR record was skipped, even though it is a valid A record
 *    of the pool hostname and the fallback pins it by IP anyway;
 *  - deduplication of failed nodes by URL discards every candidate as soon as the
 *    URL stops being node-specific.
 */
class TestCleantalkResolveIP extends ApbctTestCase
{
    const IP = '167.71.167.197';
    const HOST = 'moderate2.cleantalk.org';
    const IP_SECOND = '159.69.57.9';
    const HOST_SECOND = 'moderate8.cleantalk.org';
    const POOL_HOST = 'moderate.cleantalk.org';
    const POOL_URL = 'https://moderate.cleantalk.org';

    /**
     * @var Cleantalk
     */
    private $ct;

    public function setUp(): void
    {
        $this->ct = new Cleantalk();
    }

    /**
     * The main regression: a normal API URL has no explicit port, so the mapping
     * must fall back to the scheme default instead of being skipped.
     */
    public function testHttpsUrlWithoutPortIsPinnedToIpOnDefaultPort()
    {
        $this->assertSame(
            self::HOST . ':443:' . self::IP,
            $this->ct->maybeResolveIPInsteadOfHost('https://' . self::HOST . '/api2.0', self::IP)
        );
    }

    public function testExplicitPortIsPreserved()
    {
        $this->assertSame(
            self::HOST . ':8443:' . self::IP,
            $this->ct->maybeResolveIPInsteadOfHost('https://' . self::HOST . ':8443/api2.0', self::IP)
        );
    }

    public function testPlainHttpUrlUsesPort80()
    {
        $this->assertSame(
            self::HOST . ':80:' . self::IP,
            $this->ct->maybeResolveIPInsteadOfHost('http://' . self::HOST . '/api2.0', self::IP)
        );
    }

    /**
     * The hostname must stay in the mapping: it is what SNI and the certificate
     * check are validated against.
     */
    public function testHostnameIsKeptAndNotReplacedByIp()
    {
        $resolve = $this->ct->maybeResolveIPInsteadOfHost('https://' . self::HOST . '/api2.0', self::IP);

        $this->assertStringStartsWith(self::HOST . ':', $resolve);
        $this->assertStringEndsWith(':' . self::IP, $resolve);
    }

    /**
     * @dataProvider unusableInputProvider
     */
    public function testUnusableInputProducesNoMapping($url, $ip)
    {
        $this->assertFalse($this->ct->maybeResolveIPInsteadOfHost($url, $ip));
    }

    public function unusableInputProvider()
    {
        return array(
            'not an IP' => array('https://' . self::HOST . '/api2.0', self::HOST),
            'empty IP' => array('https://' . self::HOST . '/api2.0', ''),
            'malformed IP' => array('https://' . self::HOST . '/api2.0', '167.71.167'),
            'IPv6 is not supported by CURLOPT_RESOLVE mapping' => array(
                'https://' . self::HOST . '/api2.0',
                '2a03:6f00:1::1',
            ),
            'URL without host' => array('/api2.0', self::IP),
            'empty URL' => array('', self::IP),
        );
    }

    /**
     * Pinning the host to an IP must never weaken TLS: the fallback used to send the
     * request with CURLOPT_SSL_VERIFYPEER=false and CURLOPT_SSL_VERIFYHOST=0.
     */
    public function testResolveMappingKeepsTlsVerificationEnabled()
    {
        $resolve = $this->ct->maybeResolveIPInsteadOfHost(
            'https://' . self::HOST . '/api2.0',
            self::IP
        );

        $request = new ApbctResolveOptionsHarness();
        $request
            ->setUrl('https://' . self::HOST . '/api2.0')
            ->setOptions(array('timeout' => 15, CURLOPT_RESOLVE => array($resolve)));

        $options = $request->prepareOptionsForTest();

        $this->assertSame(array($resolve), $options[CURLOPT_RESOLVE]);
        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
        $this->assertSame('https://' . self::HOST . '/api2.0', $options[CURLOPT_URL]);
    }

    /**
     * @param array $servers
     * @param array $ptr_map
     * @param array $down_servers
     * @param array $down_ips
     *
     * @return ApbctRotateModerateStub
     */
    private function makeRotateStub(
        array $servers,
        array $ptr_map = array(),
        array $down_servers = array(),
        array $down_ips = array()
    ) {
        $ct                   = new ApbctRotateModerateStub();
        $ct->server_url       = self::POOL_URL;
        $ct->servers_fixture  = $servers;
        $ct->ptr_map          = $ptr_map;

        if ($down_servers) {
            $this->setPrivate($ct, 'downServers', $down_servers);
        }

        if ($down_ips) {
            $this->setPrivate($ct, 'down_ips', $down_ips);
        }

        return $ct;
    }

    private function setPrivate($object, $name, $value)
    {
        $property = new \ReflectionProperty(Cleantalk::class, $name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function server($ip, $ttl = 300)
    {
        return array('ip' => $ip, 'host' => self::POOL_HOST, 'ttl' => $ttl);
    }

    /**
     * The URL must never be derived from DNS: a poisoned resolver would then pick
     * the very name TLS is validated against. The pool hostname is the only name
     * known to match the API certificate.
     */
    public function testUrlKeepsPoolHostnameEvenWhenPtrIsAvailable()
    {
        $ct = $this->makeRotateStub(
            array($this->server(self::IP)),
            array(self::IP => self::HOST)
        );

        $this->assertSame(self::IP, $ct->rotateModerateAndUseIP());
        $this->assertSame(self::POOL_URL, $ct->work_url);
        $this->assertSame(self::IP, $ct->work_ip);
    }

    /**
     * Regression: an IP without a PTR used to be skipped entirely, which silently
     * disabled the fallback. The pool hostname is a valid SNI/certificate name,
     * so such an IP must still be usable.
     */
    public function testIpWithoutPtrFallsBackToPoolHostnameInsteadOfBeingSkipped()
    {
        $ct = $this->makeRotateStub(array($this->server(self::IP)));

        $this->assertSame(self::IP, $ct->rotateModerateAndUseIP());
        $this->assertSame(self::POOL_URL, $ct->work_url);
    }

    /**
     * The first candidate must be taken even if only later ones have a PTR.
     */
    public function testFirstIpIsTakenEvenWhenOnlyLaterNodesHavePtr()
    {
        $ct = $this->makeRotateStub(
            array($this->server(self::IP), $this->server(self::IP_SECOND)),
            array(self::IP_SECOND => self::HOST_SECOND)
        );

        $this->assertSame(self::IP, $ct->rotateModerateAndUseIP());
        $this->assertSame(self::POOL_URL, $ct->work_url);
    }

    /**
     * A node that already failed must not be picked again. It is identified by IP,
     * because every node now shares the pool hostname.
     */
    public function testNodeThatAlreadyFailedIsSkipped()
    {
        $ct = $this->makeRotateStub(
            array($this->server(self::IP), $this->server(self::IP_SECOND)),
            array(),
            array(),
            array(self::IP)
        );

        $this->assertSame(self::IP_SECOND, $ct->rotateModerateAndUseIP());
        $this->assertSame(self::POOL_URL, $ct->work_url);
    }

    /**
     * Regression: the pool hostname is shared by every node, so matching it against
     * the list of failed URLs must not discard the candidates. Deduplicating the
     * fallback by URL instead of by IP would return false here and kill the retry.
     */
    public function testFailedPoolUrlDoesNotDiscardCandidates()
    {
        $ct = $this->makeRotateStub(
            array($this->server(self::IP), $this->server(self::IP_SECOND)),
            array(),
            array(self::POOL_URL)
        );

        $this->assertSame(self::IP, $ct->rotateModerateAndUseIP());
        $this->assertSame(self::POOL_URL, $ct->work_url);
    }

    /**
     * The same IP must not be handed out twice within one request cycle.
     */
    public function testAlreadyUsedIpIsNotReturnedTwice()
    {
        $ct = $this->makeRotateStub(
            array($this->server(self::IP), $this->server(self::IP_SECOND))
        );

        $this->assertSame(self::IP, $ct->rotateModerateAndUseIP());
        $this->assertSame(self::IP_SECOND, $ct->rotateModerateAndUseIP());
        $this->assertFalse($ct->rotateModerateAndUseIP());
    }

    /**
     * getServersIp() returns a null IP when it could not obtain any record.
     */
    public function testEntriesWithoutIpAreSkipped()
    {
        $ct = $this->makeRotateStub(
            array($this->server(null), $this->server(''), $this->server(self::IP))
        );

        $this->assertSame(self::IP, $ct->rotateModerateAndUseIP());
    }

    public function testNoServersMeansNoFallback()
    {
        $ct = $this->makeRotateStub(array());

        $this->assertFalse($ct->rotateModerateAndUseIP());
    }

    /**
     * End to end of the two halves: the rotation always settles on the pool
     * hostname, and the mapping must pin exactly that hostname to the returned IP.
     */
    public function testSelectedIpAndWorkUrlProduceConsistentMapping()
    {
        foreach (array(array(self::IP => self::HOST), array()) as $ptr_map) {
            $ct = $this->makeRotateStub(array($this->server(self::IP)), $ptr_map);
            $ip = $ct->rotateModerateAndUseIP();

            $this->assertSame(self::IP, $ip);
            $this->assertSame(
                self::POOL_HOST . ':443:' . self::IP,
                $ct->maybeResolveIPInsteadOfHost($ct->work_url, $ip)
            );
        }
    }
}
