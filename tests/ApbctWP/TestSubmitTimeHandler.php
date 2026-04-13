<?php

use Cleantalk\ApbctWP\RequestParameters\SubmitTimeHandler;
use PHPUnit\Framework\TestCase;

class TestSubmitTimeHandler extends TestCase
{
    public function testGetFromRequestReturnsNullWhenCalculationDisabled()
    {
        global $apbct;
        $apbct = (object) [
            'data' => [
                'cookies_type' => 'alternative',
                'bot_detector_enabled' => true
            ]
        ];

        $result = SubmitTimeHandler::getFromRequest();

        $this->assertNull($result);
    }


    public function testSetToRequestModifiesArrayRegardlessOfCalculationDisabled()
    {
        global $apbct;
        $apbct = (object) [
            'data' => [
                'cookies_type' => 'alternative',
                'bot_detector_enabled' => true
            ]
        ];

        $cookie_test_value = [];
        $timestamp = time();
        SubmitTimeHandler::setToRequest($timestamp, $cookie_test_value);

        $this->assertNotEmpty($cookie_test_value);
        $this->assertContains('ct_ps_timestamp', $cookie_test_value['cookies_names']);
        $this->assertEquals((string)$timestamp, $cookie_test_value['check_value']);
    }

    public function testIsCalculationDisabledReturnsTrueWhenBotDetectorEnabled()
    {
        global $apbct;
        $apbct = (object) [
            'data' => [
                'cookies_type' => 'alternative',
                'bot_detector_enabled' => true
            ]
        ];

        $result = SubmitTimeHandler::isCalculationDisabled();

        $this->assertTrue($result);
    }

    public function testIsCalculationDisabledReturnsFalseWhenBotDetectorDisabled()
    {
        global $apbct;
        $apbct = (object) ['data' => ['bot_detector_enabled' => false]];

        $result = SubmitTimeHandler::isCalculationDisabled();

        $this->assertFalse($result);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
    }
}
