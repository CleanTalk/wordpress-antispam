<?php

use Cleantalk\ApbctWP\DB;

class TestSFWPrivateRecordsHandler extends PHPUnit\Framework\TestCase
{
    protected $cron_object;
    protected $sfw;

    protected function setUp()
    {
        apbct_run_update_actions('5.188','5.189');
        $this->sfw = new \Cleantalk\ApbctWP\Firewall\SFW(DB::getInstance(),
            APBCT_TBL_FIREWALL_DATA);
        $this->sfw->setDb(DB::getInstance());
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

    public function testSfwCheckOnAdd(){
        //good bots
        apbct_sfw_private_records_handler(
            'add',
            '{"0":"1118821483,4294967295,0,1"}'
        );

        $this->sfw->setIpArray(
            array('66.175.220.107')
            );
        $this->assertEquals(array(
            'ip'=>'66.175.220.107',
            'is_personal'=>'1',
            'status'=>'DENY_SFW',
            'network' => '66.175.220.107/32'
        ),$this->sfw->check()[0]);

        //normal IP block
        apbct_sfw_private_records_handler(
            'add',
            '{"0":"2967665369,4294967295,0,1"}'
        );

        $this->sfw->setIpArray(
            array('176.226.250.217')
        );
        $this->assertEquals(array(
            'ip'=>'176.226.250.217',
            'is_personal'=>'1',
            'status'=>'DENY_SFW',
            'network' => '176.226.250.217/32'
        ),$this->sfw->check()[0]);

        //normal IP allow
        apbct_sfw_private_records_handler(
            'add',
            '{"0":"2967665369,4294967295,1,1"}'
        );

        $this->sfw->setIpArray(
            array('176.226.250.217')
        );
        $this->assertEquals(array(
            'ip'=>'176.226.250.217',
            'is_personal'=>'1',
            'status'=>'PASS_SFW__BY_WHITELIST',
            'network' => '176.226.250.217/32'
        ),$this->sfw->check()[0]);
    }

    public function testSfwCheckOnDelete(){

        //1118821483,4294967295,1,0
        apbct_sfw_private_records_handler(
            'add',
            '{"0":"1118821483,4294967295,0,1"}'
        );

        apbct_sfw_private_records_handler(
            'delete',
            '{"0":"1118821483,4294967295"}'
        );


        $this->sfw->setIpArray(
            array('66.175.220.107')
        );
        $this->assertEquals(array(
            'ip'=>'66.175.220.107',
            'is_personal'=>null,
            'status'=>'PASS_SFW',
        ),$this->sfw->check()[0]);
    }

}