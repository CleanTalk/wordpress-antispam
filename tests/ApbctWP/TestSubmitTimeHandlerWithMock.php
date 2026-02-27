
<?php

use Cleantalk\ApbctWP\RequestParameters\SubmitTimeHandler;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class TestSubmitTimeHandlerWithMock extends TestCase
{
    public function testGetFromRequestReturnsNullWhenTimestampIsZero()
    {
        global $apbct;
        $apbct = (object) ['settings' => ['data__bot_detector_enabled' => false], 'data' => ['cookies_type' => 'alternative']];

        $mock = \Mockery::mock('alias:Cleantalk\\ApbctWP\\RequestParameters\\RequestParameters');
        $mock->shouldReceive('get')->with(SubmitTimeHandler::REQUEST_PARAM_NAME, true)->andReturn(0);

        \Mockery::mock('alias:apbct_cookies_test')->shouldReceive('__invoke')->andReturn(1);

        $result = SubmitTimeHandler::getFromRequest();

        $this->assertNull($result);
    }

    public function testGetFromRequestReturnsTimeDifferenceWhenValid()
    {
        \Mockery::close();
        global $apbct;
        $apbct = (object) [
            'settings' => ['data__bot_detector_enabled' => false],
            'data' => ['cookies_type' => 'alternative']
        ];
        unset($_COOKIE['ct_gathering_loaded']);
        $timestamp = time() - 100;
        $mock = \Mockery::mock('alias:Cleantalk\\ApbctWP\\RequestParameters\\RequestParameters');
        $mock->shouldReceive('get')->with(SubmitTimeHandler::REQUEST_PARAM_NAME, true)->andReturn($timestamp);
        \Mockery::mock('alias:apbct_cookies_test')->shouldReceive('__invoke')->andReturn(1);
        $result = SubmitTimeHandler::getFromRequest();
        $this->assertEquals(100, $result);
    }

    public function testGetFromRequestReturnsNullWhenBotDetectorEnabledAndGatheringNotLoaded()
    {
        global $apbct;
        $apbct = (object) [
            'settings' => ['data__bot_detector_enabled' => true],
            'data' => ['cookies_type' => 'alternative']
        ];
        unset($_COOKIE['ct_gathering_loaded']);
        $timestamp = time() - 100;
        $mock = \Mockery::mock('alias:Cleantalk\\ApbctWP\\RequestParameters\\RequestParameters');
        // Expectation for ct_gathering_loaded (used in isCalculationDisabled)
        $mock->shouldReceive('get')->with('ct_gathering_loaded')->andReturn(false);
        // Expectation for timestamp param (used in getFromRequest)
        $mock->shouldReceive('get')->with(SubmitTimeHandler::REQUEST_PARAM_NAME, true)->andReturn($timestamp);
        \Mockery::mock('alias:apbct_cookies_test')->shouldReceive('__invoke')->andReturn(1);
        $result = SubmitTimeHandler::getFromRequest();
        $this->assertNull($result);
    }

    public function testSetToRequestAddsTimestampToRequestAndCookieTestValue()
    {
        global $apbct;
        $apbct = (object) ['settings' => ['data__bot_detector_enabled' => false]];

        $current_timestamp = time();
        $cookie_test_value = ['cookies_names' => [], 'check_value' => ''];

        $mock = \Mockery::mock('alias:Cleantalk\\ApbctWP\\RequestParameters\\RequestParameters');
        $mock->shouldReceive('set')->with(SubmitTimeHandler::REQUEST_PARAM_NAME, (string)$current_timestamp, true)->andReturnNull();

        SubmitTimeHandler::setToRequest($current_timestamp, $cookie_test_value);

        $this->assertContains(SubmitTimeHandler::REQUEST_PARAM_NAME, $cookie_test_value['cookies_names']);
        $this->assertStringContainsString((string)$current_timestamp, $cookie_test_value['check_value']);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
    }
}