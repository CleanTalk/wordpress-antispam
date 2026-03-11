<?php

namespace ApbctWP\RequestParameters;

use Cleantalk\ApbctWP\RequestParameters\SubmitTimeHandler;
use PHPUnit\Framework\TestCase;

class TestSubmitTimeHandler extends TestCase
{
    public function testGetFromRequestReturnsNullWhenCalculationDisabled()
    {
        global $apbct;
        $apbct = (object)[
            'settings' => ['data__bot_detector_enabled' => true],
            'data'     => ['cookies_type' => 'alternative']
        ];

        $result = SubmitTimeHandler::getFromRequest();

        $this->assertNull($result);
    }


    public function testSetToRequestAlwaysModifyWhenCalculationDisabled()
    {
        global $apbct;
        $apbct = (object)[
            'settings' => ['data__bot_detector_enabled' => true],
            'data'     => ['cookies_type' => 'alternative']
        ];

        $cookie_test_value = [];
        SubmitTimeHandler::setToRequest(time(), $cookie_test_value);

        $this->assertNotEmpty($cookie_test_value);
        $this->assertArrayHasKey('cookies_names', $cookie_test_value);
        $this->assertArrayHasKey('check_value', $cookie_test_value);
    }

    public function testIsCalculationDisabledReturnsTrueWhenBotDetectorEnabled()
    {
        global $apbct;
        $apbct = (object)[
            'settings' => ['data__bot_detector_enabled' => true],
            'data'     => ['cookies_type' => 'alternative']
        ];

        $result = SubmitTimeHandler::isCalculationDisabled();

        $this->assertTrue($result);
    }

    public function testIsCalculationDisabledReturnsFalseWhenBotDetectorDisabled()
    {
        global $apbct;
        $apbct = (object)['settings' => ['data__bot_detector_enabled' => false]];

        $result = SubmitTimeHandler::isCalculationDisabled();

        $this->assertFalse($result);
    }
}
