<?php

use PHPUnit\Framework\TestCase;
use Cleantalk\Common\RateLimiter\RateLimiter;
use Cleantalk\Common\RateLimiter\RateLimiterConfig;
use Cleantalk\Common\RateLimiter\RateLimiterDto;

/**
 * Concrete test stub that replicates ApbctRateLimiter::setUID() logic
 * with controllable IP/UA, no WP or DB dependencies.
 */
class TestRateLimiterStub extends RateLimiter
{
    private $test_ip;
    private $test_ua;

    public function __construct(RateLimiterConfig $config, string $ip, string $ua)
    {
        $this->test_ip = $ip;
        $this->test_ua = $ua;
        parent::__construct($config);
    }

    protected function setIP(): void
    {
        $this->ip = $this->test_ip;
    }

    protected function setUA(): void
    {
        $this->ua = $this->test_ua;
    }

    /**
     * Same override as ApbctRateLimiter — per-IP only, ignoring UA.
     */
    protected function setUID(): void
    {
        $this->uid = md5($this->ip . $this->config->type);
    }

    protected function handleErrors(string $msg): void
    {
    }

    protected function increment(RateLimiterDto $uid_data): bool
    {
        return true;
    }

    protected function insert(RateLimiterDto $uid_data): bool
    {
        return true;
    }

    protected function cleanUp(): bool
    {
        return true;
    }

    // --- Expose protected properties for assertions ---

    public function getUID(): string
    {
        return $this->uid;
    }

    public function getIP(): string
    {
        return $this->ip;
    }

    public function getUA(): string
    {
        return $this->ua;
    }
}

/**
 * Stub with the DEFAULT base RateLimiter::setUID() (ip + ua + type)
 * to demonstrate the difference with the per-IP override.
 */
class TestRateLimiterBaseStub extends RateLimiter
{
    private $test_ip;
    private $test_ua;

    public function __construct(RateLimiterConfig $config, string $ip, string $ua)
    {
        $this->test_ip = $ip;
        $this->test_ua = $ua;
        parent::__construct($config);
    }

    protected function setIP(): void
    {
        $this->ip = $this->test_ip;
    }

    protected function setUA(): void
    {
        $this->ua = $this->test_ua;
    }

    // No setUID override — uses base RateLimiter logic: md5(ip + ua + type)

    protected function handleErrors(string $msg): void
    {
    }

    protected function increment(RateLimiterDto $uid_data): bool
    {
        return true;
    }

    protected function insert(RateLimiterDto $uid_data): bool
    {
        return true;
    }

    protected function cleanUp(): bool
    {
        return true;
    }

    public function getUID(): string
    {
        return $this->uid;
    }
}

class TestApbctRateLimiter extends TestCase
{
    /**
     * @test
     * UID must equal md5(ip + type), without UA component
     */
    public function uidMatchesExpectedHashOfIpAndType()
    {
        $config  = new RateLimiterConfig('rc_remote_call', 10, 60);
        $limiter = new TestRateLimiterStub($config, '192.168.1.1', 'Mozilla/5.0');

        $expected = md5('192.168.1.1' . 'rc_remote_call');
        $this->assertSame($expected, $limiter->getUID());
    }

    /**
     * @test
     * Same IP with different User-Agents MUST produce the same UID (per-IP blocking).
     * This is the core security fix — attacker cannot bypass rate limit by rotating UA.
     */
    public function sameIpDifferentUaProducesSameUid()
    {
        $config = new RateLimiterConfig('rc_remote_call', 10, 60);

        $limiter1 = new TestRateLimiterStub($config, '10.0.0.1', 'Mozilla/5.0');
        $limiter2 = new TestRateLimiterStub($config, '10.0.0.1', 'curl/7.68.0');
        $limiter3 = new TestRateLimiterStub($config, '10.0.0.1', '');

        $this->assertSame($limiter1->getUID(), $limiter2->getUID());
        $this->assertSame($limiter1->getUID(), $limiter3->getUID());
    }

    /**
     * @test
     * Different IPs must produce different UIDs — rate limit is per-IP.
     */
    public function differentIpsProduceDifferentUids()
    {
        $config = new RateLimiterConfig('rc_remote_call', 10, 60);

        $limiter1 = new TestRateLimiterStub($config, '10.0.0.1', 'Mozilla/5.0');
        $limiter2 = new TestRateLimiterStub($config, '10.0.0.2', 'Mozilla/5.0');

        $this->assertNotSame($limiter1->getUID(), $limiter2->getUID());
    }

    /**
     * @test
     * Different config types produce different UIDs for the same IP.
     */
    public function differentTypesProduceDifferentUids()
    {
        $config1 = new RateLimiterConfig('rc_remote_call', 10, 60);
        $config2 = new RateLimiterConfig('login_attempt', 5, 300);

        $limiter1 = new TestRateLimiterStub($config1, '10.0.0.1', 'Mozilla/5.0');
        $limiter2 = new TestRateLimiterStub($config2, '10.0.0.1', 'Mozilla/5.0');

        $this->assertNotSame($limiter1->getUID(), $limiter2->getUID());
    }

    /**
     * @test
     * Verify that the base RateLimiter (without override) DOES include UA in UID,
     * while our override does NOT. This proves the override changes behavior.
     */
    public function overrideExcludesUaWhileBaseIncludes()
    {
        $config = new RateLimiterConfig('rc_remote_call', 10, 60);

        // Base: same IP + different UA → different UID
        $base1 = new TestRateLimiterBaseStub($config, '10.0.0.1', 'Mozilla/5.0');
        $base2 = new TestRateLimiterBaseStub($config, '10.0.0.1', 'curl/7.68.0');
        $this->assertNotSame($base1->getUID(), $base2->getUID(), 'Base UID should include UA');

        // Override: same IP + different UA → SAME UID
        $override1 = new TestRateLimiterStub($config, '10.0.0.1', 'Mozilla/5.0');
        $override2 = new TestRateLimiterStub($config, '10.0.0.1', 'curl/7.68.0');
        $this->assertSame($override1->getUID(), $override2->getUID(), 'Override UID should ignore UA');
    }

    /**
     * @test
     * UID is always a 32-char hex string (MD5 hash).
     */
    public function uidIsValidMd5Hash()
    {
        $config  = new RateLimiterConfig('rc_remote_call', 10, 60);
        $limiter = new TestRateLimiterStub($config, '192.168.1.100', 'SomeAgent');

        $uid = $limiter->getUID();
        $this->assertSame(32, strlen($uid));
        $this->assertRegExp('/^[0-9a-f]{32}$/', $uid);
    }

    /**
     * @test
     * IPv6 address should also work correctly for UID generation.
     */
    public function uidWorksWithIpv6()
    {
        $config = new RateLimiterConfig('rc_remote_call', 10, 60);

        $limiter1 = new TestRateLimiterStub($config, '::1', 'Mozilla/5.0');
        $limiter2 = new TestRateLimiterStub($config, '::1', 'curl/7.68.0');

        $expected = md5('::1' . 'rc_remote_call');
        $this->assertSame($expected, $limiter1->getUID());
        $this->assertSame($limiter1->getUID(), $limiter2->getUID());
    }

    /**
     * @test
     * Empty IP edge case — should still produce a deterministic UID.
     */
    public function uidWithEmptyIp()
    {
        $config = new RateLimiterConfig('rc_remote_call', 10, 60);

        $limiter = new TestRateLimiterStub($config, '', 'Mozilla/5.0');

        $expected = md5('' . 'rc_remote_call');
        $this->assertSame($expected, $limiter->getUID());
    }
}
