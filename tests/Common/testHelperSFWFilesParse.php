<?php

namespace Common;

use Cleantalk\ApbctWP\Helper;
use PHPUnit;

class testHelperSFWFilesParse extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $this->api_responses = array(
            'null' => [
                'file_url'    => __DIR__ . '/methodGet2sBlacklistsDbMock/null/' . 'bl_list_a42388ef51bd4615babf2096ea74583e.multifiles.csv.gz',
                'file_ua_url' => __DIR__ . '/methodGet2sBlacklistsDbMock/null/' . 'ua_list_a42388ef51bd4615babf2096ea74583e.csv.gz',
                'file_ck_url' => __DIR__ . '/methodGet2sBlacklistsDbMock/null/' . 'ck_list_a42388ef51bd4615babf2096ea74583e.csv.gz',
            ],
            '1'    => [
                'file_url'    => __DIR__ . '/methodGet2sBlacklistsDbMock/1/' . 'bl_list_d9cdb4e4d893ef87efb28278445f2142.multifiles.csv.gz',
                'file_ua_url' => __DIR__ . '/methodGet2sBlacklistsDbMock/1/' . 'ua_list_d9cdb4e4d893ef87efb28278445f2142.csv.gz',
                'file_ck_url' => __DIR__ . '/methodGet2sBlacklistsDbMock/1/' . 'ck_list_d9cdb4e4d893ef87efb28278445f2142.csv.gz',
            ],
            '0'    => [
                'file_url'    => __DIR__ . '/methodGet2sBlacklistsDbMock/0/' . 'bl_list_5af42e8e26b7da5d50f384afe20c57ba.multifiles.csv.gz',
                'file_ua_url' => __DIR__ . '/methodGet2sBlacklistsDbMock/0/' . 'ua_list_5af42e8e26b7da5d50f384afe20c57ba.csv.gz',
                'file_ck_url' => __DIR__ . '/methodGet2sBlacklistsDbMock/0/' . 'ck_list_5af42e8e26b7da5d50f384afe20c57ba.csv.gz',
            ],
        );
    }

    protected function commonAction($common_list)
    {
        if ( is_null($common_list) ) {
            $result = $this->api_responses['null'];
        } else {
            $result = $this->api_responses[$common_list];
        }
        $this->assertFalse(isset($result['error']));

        if ( empty($result['error']) ) {
            $directions = array(
                'file_url'    => isset($result['file_url']) ? $result['file_url'] : null,
                'file_ua_url' => isset($result['file_ua_url']) ? $result['file_ua_url'] : null,
                'file_ck_url' => isset($result['file_ck_url']) ? $result['file_ck_url'] : null,
            );
        }

        $this->assertTrue(! empty($directions['file_url']));
        $this->assertTrue(! empty($directions['file_ua_url']));
        $this->assertTrue(! empty($directions['file_ck_url']));

        $this->assertIsString($directions['file_url']);
        $this->assertIsString($directions['file_ua_url']);
        $this->assertIsString($directions['file_ck_url']);

        return $directions;
    }

    public function testGetDefaultFWDb()
    {
        $directions = $this->commonAction(null);
        $decoded    = gzdecode(file_get_contents($directions['file_ck_url']));
        $file_ck    = Helper::bufferParseCsv($decoded);
        $this->assertFalse(isset($file_ck[2]));
    }

    public function testGetCommonSFWDb()
    {
        $directions  = $this->commonAction(1);
        $decoded     = gzdecode(file_get_contents($directions['file_ck_url']));
        $file_ck     = Helper::bufferParseCsv($decoded);
        $common_sign = $file_ck[2];
        $this->assertEquals('common_lists', $common_sign[0]);
        $this->assertEquals('1', $common_sign[1]);
    }

    public function testGetPersonalSFWDb()
    {
        $directions  = $this->commonAction(0);
        $decoded     = gzdecode(file_get_contents($directions['file_ck_url']));
        $file_ck     = Helper::bufferParseCsv($decoded);
        $common_sign = $file_ck[2];
        $this->assertEquals('common_lists', $common_sign[0]);
        $this->assertEquals('0', $common_sign[1]);
    }

}
