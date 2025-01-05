<?php

use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\Firewall\AntiCrawler;
use PHPUnit\Framework\TestCase;

class TestAnticrawler extends TestCase
{
    public $ac;
    public $sign;
    public function setUp()
    {
        global $apbct;

        $test_ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3';
        $test_host = 'test.loc';
        $test_http = '';
        $this->sign = md5($test_ua . $test_http . $test_host );

        DB::getInstance()->execute('TRUNCATE TABLE ' . APBCT_TBL_AC_LOG);
        DB::getInstance()->execute(
            'INSERT INTO ' . APBCT_TBL_AC_LOG . ' (id, ip, ua, entries, interval_start) 
                VALUES (
                    \'39a7c47aa204b7697acd172e9342f641\', \'10.10.10.10\', \''. $this->sign .'\', 1, TIMESTAMPADD(MINUTE, -1, NOW())
                    )'
        );

        $this->ac = new AntiCrawler(
            APBCT_TBL_FIREWALL_LOG,
            APBCT_TBL_AC_LOG,
            array(
                'api_key' => $apbct->api_key,
                'apbct'   => $apbct,
            )
        );
        $this->ac->setIpArray(array('real' => '10.10.10.10'));
        $this->ac->setDb(DB::getInstance());
//        $_SERVER['HTTP_USER_AGENT'] = $test_ua;
//        $_SERVER['HTTP_HOST'] = $test_host;
//        $_SERVER['HTTPS'] = '';
    }

    public function tearDown()
    {
        DB::getInstance()->execute('TRUNCATE TABLE ' . APBCT_TBL_AC_LOG);
    }

    public function testIPFlowDeny()
    {
        $this->ac->sign = $this->sign;
        $ac_result = $this->ac->check();
        $this->assertEquals('DENY_ANTICRAWLER', $ac_result[0]['status']);
    }

    public function testIPFlowAllow()
    {
        $this->ac->sign = 'test';
        $ac_result = $this->ac->check();
        $this->assertEmpty($ac_result);
    }

    //todo AG: add tests for UA flow!
}
