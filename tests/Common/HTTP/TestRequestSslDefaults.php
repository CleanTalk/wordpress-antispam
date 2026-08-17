<?php

namespace Cleantalk\Common\Tests\HTTP;

use Cleantalk\Common\HTTP\Request;
use PHPUnit\Framework\TestCase;

/**
 * Exposes prepared cURL options without performing a network request.
 */
class RequestSslDefaultsHarness extends Request
{
    public function prepareOptionsForTest()
    {
        $this->convertOptionsTocURLFormatPublic();
        $this->appendOptionsObligatory();
        $this->processPresets();

        return $this->options;
    }

    /**
     * convertOptionsTocURLFormat is private — invoke via request() preamble duplicate.
     */
    private function convertOptionsTocURLFormatPublic()
    {
        $method = new \ReflectionMethod(Request::class, 'convertOptionsTocURLFormat');
        $method->setAccessible(true);
        $method->invoke($this);
    }
}

class TestRequestSslDefaults extends TestCase
{
    public function testSslVerificationEnabledByDefault()
    {
        $request = new RequestSslDefaultsHarness();
        $request->setUrl('https://api.cleantalk.org');
        $options = $request->prepareOptionsForTest();

        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    public function testSslPresetKeepsVerificationEnabled()
    {
        $request = new RequestSslDefaultsHarness();
        $request->setUrl('https://api.cleantalk.org')->setPresets(['get', 'ssl']);
        $options = $request->prepareOptionsForTest();

        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    public function testSslverifyFalseOptionDisablesVerification()
    {
        $request = new RequestSslDefaultsHarness();
        $request
            ->setUrl('https://api.cleantalk.org')
            ->setOptions(['sslverify' => false]);
        $options = $request->prepareOptionsForTest();

        $this->assertFalse($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(0, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    public function testSslverifyTrueSetsVerifyHostToTwo()
    {
        $request = new RequestSslDefaultsHarness();
        $request
            ->setUrl('https://api.cleantalk.org')
            ->setOptions(['sslverify' => true]);
        $options = $request->prepareOptionsForTest();

        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
    }
}
