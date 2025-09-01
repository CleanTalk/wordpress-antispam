<?php

use Cleantalk\ApbctWP\RequestParameters\SubmitTimeHandler;
use PHPUnit\Framework\TestCase;

class TestSubmitTimeHandler extends TestCase
{
    public function testGetFromRequestReturnsNullWhenCalculationDisabled()
    {
        global $apbct;
        $apbct = (object) ['settings' => ['data__bot_detector_enabled' => true]];

        $result = SubmitTimeHandler::getFromRequest();

        $this->assertNull($result);
    }

    public function testGetFromRequestReturnsNullWhenTimestampIsZero()
    {
        global $apbct;
        $apbct = (object) ['settings' => ['data__bot_detector_enabled' => false]];

        $mock = \Mockery::mock('alias:Cleantalk\ApbctWP\RequestParameters\RequestParameters');
        $mock->shouldReceive('get')->with(SubmitTimeHandler::REQUEST_PARAM_NAME, true)->andReturn(0);

        Mockery::mock('alias:apbct_cookies_test')->shouldReceive('__invoke')->andReturn(1);

        $result = SubmitTimeHandler::getFromRequest();

        $this->assertNull($result);
    }

    public function testGetFromRequestReturnsTimeDifferenceWhenValid()
    {
        global $apbct;
        $apbct = (object) [
            'settings' => ['data__bot_detector_enabled' => false],
            'data' => ['cookies_type' => 'alternative']
        ];

        $timestamp = time() - 100;

        $mock = \Mockery::mock('alias:Cleantalk\ApbctWP\RequestParameters\RequestParameters');
        $mock->shouldReceive('get')->with(SubmitTimeHandler::REQUEST_PARAM_NAME, true)->andReturn($timestamp);

        Mockery::mock('alias:apbct_cookies_test')->shouldReceive('__invoke')->andReturn(1);

        $result = SubmitTimeHandler::getFromRequest();

        $this->assertEquals(100, $result);
    }

    public function testSetToRequestDoesNotModifyWhenCalculationDisabled()
    {
        global $apbct;
        $apbct = (object) ['settings' => ['data__bot_detector_enabled' => true]];

        $cookie_test_value = [];
        SubmitTimeHandler::setToRequest(time(), $cookie_test_value);

        $this->assertEmpty($cookie_test_value);
    }

    public function testSetToRequestAddsTimestampToRequestAndCookieTestValue()
    {
        global $apbct;
        $apbct = (object) ['settings' => ['data__bot_detector_enabled' => false]];

        $current_timestamp = time();
        $cookie_test_value = ['cookies_names' => [], 'check_value' => ''];

        $mock = \Mockery::mock('alias:Cleantalk\ApbctWP\RequestParameters\RequestParameters');
        $mock->shouldReceive('set')->with(SubmitTimeHandler::REQUEST_PARAM_NAME, (string)$current_timestamp, true)->andReturnNull();

        SubmitTimeHandler::setToRequest($current_timestamp, $cookie_test_value);

        $this->assertContains(SubmitTimeHandler::REQUEST_PARAM_NAME, $cookie_test_value['cookies_names']);
        $this->assertStringContainsString((string)$current_timestamp, $cookie_test_value['check_value']);
    }

    public function testIsCalculationDisabledReturnsTrueWhenBotDetectorEnabled()
    {
        global $apbct;
        $apbct = (object) ['settings' => ['data__bot_detector_enabled' => true]];

        $result = SubmitTimeHandler::isCalculationDisabled();

        $this->assertTrue($result);
    }

    public function testIsCalculationDisabledReturnsFalseWhenBotDetectorDisabled()
    {
        global $apbct;
        $apbct = (object) ['settings' => ['data__bot_detector_enabled' => false]];

        $result = SubmitTimeHandler::isCalculationDisabled();

        $this->assertFalse($result);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
    }
}
