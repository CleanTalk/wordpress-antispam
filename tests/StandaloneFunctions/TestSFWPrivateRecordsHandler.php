<?php

class TestSFWPrivateRecordsHandler extends PHPUnit\Framework\TestCase
{
    protected $cron_object;

    protected function setUp()
    {
        apbct_run_update_actions('5.188','5.189');
    }

    public function testEmptyActionPRH()
    {
        $this->expectException(Exception::class);
        apbct_sfw_private_records_handler('');
    }

    public function testWrongActionPRH()
    {
        $this->expectException(Exception::class);
        apbct_sfw_private_records_handler('update');
    }

    public function testEmptyDataPRHAdd()
    {
        $this->expectException(Exception::class);
        apbct_sfw_private_records_handler('add');
    }

    public function testDeleteEmptyDataPRH()
    {
        $this->expectException(Exception::class);
        apbct_sfw_private_records_handler('delete');
    }

    public function testGoodDataPRHAdd()
    {

        //multi

        apbct_sfw_private_records_handler('delete','{"0":"424242424,323232323,1,1","1":"434343434,33333333,1,1"}');

        $this->assertEquals(
            '{"OK":{"total":2,"added":2,"updated":0,"ignored":0}}',
            apbct_sfw_private_records_handler('add','{"0":"424242424,323232323,1,1","1":"434343434,33333333,1,1"}')
        );
        //next iteration returns ignorance
        $this->assertEquals(
            '{"OK":{"total":2,"added":0,"updated":0,"ignored":2}}',
            apbct_sfw_private_records_handler('add','{"0":"424242424,323232323,1,1","1":"434343434,33333333,1,1"}')
        );
        //next iteration returns updating
        $this->assertEquals(
            '{"OK":{"total":2,"added":0,"updated":2,"ignored":0}}',
            apbct_sfw_private_records_handler('add','{"0":"424242424,323232323,0,1","1":"434343434,33333333,0,1"}')
        );

        //single

        apbct_sfw_private_records_handler('delete','{"0":"424242424,323232323,1,1","1":"434343434,33333333,1,1"}');

        $this->assertEquals(
            '{"OK":{"total":1,"added":1,"updated":0,"ignored":0}}',
            apbct_sfw_private_records_handler('add','{"0":"424242424,323232323,1,1"}')
        );
        //next iteration returns ignorance
        $this->assertEquals(
            '{"OK":{"total":1,"added":0,"updated":0,"ignored":1}}',
            apbct_sfw_private_records_handler('add','{"0":"424242424,323232323,1,1"}')
        );
        //next iteration returns updating
        $this->assertEquals(
            '{"OK":{"total":1,"added":0,"updated":1,"ignored":0}}',
            apbct_sfw_private_records_handler('add','{"0":"424242424,323232323,0,1"}')
        );
    }

    public function testDataValidatePRHAdd()
    {
        $this->expectExceptionMessage('network');
        apbct_sfw_private_records_handler('add','{"0":"adsd,323232323,1,1","1":"434343434,33333333,1,1"}');
        $this->expectExceptionMessage('mask');
        apbct_sfw_private_records_handler('add','{"0":"424242424,adsd,1,1","1":"434343434,33333333,1,1"}');
        $this->expectExceptionMessage('mask');
        apbct_sfw_private_records_handler('add','{"0":"424242424,323232323,1,1","1":"434343434,adsd,1,1"}');
        $this->expectExceptionMessage('status');
        apbct_sfw_private_records_handler('add','{"0":"424242424,323232323,adsd,1","1":"434343434,33333333,1,1"}');
        $this->expectExceptionMessage('source');
        apbct_sfw_private_records_handler('add','{"0":"424242424,323232323,1,1","1":"434343434,33333333,1,adsd"}');

        $this->expectExceptionMessage('network');
        apbct_sfw_private_records_handler('add','{"0":"0,823232323,1,1"}');
    }

    public function testPRHDeleteBadData()
    {
        $this->expectExceptionMessage('network');
        apbct_sfw_private_records_handler('delete','{"0":"adsd,323232323,1,1","1":"434343434,33333333,1,1"}');
        $this->expectExceptionMessage('mask');
        apbct_sfw_private_records_handler('delete','{"0":"424242424,adsd,1,1","1":"434343434,33333333,1,1"}');
        $this->expectExceptionMessage('mask');
        apbct_sfw_private_records_handler('delete','{"0":"424242424,323232323,1,1","1":"434343434,adsd,1,1"}');

    }

    public function testPRHDeleteGoodData()
    {

        //multi

        apbct_sfw_private_records_handler('add','{"0":"424242424,323232323,1,1","1":"434343434,33333333,1,1"}');

        $this->assertEquals(
            '{"OK":{"total":2,"deleted":2,"ignored":0}}',
            apbct_sfw_private_records_handler('delete','{"0":"424242424,323232323,1,1","1":"434343434,33333333,1,1"}')
        );
        //next iteration returns ignorance
        $this->assertEquals(
            '{"OK":{"total":2,"deleted":0,"ignored":2}}',
            apbct_sfw_private_records_handler('delete','{"0":"424242424,323232323,1,1","1":"434343434,33333333,1,1"}')
        );

        apbct_sfw_private_records_handler('add','{"0":"424242424,323232323,1,1"}');

        //ignore one of them
        $this->assertEquals(
            '{"OK":{"total":2,"deleted":1,"ignored":1}}',
            apbct_sfw_private_records_handler('delete','{"0":"424242424,323232323,0,1","1":"434343434,33333333,0,1"}')
        );

        //single

        apbct_sfw_private_records_handler('add','{"0":"424242424,323232323,1,1"}');

        $this->assertEquals(
            '{"OK":{"total":1,"deleted":1,"ignored":0}}',
            apbct_sfw_private_records_handler('delete','{"0":"424242424,323232323,1,1"}')
        );
        //next iteration returns ignorance
        $this->assertEquals(
            '{"OK":{"total":1,"deleted":0,"ignored":1}}',
            apbct_sfw_private_records_handler('delete','{"0":"424242424,323232323,1,1"}')
        );
    }

}