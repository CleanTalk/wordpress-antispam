<?php

namespace Cleantalk\Common\Tests\HTTP;

use Cleantalk\Common\HTTP\Request;
use PHPUnit\Framework\TestCase;

/**
 * Exposes prepared cURL options without performing a network request.
 */
class RequestResolveHarness extends Request
{
    public function prepareOptionsForTest()
    {
        $method = new \ReflectionMethod(Request::class, 'convertOptionsTocURLFormat');
        $method->setAccessible(true);
        $method->invoke($this);

        $this->appendOptionsObligatory();
        $this->processPresets();

        return $this->options;
    }
}

/**
 * The 'resolve' option pins a hostname to an IP via CURLOPT_RESOLVE.
 *
 * This is what lets the plugin bypass a broken system resolver WITHOUT putting a
 * bare IP into the URL -- doing that breaks TLS, because the CleanTalk certificate
 * only carries '*.cleantalk.org' and no IP SANs.
 */
class TestRequestResolveOption extends TestCase
{
    public function testResolveOptionIsConvertedToCurlResolve()
    {
        $request = new RequestResolveHarness();
        $request
            ->setUrl('https://moderate.cleantalk.org')
            ->setOptions(['resolve' => ['moderate.cleantalk.org:443:88.198.153.60']]);

        $options = $request->prepareOptionsForTest();

        $this->assertArrayHasKey(CURLOPT_RESOLVE, $options);
        $this->assertSame(
            ['moderate.cleantalk.org:443:88.198.153.60'],
            $options[CURLOPT_RESOLVE]
        );
    }

    public function testResolveOptionDoesNotWeakenSslVerification()
    {
        $request = new RequestResolveHarness();
        $request
            ->setUrl('https://moderate.cleantalk.org')
            ->setOptions(['resolve' => ['moderate.cleantalk.org:443:88.198.153.60']]);

        $options = $request->prepareOptionsForTest();

        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    public function testUrlKeepsHostnameSoCertificateStillMatches()
    {
        $request = new RequestResolveHarness();
        $request
            ->setUrl('https://moderate.cleantalk.org')
            ->setOptions(['resolve' => ['moderate.cleantalk.org:443:88.198.153.60']]);

        $options = $request->prepareOptionsForTest();

        $this->assertSame('https://moderate.cleantalk.org', $options[CURLOPT_URL]);
        $this->assertStringNotContainsString('88.198.153.60', $options[CURLOPT_URL]);
    }

    public function testEmptyResolveOptionIsDropped()
    {
        $request = new RequestResolveHarness();
        $request
            ->setUrl('https://moderate.cleantalk.org')
            ->setOptions(['resolve' => []]);

        $options = $request->prepareOptionsForTest();

        $this->assertArrayNotHasKey(CURLOPT_RESOLVE, $options);
    }

    public function testNonArrayResolveOptionIsDropped()
    {
        $request = new RequestResolveHarness();
        $request
            ->setUrl('https://moderate.cleantalk.org')
            ->setOptions(['resolve' => 'moderate.cleantalk.org:443:88.198.153.60']);

        $options = $request->prepareOptionsForTest();

        $this->assertArrayNotHasKey(CURLOPT_RESOLVE, $options);
    }

    /**
     * String option names must never leak into curl_setopt_array().
     */
    public function testResolveStringKeyIsRemovedFromOptions()
    {
        $request = new RequestResolveHarness();
        $request
            ->setUrl('https://moderate.cleantalk.org')
            ->setOptions(['resolve' => ['moderate.cleantalk.org:443:88.198.153.60']]);

        $options = $request->prepareOptionsForTest();

        $this->assertArrayNotHasKey('resolve', $options);
        foreach ( array_keys($options) as $key ) {
            $this->assertIsInt($key, 'Only cURL constants may remain in options');
        }
    }
}
