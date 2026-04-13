<?php

namespace Cleantalk\ApbctWP\Tests\Firewall;

use Cleantalk\ApbctWP\Firewall\SFWUpdateHelper;
use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;
use stdClass;

class SFWUpdateHelperTest extends TestCase
{
    private $apbctBackup;

    protected function setUp(): void
    {
        parent::setUp();
        global $apbct;
        $this->apbctBackup = $apbct;

        // Setup mock $apbct global
        $apbct = new \stdClass();
        $apbct->fw_stats['multi_request_batch_size'] = 10;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        global $apbct;
        $apbct = $this->apbctBackup;
    }

    /**
     * @test
     */
    public function testGetSFWFilesBatchSizeReturnsDefaultValue()
    {
        global $apbct;
        unset($apbct->fw_stats['multi_request_batch_size']);

        $batchSize = SFWUpdateHelper::getSFWFilesBatchSize();

        $this->assertEquals(10, $batchSize);
    }

    /**
     * @test
     */
    public function testGetSFWFilesBatchSizeReturnsCustomValue()
    {
        global $apbct;
        $apbct->fw_stats['multi_request_batch_size'] = 5;

        $batchSize = SFWUpdateHelper::getSFWFilesBatchSize();

        $this->assertEquals(5, $batchSize);
    }

    /**
     * @test
     */
    public function testGetSFWFilesBatchSizeReturnsCustomValueAsString()
    {
        global $apbct;
        $apbct->fw_stats['multi_request_batch_size'] = 7;

        $batchSize = SFWUpdateHelper::getSFWFilesBatchSize();

        $this->assertEquals(7, $batchSize);
    }

    /**
     * @test
     */
    public function testGetSFWFilesBatchSizeRespectsConstantWithValidValue()
    {
        if (!defined('APBCT_SERVICE__SFW_UPDATE_CURL_MULTI_BATCH_SIZE')) {
            define('APBCT_SERVICE__SFW_UPDATE_CURL_MULTI_BATCH_SIZE', 3);
        }

        global $apbct;
        unset($apbct->fw_stats['multi_request_batch_size']);

        $batchSize = SFWUpdateHelper::getSFWFilesBatchSize();

        $this->assertEquals(3, $batchSize);
    }

    public function testSFWDataOutdated()
    {
        $apbct = new StdClass();
        $apbct->stats = ['sfw' => [
            'last_update_time' => time() - (10002*3),
            'update_period' => 10000,
        ]];
        $this->assertTrue(SFWUpdateHelper::SFWDataOutdated($apbct));
        $apbct->stats = ['sfw' => [
            'last_update_time' => time() - (9999*3),
            'update_period' => 10000,
        ]];
        $this->assertFalse(SFWUpdateHelper::SFWDataOutdated($apbct));
    }

    public function testSFWUpdateModeEnabled()
    {
        $apbct = new StdClass();
        $apbct->fw_stats = ['update_mode' => 1];
        $this->assertTrue(SFWUpdateHelper::SFWUpdateModeEnabled($apbct));
        $apbct->fw_stats = ['update_mode' => '1'];
        $this->assertTrue(SFWUpdateHelper::SFWUpdateModeEnabled($apbct));
        $apbct->fw_stats = ['update_mode' => true];
        $this->assertTrue(SFWUpdateHelper::SFWUpdateModeEnabled($apbct));

        $apbct->fw_stats = ['update_mode' => 0];
        $this->assertFalse(SFWUpdateHelper::SFWUpdateModeEnabled($apbct));
        $apbct->fw_stats = ['update_mode' => '0'];
        $this->assertFalse(SFWUpdateHelper::SFWUpdateModeEnabled($apbct));
        $apbct->fw_stats = ['update_mode' => false];
        $this->assertFalse(SFWUpdateHelper::SFWUpdateModeEnabled($apbct));
    }

    public function testProcessSFWOutdatedErrorHasError()
    {
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $apbct->stats = ['sfw' => [
            'last_update_time' => time() - (10002*3),
            'update_period' => 10000,
        ]];
        $apbct->settings['misc__send_connection_reports'] = 1;
        $apbct->settings['sfw__enabled'] = 1;
        SFWUpdateHelper::processSFWOutdatedError($apbct);
        add_action('init', function () use ($apbct) {
            $this->assertTrue($apbct->errorExists('sfw_outdated'));
        }, 999);
        do_action('init');
    }

    public function testProcessSFWOutdatedErrorNoErrorOnSettingDisabled()
    {
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $apbct->stats = ['sfw' => [
            'last_update_time' => time() - (10002*3),
            'update_period' => 10000,
        ]];
        $apbct->settings['sfw__enabled'] = 1;
        $apbct->settings['misc__send_connection_reports'] = 0;
        SFWUpdateHelper::processSFWOutdatedError($apbct);
        add_action('init', function () use ($apbct) {
            $this->assertFalse($apbct->errorExists('sfw_outdated'));
        }, 999);
        do_action('init');
    }

    public function testProcessSFWOutdatedErrorNoErrorOnUpdated()
    {
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $apbct->stats = ['sfw' => [
            'last_update_time' => time() - (9999*3),
            'update_period' => 10000,
        ]];
        $apbct->settings['sfw__enabled'] = 1;
        $apbct->settings['misc__send_connection_reports'] = 1;
        SFWUpdateHelper::processSFWOutdatedError($apbct);
        add_action('init', function () use ($apbct) {
            $this->assertFalse($apbct->errorExists('sfw_outdated'));
        }, 999);
        do_action('init');
    }

    public function testProcessSFWOutdatedErrorNoErrorSFWDisabled()
    {
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $apbct->stats = ['sfw' => [
            'last_update_time' => time() - (10002*3),
            'update_period' => 10000,
        ]];
        $apbct->settings['sfw__enabled'] = 0;
        $apbct->settings['misc__send_connection_reports'] = 1;
        SFWUpdateHelper::processSFWOutdatedError($apbct);
        add_action('init', function () use ($apbct) {
            $this->assertFalse($apbct->errorExists('sfw_outdated'));
        }, 999);
        do_action('init');
    }
}

