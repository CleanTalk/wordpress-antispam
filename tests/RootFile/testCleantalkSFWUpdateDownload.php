<?php

use PHPUnit\Framework\TestCase;
use Cleantalk\ApbctWP\Helper;

class SfwUpdateDownloadTest extends TestCase
{
    private $apbctBackup;
    private $helper;

    protected function setUp(): void
    {
        global $apbct;

        $this->apbctBackup = $apbct;

        $apbct = new \Cleantalk\ApbctWP\State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));

        $apbct->api_key = getenv("CLEANTALK_TEST_API_KEY");
        $apbct->data['key_is_ok'] = 1;
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
        $apbct->fw_stats['updating_folder'] = $directory;

        // mock Helper::httpMultiRequest
        $this->helper = $this->createMock(Helper::class);
        $this->helper->method('httpMultiRequest')
            ->willReturn(['https://example.com/file.csv.gz' => 'success']);
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct = $this->apbctBackup;
    }
    public function test_retry_count_greater_than_3_returns_error()
    {
        $urls = ['https://example.com/file.csv.gz'];
        $result = apbct_sfw_update__download_files($urls, false, 10, 4);
        $this->assertEquals('SFW update: retry count is greater than 3.', $result['error']);
    }

    public function test_folder_is_not_writable_returns_error()
    {
        $urls = ['https://example.com/file.csv.gz'];
        $result = apbct_sfw_update__download_files($urls, false, 10, 1);
        $this->assertEquals('SFW update folder is not writable.', $result['error']);
    }

    public function test_http_multi_request_returns_success()
    {
        $urls = ['https://example.com/file.csv.gz'];
        $result = apbct_sfw_update__download_files($urls, false, 10, 1);
        $this->assertEquals('success', $result['https://example.com/file.csv.gz']);
    }

    public function test_http_multi_request_returns_error()
    {
        global $apbct;
        
        // Use a writable directory for this test
        $testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cleantalk_test_' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($testDir, 0777, true);
        $apbct->fw_stats['updating_folder'] = $testDir;
        
        // Use an invalid URL that will cause httpMultiRequest to fail
        // This will make the download fail, triggering retries
        $urls = ['https://invalid-url-that-does-not-exist-12345.com/file.csv.gz'];
        
        // Start with retry_count = 1, it will retry 3 times (retry_count 1->2->3->4)
        // On the 4th attempt (retry_count = 4), it should return the error
        $result = apbct_sfw_update__download_files($urls, false, 10, 1);
        
        // After 3 retries, retry_count becomes 4, so it should return the error
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('SFW update: retry count is greater than 3.', $result['error']);
        
        // Clean up
        if (file_exists($testDir)) {
            $files = glob($testDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($testDir);
        }
    }
}
