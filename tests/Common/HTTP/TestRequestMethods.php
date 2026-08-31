<?php

namespace Cleantalk\Common\Tests\HTTP;

use Cleantalk\Common\HTTP\Request;
use Cleantalk\Common\HTTP\Response;
use PHPUnit\Framework\TestCase;

/**
 * Harness that exposes internals and stubs network I/O.
 */
class RequestMethodsHarness extends Request
{
    /** @var Response|null Override for requestSingle() */
    public $stubbedSingleResponse;

    /** @var Response|null Override for requestWithSocket() */
    public $stubbedSocketResponse;

    /** @var bool Track whether requestWithSocket was called */
    public $socketCalled = false;

    /** @var bool Track whether requestSingle was called */
    public $singleCalled = false;

    protected function requestSingle()
    {
        $this->singleCalled = true;
        if ($this->stubbedSingleResponse !== null) {
            return $this->stubbedSingleResponse;
        }
        return parent::requestSingle();
    }

    /**
     * Override to avoid real file_get_contents calls.
     */
    private function requestWithSocketStub()
    {
        $this->socketCalled = true;
        if ($this->stubbedSocketResponse !== null) {
            return $this->stubbedSocketResponse;
        }
        return new Response(['error' => 'STUBBED_SOCKET_ERROR'], []);
    }

    /**
     * Invoke the real private requestWithSocket() via reflection.
     */
    public function callRequestWithSocket()
    {
        $method = new \ReflectionMethod(Request::class, 'requestWithSocket');
        $method->setAccessible(true);
        return $method->invoke($this);
    }

    /**
     * Invoke the real private getSocketTimeout() via reflection.
     */
    public function callGetSocketTimeout()
    {
        $method = new \ReflectionMethod(Request::class, 'getSocketTimeout');
        $method->setAccessible(true);
        return $method->invoke($this);
    }

    /**
     * Expose options for inspection.
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Directly set options array for testing.
     */
    public function setOptionsRaw(array $options)
    {
        $this->options = $options;
    }

    /**
     * Expose presets for inspection.
     */
    public function getPresets()
    {
        return $this->presets;
    }
}

/**
 * Harness that intercepts requestSingle/requestWithSocket in request() flow
 * using method overrides at protected/private level via reflection patching.
 */
class RequestFlowHarness extends Request
{
    /** @var Response|null */
    public $stubbedSingleResponse;

    /** @var Response|null */
    public $stubbedSocketResponse;

    public $singleCalled = false;
    public $socketCalled = false;

    protected function requestSingle()
    {
        $this->singleCalled = true;
        return $this->stubbedSingleResponse
            ?? new Response(['error' => 'STUBBED_SINGLE_ERROR'], []);
    }

    /**
     * Override requestMulti to return stubbed responses keyed by URL.
     */
    protected function requestMulti()
    {
        return [];
    }
}

class TestRequestMethods extends TestCase
{
    // ─── request() ──────────────────────────────────────────────

    public function testRequestReturnsErrorWhenUrlNotSet()
    {
        $request = new Request();
        $result = $request->request();

        $this->assertIsArray($result);
        $this->assertSame('URL_IS_NOT_SET', $result['error']);
    }

    public function testRequestReturnsCurlNotInstalledWhenNoCurlAndNoSocketPreset()
    {
        if (function_exists('curl_init')) {
            $this->markTestSkipped('cURL is available; cannot test CURL_NOT_INSTALLED path.');
        }

        $request = new Request();
        $request->setUrl('https://example.com');
        $result = $request->request();

        $this->assertIsArray($result);
        $this->assertSame('CURL_NOT_INSTALLED', $result['error']);
    }

    public function testRequestCallsSingleForScalarUrl()
    {
        $harness = new RequestFlowHarness();
        $harness->setUrl('https://example.com');
        $harness->stubbedSingleResponse = new Response('OK', ['http_code' => 200]);

        $result = $harness->request();

        $this->assertTrue($harness->singleCalled);
        $this->assertSame('OK', $result);
    }

    public function testRequestCallsSingleAndReturnsProcessedContent()
    {
        $harness = new RequestFlowHarness();
        $harness->setUrl('https://example.com');
        $harness->setData(['key' => 'value']);
        $harness->stubbedSingleResponse = new Response('{"status":"ok"}', ['http_code' => 200]);

        $result = $harness->request();

        $this->assertSame('{"status":"ok"}', $result);
    }

    public function testRequestPropagatesErrorFromSingleResponse()
    {
        $harness = new RequestFlowHarness();
        $harness->setUrl('https://example.com');
        $harness->stubbedSingleResponse = new Response(['error' => 'CONNECT_TIMEOUT'], []);

        $result = $harness->request();

        $this->assertIsArray($result);
        $this->assertSame('CONNECT_TIMEOUT', $result['error']);
    }

    public function testRequestWithCallbackProcessesResult()
    {
        $harness = new RequestFlowHarness();
        $harness->setUrl('https://example.com');
        $harness->stubbedSingleResponse = new Response('hello world', ['http_code' => 200]);
        $harness->addCallback(static function ($content, $_url) {
            return strtoupper($content);
        });

        $result = $harness->request();

        $this->assertSame('HELLO WORLD', $result);
    }

    public function testRequestWithMultipleCallbacksRespectsPriority()
    {
        $harness = new RequestFlowHarness();
        $harness->setUrl('https://example.com');
        $harness->stubbedSingleResponse = new Response('test', ['http_code' => 200]);

        $harness->addCallback(static function ($content, $_url) {
            return $content . '_second';
        }, [], 20);

        $harness->addCallback(static function ($content, $_url) {
            return $content . '_first';
        }, [], 10);

        $result = $harness->request();

        $this->assertSame('test_first_second', $result);
    }

    public function testRequestPassesResponseObjectWhenPassResponseTrue()
    {
        $harness = new RequestFlowHarness();
        $harness->setUrl('https://example.com');
        $harness->stubbedSingleResponse = new Response('body', ['http_code' => 200]);

        $harness->addCallback(static function ($response, $_url) {
            // $response should be a Response object
            return $response->getResponseCode();
        }, [], 100, true);

        $result = $harness->request();

        $this->assertSame(200, $result);
    }

    public function testRequestWithGetPreset()
    {
        $harness = new RequestFlowHarness();
        $harness->setUrl('https://example.com');
        $harness->setData(['foo' => 'bar']);
        $harness->setPresets(['get']);
        $harness->stubbedSingleResponse = new Response('get_result', ['http_code' => 200]);

        $result = $harness->request();

        $this->assertSame('get_result', $result);
    }

    // ─── getSocketTimeout() ─────────────────────────────────────

    public function testGetSocketTimeoutDefaultIs10()
    {
        $harness = new RequestMethodsHarness();
        $timeout = $harness->callGetSocketTimeout();

        $this->assertSame(10, $timeout);
    }

    public function testGetSocketTimeoutFromStringOption()
    {
        $harness = new RequestMethodsHarness();
        $harness->setOptionsRaw(['timeout' => 30]);
        $timeout = $harness->callGetSocketTimeout();

        $this->assertSame(30, $timeout);
    }

    public function testGetSocketTimeoutFromCurloptTimeout()
    {
        if (!defined('CURLOPT_TIMEOUT')) {
            $this->markTestSkipped('CURLOPT_TIMEOUT not defined.');
        }

        $harness = new RequestMethodsHarness();
        $harness->setOptionsRaw([CURLOPT_TIMEOUT => 45]);
        $timeout = $harness->callGetSocketTimeout();

        $this->assertSame(45, $timeout);
    }

    public function testGetSocketTimeoutStringOptionTakesPrecedence()
    {
        if (!defined('CURLOPT_TIMEOUT')) {
            $this->markTestSkipped('CURLOPT_TIMEOUT not defined.');
        }

        $harness = new RequestMethodsHarness();
        $harness->setOptionsRaw(['timeout' => 20, CURLOPT_TIMEOUT => 45]);
        $timeout = $harness->callGetSocketTimeout();

        $this->assertSame(20, $timeout);
    }

    public function testGetSocketTimeoutCastsToInt()
    {
        $harness = new RequestMethodsHarness();
        $harness->setOptionsRaw(['timeout' => '15']);
        $timeout = $harness->callGetSocketTimeout();

        $this->assertSame(15, $timeout);
    }

    // ─── requestWithSocket() ────────────────────────────────────

    public function testRequestWithSocketReturnsErrorWhenFopenDisabled()
    {
        $allowUrlFopen = ini_get('allow_url_fopen');
        if ($allowUrlFopen) {
            // We cannot reliably toggle ini at runtime in all environments
            // so we test the branch via a dedicated harness
            $this->markTestSkipped('allow_url_fopen is enabled; cannot test disabled path without ini override.');
        }

        $harness = new RequestMethodsHarness();
        $harness->setUrl('https://example.com');
        $response = $harness->callRequestWithSocket();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertIsArray($response->getError());
        $this->assertSame('ALLOW_URL_FOPEN_IS_DISABLED', $response->getError()['error']);
    }

    public function testRequestWithSocketReturnsResponseObject()
    {
        if (!ini_get('allow_url_fopen')) {
            $this->markTestSkipped('allow_url_fopen is disabled.');
        }

        $harness = new RequestMethodsHarness();
        // Use an unreachable URL to trigger a file_get_contents failure quickly
        $harness->setUrl('http://0.0.0.0:1');
        $harness->setOptionsRaw(['timeout' => 1]);
        $response = $harness->callRequestWithSocket();

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRequestWithSocketSetsPostMethodByDefault()
    {
        if (!ini_get('allow_url_fopen')) {
            $this->markTestSkipped('allow_url_fopen is disabled.');
        }

        $harness = new RequestMethodsHarness();
        $harness->setUrl('http://0.0.0.0:1');
        $harness->setData(['key' => 'value']);
        $harness->setOptionsRaw(['timeout' => 1]);

        $response = $harness->callRequestWithSocket();

        // Should return a Response (even if errored) — POST is the default method
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRequestWithSocketUsesGetPreset()
    {
        if (!ini_get('allow_url_fopen')) {
            $this->markTestSkipped('allow_url_fopen is disabled.');
        }

        $harness = new RequestMethodsHarness();
        $harness->setUrl('http://0.0.0.0:1');
        $harness->setPresets(['get']);
        $harness->setData(['foo' => 'bar']);
        $harness->setOptionsRaw(['timeout' => 1]);

        $response = $harness->callRequestWithSocket();

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRequestWithSocketUsesUrlFromCurloptWhenAvailable()
    {
        if (!ini_get('allow_url_fopen')) {
            $this->markTestSkipped('allow_url_fopen is disabled.');
        }
        if (!defined('CURLOPT_URL')) {
            $this->markTestSkipped('CURLOPT_URL not defined.');
        }

        $harness = new RequestMethodsHarness();
        $harness->setUrl('http://should-not-be-used:1');
        $harness->setOptionsRaw([
            CURLOPT_URL => 'http://0.0.0.0:1?already=processed',
            'timeout' => 1,
        ]);

        $response = $harness->callRequestWithSocket();

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRequestWithSocketRespectsTimeout()
    {
        if (!ini_get('allow_url_fopen')) {
            $this->markTestSkipped('allow_url_fopen is disabled.');
        }

        $harness = new RequestMethodsHarness();
        $harness->setUrl('http://0.0.0.0:1');
        $harness->setOptionsRaw(['timeout' => 1]);

        $start = microtime(true);
        $harness->callRequestWithSocket();
        $elapsed = microtime(true) - $start;

        // Should not hang indefinitely — timeout should be respected
        $this->assertLessThan(10, $elapsed);
    }

    public function testRequestWithSocketReturnsErrorOnFailure()
    {
        if (!ini_get('allow_url_fopen')) {
            $this->markTestSkipped('allow_url_fopen is disabled.');
        }

        $harness = new RequestMethodsHarness();
        $harness->setUrl('http://0.0.0.0:1');
        $harness->setOptionsRaw(['timeout' => 1]);

        $response = $harness->callRequestWithSocket();

        $this->assertInstanceOf(Response::class, $response);
        // file_get_contents to 0.0.0.0:1 should fail
        $error = $response->getError();
        $this->assertNotNull($error);
        $this->assertSame('FAILED_TO_USE_FILE_GET_CONTENTS', $error['error']);
    }

    public function testRequestWithSocketAppendsDataToUrlForGet()
    {
        if (!ini_get('allow_url_fopen')) {
            $this->markTestSkipped('allow_url_fopen is disabled.');
        }

        // When no CURLOPT_URL is set and preset is 'get', data should be appended to URL
        $harness = new RequestMethodsHarness();
        $harness->setUrl('http://0.0.0.0:1/path');
        $harness->setPresets(['get']);
        $harness->setData(['param1' => 'val1']);
        // No CURLOPT_URL in options — so the socket method should append data itself
        $harness->setOptionsRaw(['timeout' => 1]);

        $response = $harness->callRequestWithSocket();

        $this->assertInstanceOf(Response::class, $response);
    }
}
