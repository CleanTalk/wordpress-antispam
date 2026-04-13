<?php

namespace Cleantalk\ApbctWP\Tests\Firewall;

use Cleantalk\ApbctWP\Firewall\SFWFilesDownloader;
use Cleantalk\ApbctWP\HTTP\HTTPMultiRequestService;
use Cleantalk\ApbctWP\HTTP\HTTPRequestContract;
use PHPUnit\Framework\TestCase;
use Cleantalk\ApbctWP\State;

class TestSFWFilesDownloader extends TestCase
{
    private $apbctBackup;
    private $testFolder;

    protected function setUp(): void
    {
        parent::setUp();
        global $apbct;
        $this->apbctBackup = $apbct;

        $this->testFolder = sys_get_temp_dir() . '/test_sfw_' . time() . '/';
        if (!is_dir($this->testFolder)) {
            mkdir($this->testFolder, 0777, true);
        }

        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $apbct->data = ['sfw_update__batch_size' => 10];
        $apbct->fw_stats = ['updating_folder' => $this->testFolder];
        $apbct->save = function($key) {};
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        global $apbct;

        if (is_dir($this->testFolder)) {
            $files = glob($this->testFolder . '/*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($this->testFolder);
        }

        $apbct = $this->apbctBackup;
    }

    /**
     * @test
     */
    public function testThrowsExceptionWhenInvalidServicePassed()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Service must be an instance of');

        // Pass an invalid object (not HTTPMultiRequestService)
        new SFWFilesDownloader(new \stdClass());
    }

    /**
     * @test
     */
    public function testReturnsErrorWhenFolderNotWritable()
    {
        global $apbct;
        $apbct->fw_stats['updating_folder'] = '/nonexistent/path/';

        $mockFabric = $this->getMockBuilder(HTTPMultiRequestService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $downloader = new SFWFilesDownloader($mockFabric);
        $result = $downloader->downloadFiles(['https://example.com/file1.gz'], false, 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('NOT WRITABLE', $result['error']);
        $this->assertArrayNotHasKey('update_args', $result);
    }

    /**
     * @test
     */
    public function testReturnsErrorWhenUrlsNotArray()
    {
        $mockFabric = $this->getMockBuilder(HTTPMultiRequestService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $downloader = new SFWFilesDownloader($mockFabric);
        $result = $downloader->downloadFiles('NOT AN ARRAY', false, 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('SHOULD BE AN ARRAY', $result['error']);
    }

    /**
     * @test
     */
    public function testReturnsSuccessStageWhenEmptyUrlsArray()
    {
        $mockFabric = $this->getMockBuilder(HTTPMultiRequestService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $downloader = new SFWFilesDownloader($mockFabric);
        $result = $downloader->downloadFiles([], false, 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('next_stage', $result);
        $this->assertEquals('apbct_sfw_update__create_tables', $result['next_stage']['name']);
    }

    /**
     * @test
     */
    public function testReturnsTrueWhenEmptyUrlsAndDirectUpdateTrue()
    {
        $mockFabric = $this->getMockBuilder(HTTPMultiRequestService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $downloader = new SFWFilesDownloader($mockFabric);
        $result = $downloader->downloadFiles([], true, 0);

        $this->assertTrue((bool)$result);
    }

    /**
     * @test
     */
    public function testReturnsErrorWhenContractProcessNotDone()
    {
        $urls = ['https://example.com/file1.gz'];

        $mockFabric = $this->getMockBuilder(HTTPMultiRequestService::class)
            ->disableOriginalConstructor()
            ->setMethods(['setMultiContract'])
            ->getMock();

        $mockFabric->expects($this->once())
            ->method('setMultiContract')
            ->with($urls)
            ->willReturnCallback(function() use ($mockFabric) {
                $mockFabric->process_done = false;
                $mockFabric->error_msg = 'CONTRACT PROCESSING FAILED';
                return $mockFabric;
            });

        $downloader = new SFWFilesDownloader($mockFabric);
        $result = $downloader->downloadFiles($urls, false, 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('CONTRACT PROCESSING FAILED', $result['error']);
    }

    /**
     * @test
     */
    public function testReturnsRepeatStageWhenSomeFilesFailedToDownload()
    {
        global $apbct;
        $apbct->fw_stats['multi_request_batch_size'] = 10;

        $urls = [
            'https://example.com/file1.gz',
            'https://example.com/file2.gz',
            'https://example.com/file3.gz'
        ];

        $mockFabric = $this->getMockBuilder(HTTPMultiRequestService::class)
            ->disableOriginalConstructor()
            ->setMethods(['setMultiContract', 'getFailedURLs', 'writeSuccessURLsContent'])
            ->getMock();

        $mockFabric->method('setMultiContract')
            ->willReturnCallback(function() use ($mockFabric) {
                $mockFabric->process_done = true;
                $mockFabric->suggest_batch_reduce_to = 2;
                return $mockFabric;
            });

        $mockFabric->method('getFailedURLs')
            ->willReturn(['https://example.com/file2.gz']);

        $mockFabric->method('writeSuccessURLsContent')
            ->willReturn(['https://example.com/file1.gz', 'https://example.com/file3.gz']);

        $downloader = new SFWFilesDownloader($mockFabric);
        $result = $downloader->downloadFiles($urls, false, 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('NOT COMPLETED, TRYING AGAIN', $result['error']);
        $this->assertArrayHasKey('update_args', $result);
        $this->assertEquals(['https://example.com/file2.gz'], $result['update_args']['args']);
        $this->assertEquals(2, $apbct->fw_stats['multi_request_batch_size']);
    }

    /**
     * @test
     */
    public function testReturnsErrorWhenWriteToFileSystemFails()
    {
        $urls = ['https://example.com/file1.gz'];

        $mockFabric = $this->getMockBuilder(HTTPMultiRequestService::class)
            ->disableOriginalConstructor()
            ->setMethods(['setMultiContract', 'getFailedURLs', 'writeSuccessURLsContent'])
            ->getMock();

        $mockFabric->method('setMultiContract')
            ->willReturnCallback(function() use ($mockFabric) {
                $mockFabric->process_done = true;
                return $mockFabric;
            });

        $mockFabric->method('getFailedURLs')
            ->willReturn([]);

        $mockFabric->method('writeSuccessURLsContent')
            ->willReturn('CAN NOT WRITE TO FILE: /test/file1.gz');

        $downloader = new SFWFilesDownloader($mockFabric);
        $result = $downloader->downloadFiles($urls, false, 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('CAN NOT WRITE TO FILE', $result['error']);
        $this->assertStringContainsString('/test/file1.gz', $result['error']);
    }

    /**
     * @test
     */
    public function testReturnsNextStageWhenAllFilesDownloadedSuccessfully()
    {
        $urls = [
            'https://example.com/file1.gz',
            'https://example.com/file2.gz'
        ];

        $mockFabric = $this->getMockBuilder(HTTPMultiRequestService::class)
            ->disableOriginalConstructor()
            ->setMethods(['setMultiContract', 'getFailedURLs', 'writeSuccessURLsContent'])
            ->getMock();

        $mockFabric->method('setMultiContract')
            ->willReturnCallback(function() use ($mockFabric) {
                $mockFabric->process_done = true;
                $mockFabric->suggest_batch_reduce_to = false;
                return $mockFabric;
            });

        $mockFabric->method('getFailedURLs')
            ->willReturn([]);

        $mockFabric->method('writeSuccessURLsContent')
            ->willReturn($urls);

        $downloader = new SFWFilesDownloader($mockFabric);
        $result = $downloader->downloadFiles($urls, false, 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('next_stage', $result);
        $this->assertEquals('apbct_sfw_update__create_tables', $result['next_stage']['name']);
    }

    /**
     * @test
     */
    public function testProcessesUrlsInBatchesAccordingToBatchSize()
    {
        global $apbct;
        $apbct->fw_stats['multi_request_batch_size'] = 3;

        $urls = [
            'https://example.com/file1.gz',
            'https://example.com/file2.gz',
            'https://example.com/file3.gz',
            'https://example.com/file4.gz',
            'https://example.com/file5.gz'
        ];

        $callCount = 0;
        $receivedBatches = [];

        $mockFabric = $this->getMockBuilder(HTTPMultiRequestService::class)
            ->disableOriginalConstructor()
            ->setMethods(['setMultiContract', 'getFailedURLs', 'writeSuccessURLsContent'])
            ->getMock();

        $mockFabric->method('setMultiContract')
            ->willReturnCallback(function($batchUrls) use ($mockFabric, &$callCount, &$receivedBatches) {
                $callCount++;
                $receivedBatches[] = $batchUrls;
                $mockFabric->process_done = true;
                $mockFabric->suggest_batch_reduce_to = false;
                return $mockFabric;
            });

        $mockFabric->method('getFailedURLs')
            ->willReturn([]);

        $mockFabric->method('writeSuccessURLsContent')
            ->willReturnCallback(function() use (&$receivedBatches, &$callCount) {
                return $receivedBatches[$callCount - 1];
            });

        $downloader = new SFWFilesDownloader($mockFabric);
        $downloader->downloadFiles($urls, false, 0);

        $this->assertEquals(2, $callCount);
        $this->assertCount(3, $receivedBatches[0]);
        $this->assertCount(2, $receivedBatches[1]);
    }

    /**
     * @test
     */
    public function testReducesBatchSizeToMinimumWhenMultipleSuggestions()
    {
        global $apbct;
        $apbct->fw_stats['multi_request_batch_size'] = 10;

        $urls = [];
        for ($i = 0; $i < 20; $i++) {
            $urls[] = 'https://example.com/file' . $i . '.gz';
        }

        $callCount = 0;

        $mockFabric = $this->getMockBuilder(HTTPMultiRequestService::class)
            ->disableOriginalConstructor()
            ->setMethods(['setMultiContract', 'getFailedURLs', 'writeSuccessURLsContent'])
            ->getMock();

        $mockFabric->method('setMultiContract')
            ->willReturnCallback(function($batchUrls) use ($mockFabric, &$callCount) {
                $callCount++;
                $mockFabric->process_done = true;
                $mockFabric->suggest_batch_reduce_to = $callCount === 1 ? 7 : 5;
                return $mockFabric;
            });

        $mockFabric->method('getFailedURLs')
            ->willReturnCallback(function() use (&$callCount, $urls) {
                $batchStart = ($callCount - 1) * 10;
                return [$urls[$batchStart]];
            });

        $mockFabric->method('writeSuccessURLsContent')
            ->willReturnCallback(function() use (&$callCount, $urls) {
                $batchStart = ($callCount - 1) * 10;
                $batchSize = min(10, count($urls) - $batchStart);
                $result = [];
                for ($i = 1; $i < $batchSize; $i++) {
                    $result[] = $urls[$batchStart + $i];
                }
                return $result;
            });

        $downloader = new SFWFilesDownloader($mockFabric);
        $downloader->downloadFiles($urls, false, 0);

        $this->assertEquals(5, $apbct->fw_stats['multi_request_batch_size']);
    }

    /**
     * @test
     */
    public function testReturnsErrorWhenNotAllFilesDownloadedAfterBatches()
    {
        $urls = [
            'https://example.com/file1.gz',
            'https://example.com/file2.gz'
        ];

        $mockFabric = $this->getMockBuilder(HTTPMultiRequestService::class)
            ->disableOriginalConstructor()
            ->setMethods(['setMultiContract', 'getFailedURLs', 'writeSuccessURLsContent', 'getContractsErrors'])
            ->getMock();

        $mockFabric->method('setMultiContract')
            ->willReturnCallback(function() use ($mockFabric) {
                $mockFabric->process_done = true;
                return $mockFabric;
            });

        $mockFabric->method('getFailedURLs')
            ->willReturn([]);

        $mockFabric->method('writeSuccessURLsContent')
            ->willReturn(['https://example.com/file1.gz']);

        $mockFabric->method('getContractsErrors')
            ->willReturn('[url1]:[error1],[url2]:[error2]');

        $downloader = new SFWFilesDownloader($mockFabric);
        $result = $downloader->downloadFiles($urls, false, 0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('NOT COMPLETED - STOP UPDATE', $result['error']);
        $this->assertStringContainsString('[url1]:[error1],[url2]:[error2]', $result['error']);
    }

    /**
     * @test
     */
    public function testRemovesDuplicateUrlsAndResetsKeys()
    {
        $urls = [
            5 => 'https://example.com/file1.gz',
            7 => 'https://example.com/file1.gz',
            9 => 'https://example.com/file2.gz'
        ];

        $receivedUrls = null;

        $mockFabric = $this->getMockBuilder(HTTPMultiRequestService::class)
            ->disableOriginalConstructor()
            ->setMethods(['setMultiContract', 'getFailedURLs', 'writeSuccessURLsContent'])
            ->getMock();

        $mockFabric->method('setMultiContract')
            ->willReturnCallback(function($batchUrls) use ($mockFabric, &$receivedUrls) {
                $receivedUrls = $batchUrls;
                $mockFabric->process_done = true;
                return $mockFabric;
            });

        $mockFabric->method('getFailedURLs')
            ->willReturn([]);

        $mockFabric->method('writeSuccessURLsContent')
            ->willReturn(['https://example.com/file1.gz', 'https://example.com/file2.gz']);

        $downloader = new SFWFilesDownloader($mockFabric);
        $downloader->downloadFiles($urls, false, 0);

        $this->assertCount(2, $receivedUrls);
        $this->assertEquals(['https://example.com/file1.gz', 'https://example.com/file2.gz'], $receivedUrls);
    }
}
