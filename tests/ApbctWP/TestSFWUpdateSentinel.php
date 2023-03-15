<?php

use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\ConnectionReports;
use Cleantalk\ApbctWP\HTTP\Request;
use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class TestSFWUpdateSentinel extends TestCase
{
    public function setUp()
    {
        global $apbct;
        $this->db = DB::getInstance();
        $apbct = new State('cleantalk', array('settings', 'data', 'debug', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $apbct->setSFWUpdateSentinel();

        $apbct->runAutoSaveStateVars();
        $apbct->data['sentinel_data']['last_sent_try'] = array(
            'date' => 0,
            'success' => false
        );
        $apbct->saveData();

    }

    public function testAddingID()
    {
        global $apbct;
        $apbct->sfw_update_sentinel->seekId('test_id_1');
        $this->assertIsInt($apbct->data['sentinel_data']['ids']['test_id_1']['started']);
    }

    public function testFinishID()
    {
        global $apbct;
        $apbct->sfw_update_sentinel->seekId('test_id_1');
        $apbct->sfw_update_sentinel->clearSentinelData();
        $this->assertEmpty($apbct->data['sentinel_data']['ids']);
    }

    public function testNotEnoughUpdates()
    {
        global $apbct;
        $apbct->sfw_update_sentinel->seekId('test_id_1');
        $apbct->sfw_update_sentinel->seekId('test_id_2');
        $apbct->sfw_update_sentinel->runWatchDog();
        $this->assertNotEmpty($apbct->data['sentinel_data']['ids']);
        $this->assertFalse($apbct->data['sentinel_data']['last_sent_try']['success']);
    }

    public function testGoodUpdates()
    {
        global $apbct;
        $apbct->sfw_update_sentinel->seekId('test_id_1');
        $apbct->sfw_update_sentinel->clearSentinelData();
        $apbct->sfw_update_sentinel->runWatchDog();
        $this->assertEmpty($apbct->data['sentinel_data']['ids']);
    }

    public function testSeveralBadBeforeGoodUpdates()
    {
        global $apbct;
        $apbct->sfw_update_sentinel->seekId('test_id_1');
        $apbct->sfw_update_sentinel->seekId('test_id_2');
        $apbct->sfw_update_sentinel->seekId('test_id_2');
        $apbct->sfw_update_sentinel->clearSentinelData();
        $apbct->sfw_update_sentinel->runWatchDog();
        $this->assertEmpty($apbct->data['sentinel_data']['ids']);
    }

    public function testFailedUpdate()
    {
        global $apbct;
        $apbct->settings['misc__send_connection_reports'] = 1;
        $apbct->saveSettings();
        $apbct->sfw_update_sentinel->seekId('test_id_1');
        $apbct->sfw_update_sentinel->seekId('test_id_2');
        $apbct->sfw_update_sentinel->seekId('test_id_3');
        $apbct->sfw_update_sentinel->runWatchDog();
        $this->assertEmpty($apbct->data['sentinel_data']['ids']);
        $this->assertIsBool($apbct->data['sentinel_data']['last_sent_try']['success']);
        $this->assertNotEquals($apbct->data['sentinel_data']['last_sent_try']['date'],0);
    }

    public function testDoNotSendReportIfDenied()
    {
        global $apbct;
        $apbct->settings['misc__send_connection_reports'] = 0;
        $apbct->saveSettings();
        $apbct->sfw_update_sentinel->seekId('test_id_1');
        $apbct->sfw_update_sentinel->seekId('test_id_2');
        $apbct->sfw_update_sentinel->seekId('test_id_3');
        $apbct->sfw_update_sentinel->runWatchDog();
        $this->assertEmpty($apbct->data['sentinel_data']['ids']);
        $this->assertIsBool($apbct->data['sentinel_data']['last_sent_try']['success']);
        $this->assertEquals($apbct->data['sentinel_data']['last_sent_try']['date'],0);
    }
}
