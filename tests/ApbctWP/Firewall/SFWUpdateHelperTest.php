<?php

namespace Cleantalk\ApbctWP\Tests\Firewall;

use Cleantalk\ApbctWP\Firewall\SFWUpdateHelper;
use PHPUnit\Framework\TestCase;

class SFWUpdateHelperTest extends TestCase
{
    private $apbctBackup;

    protected function setUp()
    {
        parent::setUp();
        global $apbct;
        $this->apbctBackup = $apbct;

        // Setup mock $apbct global
        $apbct = new \stdClass();
        $apbct->fw_stats['multi_request_batch_size'] = 10;
    }

    protected function tearDown()
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
}

