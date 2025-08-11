<?php

use Cleantalk\ApbctWP\API;
use Cleantalk\ApbctWP\Helper;

class APITest extends PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
    }

    protected function commonAction($common_list)
    {
        $result = API::methodGet2sBlacklistsDb(getenv("CLEANTALK_TEST_API_KEY"), 'multifiles', '3_2', $common_list);
        $this->assertFalse(isset($result['error']));

        if ( empty($result['error']) ) {
            $directions =  array(
                'file_url'  => isset($result['file_url'])             ? $result['file_url']             : null,
                'file_ua_url'  => isset($result['file_ua_url'])             ? $result['file_ua_url']             : null,
                'file_ck_url'  => isset($result['file_ck_url'])             ? $result['file_ck_url']             : null,
            );
        }

        $this->assertTrue(!empty($directions['file_url']));
        $this->assertTrue(!empty($directions['file_ua_url']));
        $this->assertTrue(!empty($directions['file_ck_url']));

        $this->assertIsString($directions['file_url']);
        $this->assertIsString($directions['file_ua_url']);
        $this->assertIsString($directions['file_ck_url']);

        return $directions;
    }

    public function testGetDefaultFWDb() {

        $directions = $this->commonAction(null);
        $file_ck = Helper::httpGetDataFromRemoteGzAndParseCsv($directions['file_ck_url']);
        $this->assertFalse(isset($file_ck[2]));

    }

    public function testGetCommonSFWDb() {
        $directions = $this->commonAction(1);
        $file_ck = Helper::httpGetDataFromRemoteGzAndParseCsv($directions['file_ck_url']);
        $common_sign = $file_ck[2];
        $this->assertEquals('common_lists', $common_sign[0]);
        $this->assertEquals('1', $common_sign[1]);
    }

    public function testGetPersonalSFWDb() {
        $directions = $this->commonAction(0);
        $file_ck = Helper::httpGetDataFromRemoteGzAndParseCsv($directions['file_ck_url']);
        $common_sign = $file_ck[2];
        $this->assertEquals('common_lists', $common_sign[0]);
        $this->assertEquals('0', $common_sign[1]);
    }

}