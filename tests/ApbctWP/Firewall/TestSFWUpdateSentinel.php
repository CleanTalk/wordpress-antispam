<?php

use PHPUnit\Framework\TestCase;
use Cleantalk\ApbctWP\Firewall\SFWUpdateSentinel;
use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\State;

/**
 * Full coverage test for SFWUpdateSentinel.
 */
class TestSFWUpdateSentinel extends TestCase
{
    private $apbct_copy;
    public function setUp(): void
    {
        global $apbct;
        $this->apbct_copy = $apbct;
        $this->db = DB::getInstance();
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $apbct->setSFWUpdateSentinel();

        $apbct->runAutoSaveStateVars();
        $apbct->data['sentinel_data']['last_sent_try'] = array(
            'date' => 0,
            'success' => false
        );
        //$apbct->saveData();
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct = $this->apbct_copy;

        parent::tearDown();
    }

    public function testSeekAndClear()
    {
        global $apbct;

        $s = new SFWUpdateSentinel();

        $s->seekId('a');
        $s->seekId('b');

        $this->assertCount(2, $apbct->data['sentinel_data']['ids']);

        $s->clearSentinelData();

        $this->assertEmpty($apbct->data['sentinel_data']['ids']);
    }

    public function testHasNumberOfFailedUpdatesBranches()
    {
        $s = new SFWUpdateSentinel();

        $s->seekId('1');
        $s->seekId('2');
        $s->seekId('3');

        $this->assertTrue($s->hasNumberOfFailedUpdates(3));
        $this->assertFalse($s->hasNumberOfFailedUpdates(4));
    }

    public function testGetWatchDogCronPeriod()
    {
        $s = new SFWUpdateSentinel();

        $this->assertEquals(43200, $s->getWatchDogCronPeriod());
    }

    public function testRunWatchDogDoesNothingIfNotEnoughIds()
    {
        global $apbct;

        $s = new SFWUpdateSentinel();
        $s->seekId('1');

        $s->runWatchDog();

        $this->assertEquals(
            0,
            $apbct->data['sentinel_data']['last_sent_try']['date']
        );
    }

    public function testRunWatchDogDisabledInSettings()
    {
        global $apbct;

        $apbct->settings['misc__send_connection_reports'] = 0;

        $s = new SFWUpdateSentinel();
        $s->seekId('1');
        $s->seekId('2');
        $s->seekId('3');

        $s->runWatchDog();

        $this->assertEquals(
            0,
            $apbct->data['sentinel_data']['last_sent_try']['date']
        );
    }

    public function testSuccessfulEmailSend()
    {
        global $apbct;

        $s = new SFWUpdateSentinel();

        $apbct->settings['misc__send_connection_reports'] = 1;

        $s->seekId('1');
        $s->seekId('2');
        $s->seekId('3');

        $s->runWatchDog();

        $this->assertNotEquals(
            0,
            $apbct->data['sentinel_data']['last_sent_try']['date']
        );

        $this->assertTrue(
            $apbct->data['sentinel_data']['last_sent_try']['success']
        );
    }

    public function testFailedEmailSendBranch()
    {
        global $apbct;

        $GLOBALS['__wp_mail_return'] = false;

        $s = new SFWUpdateSentinel();
        $s->seekId('1');
        $s->seekId('2');
        $s->seekId('3');

        $s->runWatchDog();

        $this->assertFalse(
            $apbct->data['sentinel_data']['last_sent_try']['success']
        );

        unset($GLOBALS['__wp_mail_return']);
    }

    public function testHtmlGenerators()
    {
        global $apbct;

        $apbct->fw_stats =  array
        (
            'firewall_updating' => 123,
            'updating_folder' => 'uploads\cleantalk_fw_files_for_blog_1',
            'firewall_updating_id' => null,
            'firewall_update_percent' => 0,
            'firewall_updating_last_start' => '2026-02-28 16:39:28',
            'expected_networks_count' => 0,
            'expected_networks_count_personal' => 1,
            'expected_ua_count' => 0,
            'expected_ua_count_personal' => 0,
            'update_mode' => 0,
            'reason_direct_update_log' => null,
            'multi_request_batch_size' => 10,
            'personal_lists_url_id' => '5486d23bc60dde935fd906d3145fd8bb',
            'common_lists_url_id' => '1aeb928b023fa748e81215fd551d108e',
            'calls' => 18
        );

        $s = new SFWUpdateSentinel();

        $s->seekId('abc');
        $html1 = $s->getFailedUpdatesHTML(
            $apbct->data['sentinel_data']['ids']
        );
        $this->assertStringContainsString('abc', $html1);

        $html2 = $s->getFWStatsHTML();
        $this->assertStringContainsString('123', $html2);
        $this->assertStringContainsString('2026-02-28 16:39:28', $html2);
        $this->assertStringContainsString('reason_direct_update_log', $html2);

        $html3 = $s->getQueueJSONPretty();
        $this->assertIsString($html3);
        $this->assertNotEmpty($html3);
        $this->assertStringContainsString('Last queue not found or invalid.', $html3);

        update_option('cleantalk_sfw_update_queue', array(
            'stage' => 'some'
        ));
        $html3 = $s->getQueueJSONPretty();
        $this->assertIsString($html3);
        $this->assertNotEmpty($html3);
        $this->assertStringContainsString('some', $html3);
        $this->assertStringContainsString('stage', $html3);

        $apbct->data['sentinel_data']['prev_sent_try'] = [
            'date'    => time(),
            'success' => true,
        ];

        update_option('cleantalk_sfw_update_queue', array());

        $html4 = $s->getPrevReportHTML($apbct->data);
        $this->assertStringContainsString('Previous', $html4);
    }
}
