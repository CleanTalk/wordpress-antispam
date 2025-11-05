<?php

use Cleantalk\ApbctWP\DB;
use Cleantalk\ApbctWP\ConnectionReports;
use Cleantalk\ApbctWP\HTTP\Request;
use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-updater.php');

class TestConnectionReports extends TestCase
{
    private $connection_reports;
    private $db;
    private $ct_request;
    private $ct_response;
    private $default_params;

    public function setUp()
    {
        global $apbct;
        $this->db = DB::getInstance();
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        apbct_run_update_actions('5.188', '5,189');
        $apbct->setConnectionReports();
        $this->connection_reports = $apbct->getConnectionReports();
        $this->ct_response = new \Cleantalk\Antispam\CleantalkResponse();
        $this->default_params = array(
            'sender_ip' => defined('CT_TEST_IP')
                ? CT_TEST_IP
                : \Cleantalk\ApbctWP\Helper::ipGet('remote_addr', false),
            'x_forwarded_for' => \Cleantalk\ApbctWP\Helper::ipGet('x_forwarded_for', false),
            'x_real_ip' => \Cleantalk\ApbctWP\Helper::ipGet('x_real_ip', false),
            'auth_key' => $apbct->api_key,
            'js_on' => '1',
            'agent' => APBCT_AGENT,
            'sender_info' => array('sender_info' => 'test'),
            'submit_time' => '10'
        );
        $this->ct_request = new \Cleantalk\Antispam\CleantalkRequest(\Cleantalk\ApbctWP\Helper::arrayMergeSaveNumericKeysRecursive($this->default_params, array()));

    }

    public function testCRIsTableExists()
    {
        $this->assertTrue($this->db->isTableExists(APBCT_TBL_CONNECTION_REPORTS));
    }

    public function testHandleRequestException()
    {
        $this->expectException(TypeError::class);
        $this->connection_reports->handleRequest('', '', '');
    }

    public function testAddingSuccessReportProcess()
    {
        $this->connection_reports->wipeReportsData();

        $this->ct_response->errno = 0;
        $this->ct_response->errstr = '';
        $this->connection_reports->handleRequest($this->ct_request, $this->ct_response, 0);
        $this->assertFalse($this->connection_reports->hasNegativeReports());
        $this->assertFalse($this->connection_reports->hasUnsentReports());
        $this->assertEquals('Nothing to sent.', $this->connection_reports->sendUnsentReports());
    }

    public function testAddingNegativeReportProcess()
    {
        $this->connection_reports->wipeReportsData();

        $this->ct_response->errno = 404;
        $this->ct_response->errstr = 'TEST 404 ERROR';
        $this->ct_response->failed_connections_urls_string = 'test.moderate1.cleantalk.org,test.moderate1.cleantalk.org';
        $this->ct_request->js_on = 0;
        $this->connection_reports->handleRequest($this->ct_request, $this->ct_response, 1);

        $this->assertTrue($this->connection_reports->hasNegativeReports());

        // Get from RC directly
        $data = $this->connection_reports->remoteCallOutput();
        $this->assertNotEmpty($data);
        $this->assertNotEmpty($data[0]['lib_report']);
        $this->assertEquals('TEST 404 ERROR', $data[0]['lib_report']);
        $this->assertEquals('1', $data[0]['js_block']);
        $this->assertEquals('test.moderate1.cleantalk.org,test.moderate1.cleantalk.org', $data[0]['failed_work_urls']);

        $this->assertNotEmpty($this->connection_reports->prepareNegativeReportsHtmlForSettingsPage());
        $this->assertTrue($this->connection_reports->hasUnsentReports());

        $this->assertEquals('1 reports were sent.', $this->connection_reports->sendUnsentReports());
        $this->assertNotEmpty($this->connection_reports->remoteCallOutput());
    }

    public function testReportsRotation()
    {
        $this->connection_reports->wipeReportsData();

        // Try to add more than limit
        for ($i = 0; $i < 25; $i++) {
            $this->ct_response->errno = 500;
            $this->ct_response->errstr = 'TEST ERROR ' . $i;
            $this->ct_response->failed_connections_urls_string = 'server' . $i . '.cleantalk.org';
            $this->connection_reports->handleRequest($this->ct_request, $this->ct_response, 0);
        }

        $data = $this->connection_reports->remoteCallOutput();
        // Should be 20 maximum
        $this->assertLessThanOrEqual(20, count($data));
    }

    public function testUnsentReportsCache()
    {
        $this->connection_reports->wipeReportsData();

        // Add several reports
        $this->ct_response->errno = 404;
        $this->ct_response->errstr = 'TEST ERROR';
        $this->ct_response->failed_connections_urls_string = 'test.cleantalk.org';

        $this->connection_reports->handleRequest($this->ct_request, $this->ct_response, 0);
        $this->connection_reports->handleRequest($this->ct_request, $this->ct_response, 1);

        // Check that unsent exists
        $this->assertTrue($this->connection_reports->hasUnsentReports());

        // Sending reports
        $this->assertEquals('2 reports were sent.', $this->connection_reports->sendUnsentReports());

        // Should be no more unsent reports
        $this->assertFalse($this->connection_reports->hasUnsentReports());
    }

    public function testEmptyReports()
    {
        $this->connection_reports->wipeReportsData();

        $this->assertFalse($this->connection_reports->hasNegativeReports());
        $this->assertFalse($this->connection_reports->hasUnsentReports());
        $this->assertEquals('', $this->connection_reports->prepareNegativeReportsHtmlForSettingsPage());
        $this->assertEquals(array(), $this->connection_reports->remoteCallOutput());
    }
}
