<?php

namespace Cleantalk\ApbctWP\Tests\HTTP;

use Cleantalk\ApbctWP\HTTP\HTTPMultiRequestFactory;
use Cleantalk\ApbctWP\HTTP\HTTPRequestContract;
use PHPUnit\Framework\TestCase;

class TestHTTPMultiRequestFactory extends TestCase
{
    private $testFolder;

    protected function setUp()
    {
        parent::setUp();

        $this->testFolder = sys_get_temp_dir() . '/test_fabric_' . time() . '/';
        if (!is_dir($this->testFolder)) {
            mkdir($this->testFolder, 0777, true);
        }
    }

    protected function tearDown()
    {
        parent::tearDown();

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
    }

    /**
     * @test
     */
    public function testPrepareContractsWithEmptyUrlsSetsError()
    {
        $fabric = $this->getMockBuilder(HTTPMultiRequestFactory::class)
            ->setMethods(['executeMultiContract', 'sendMultiRequest'])
            ->getMock();

        $fabric->expects($this->never())
            ->method('sendMultiRequest');

        $fabric->setMultiContract([]);

        $this->assertNotNull($fabric->error_msg);
        $this->assertStringContainsString('URLS SHOULD BE NOT EMPTY', $fabric->error_msg);
        $this->assertEmpty($fabric->contracts);
    }

    /**
     * @test
     */
    public function testPrepareContractsWithNonStringUrlSetsError()
    {
        $fabric = $this->getMockBuilder(HTTPMultiRequestFactory::class)
            ->setMethods(['executeMultiContract'])
            ->getMock();

        $urls = [
            'https://example.com/file1.gz',
            123, // Invalid
            'https://example.com/file3.gz'
        ];

        $fabric->setMultiContract($urls);

        $this->assertNotNull($fabric->error_msg);
        $this->assertStringContainsString('SINGLE URL SHOULD BE A STRING', $fabric->error_msg);
        $this->assertEmpty($fabric->contracts);
    }

    /**
     * @test
     */
    public function testPrepareContractsCreatesHTTPRequestContracts()
    {
        $fabric = $this->getMockBuilder(HTTPMultiRequestFactory::class)
            ->setMethods(['executeMultiContract'])
            ->getMock();

        $urls = [
            'https://example.com/file1.gz',
            'https://example.com/file2.gz'
        ];

        $fabric->setMultiContract($urls);

        $this->assertCount(2, $fabric->contracts);
        $this->assertContainsOnlyInstancesOf(HTTPRequestContract::class, $fabric->contracts);
        $this->assertEquals($urls[0], $fabric->contracts[0]->url);
        $this->assertEquals($urls[1], $fabric->contracts[1]->url);
    }

    /**
     * @test
     */
    public function testGetAllURLsReturnsAllContractUrls()
    {
        $fabric = $this->getMockBuilder(HTTPMultiRequestFactory::class)
            ->setMethods(['executeMultiContract'])
            ->getMock();

        $urls = [
            'https://example.com/file1.gz',
            'https://example.com/file2.gz'
        ];

        $fabric->setMultiContract($urls);

        $this->assertEquals($urls, $fabric->getAllURLs());
    }

    /**
     * @test
     */
    public function testGetFailedURLsReturnsUrlsWithNoSuccess()
    {
        $fabric = new HTTPMultiRequestFactory();

        $contract1 = new HTTPRequestContract('https://example.com/file1.gz');
        $contract1->success = true;
        $contract1->content = 'content';

        $contract2 = new HTTPRequestContract('https://example.com/file2.gz');
        $contract2->success = false;

        $contract3 = new HTTPRequestContract('https://example.com/file3.gz');
        $contract3->success = true;
        $contract3->content = '';

        $fabric->contracts = [$contract1, $contract2, $contract3];

        $failed = $fabric->getFailedURLs();

        $this->assertCount(2, $failed);
        $this->assertContains('https://example.com/file2.gz', $failed);
        $this->assertContains('https://example.com/file3.gz', $failed);
    }

    /**
     * @test
     */
    public function testGetSuccessURLsReturnsOnlySuccessfulUrls()
    {
        $fabric = new HTTPMultiRequestFactory();

        $contract1 = new HTTPRequestContract('https://example.com/file1.gz');
        $contract1->success = true;
        $contract1->content = 'content1';

        $contract2 = new HTTPRequestContract('https://example.com/file2.gz');
        $contract2->success = false;

        $contract3 = new HTTPRequestContract('https://example.com/file3.gz');
        $contract3->success = true;
        $contract3->content = 'content3';

        $fabric->contracts = [$contract1, $contract2, $contract3];

        $success = $fabric->getSuccessURLs();

        $this->assertCount(2, $success);
        $this->assertEquals(['https://example.com/file1.gz', 'https://example.com/file3.gz'], $success);
    }

    /**
     * @test
     */
    public function testFillMultiContractWithErrorArraySetsError()
    {
        $fabric = new HTTPMultiRequestFactory();

        $fabric->fillMultiContract(['error' => 'CURL_ERROR']);

        $this->assertNotNull($fabric->error_msg);
        $this->assertStringContainsString('HTTP_MULTI_RESULT ERROR', $fabric->error_msg);
    }

    /**
     * @test
     */
    public function testFillMultiContractWithNonArraySetsError()
    {
        $fabric = new HTTPMultiRequestFactory();

        $fabric->fillMultiContract('not an array');

        $this->assertNotNull($fabric->error_msg);
        $this->assertStringContainsString('HTTP_MULTI_RESULT INVALID', $fabric->error_msg);
    }

    /**
     * @test
     */
    public function testFillMultiContractWithValidResultsFillsContracts()
    {
        $fabric = new HTTPMultiRequestFactory();

        $fabric->contracts = [
            new HTTPRequestContract('https://example.com/file1.gz'),
            new HTTPRequestContract('https://example.com/file2.gz')
        ];

        $results = [
            'https://example.com/file1.gz' => 'content for file1',
            'https://example.com/file2.gz' => 'content for file2'
        ];

        $fabric->fillMultiContract($results);

        $this->assertTrue($fabric->contracts[0]->success);
        $this->assertEquals('content for file1', $fabric->contracts[0]->content);
        $this->assertTrue($fabric->contracts[1]->success);
        $this->assertEquals('content for file2', $fabric->contracts[1]->content);
        $this->assertTrue($fabric->process_done);
    }

    /**
     * @test
     */
    public function testFillMultiContractWithNonStringContentSetsContractError()
    {
        $fabric = new HTTPMultiRequestFactory();

        $fabric->contracts = [
            new HTTPRequestContract('https://example.com/file1.gz')
        ];

        $results = [
            'https://example.com/file1.gz' => 123
        ];

        $fabric->fillMultiContract($results);

        $this->assertFalse($fabric->contracts[0]->success);
        $this->assertNotNull($fabric->contracts[0]->error_msg);
        $this->assertStringContainsString('SHOULD BE A STRING', $fabric->contracts[0]->error_msg);
    }

    /**
     * @test
     */
    public function testFillMultiContractWithEmptyContentSetsContractError()
    {
        $fabric = new HTTPMultiRequestFactory();

        $fabric->contracts = [
            new HTTPRequestContract('https://example.com/file1.gz')
        ];

        $results = [
            'https://example.com/file1.gz' => ''
        ];

        $fabric->fillMultiContract($results);

        $this->assertFalse($fabric->contracts[0]->success);
        $this->assertNotNull($fabric->contracts[0]->error_msg);
        $this->assertStringContainsString('SHOULD BE NOT EMPTY', $fabric->contracts[0]->error_msg);
    }

    /**
     * @test
     */
    public function testFillMultiContractWithPartialSuccessSuggestsBatchReduce()
    {
        $fabric = new HTTPMultiRequestFactory();

        $fabric->contracts = [
            new HTTPRequestContract('https://example.com/file1.gz'),
            new HTTPRequestContract('https://example.com/file2.gz'),
            new HTTPRequestContract('https://example.com/file3.gz')
        ];

        $results = [
            'https://example.com/file1.gz' => 'content1',
            'https://example.com/file3.gz' => 'content3'
        ];

        $fabric->fillMultiContract($results);

        $this->assertEquals(2, $fabric->suggest_batch_reduce_to);
        $this->assertTrue($fabric->process_done);
    }

    /**
     * @test
     */
    public function testFillMultiContractWithAllFailedSuggestsMinimumBatchSize()
    {
        $fabric = new HTTPMultiRequestFactory();

        $fabric->contracts = [
            new HTTPRequestContract('https://example.com/file1.gz'),
            new HTTPRequestContract('https://example.com/file2.gz')
        ];

        $results = [];

        $fabric->fillMultiContract($results);

        $this->assertEquals(2, $fabric->suggest_batch_reduce_to);
        $this->assertTrue($fabric->process_done);
    }

    /**
     * @test
     */
    public function testFillMultiContractWithAllSuccessDoesNotSuggestBatchReduce()
    {
        $fabric = new HTTPMultiRequestFactory();

        $fabric->contracts = [
            new HTTPRequestContract('https://example.com/file1.gz'),
            new HTTPRequestContract('https://example.com/file2.gz')
        ];

        $results = [
            'https://example.com/file1.gz' => 'content1',
            'https://example.com/file2.gz' => 'content2'
        ];

        $fabric->fillMultiContract($results);

        $this->assertFalse($fabric->suggest_batch_reduce_to);
        $this->assertTrue($fabric->process_done);
    }

    /**
     * @test
     */
    public function testGetContractsErrorsReturnsFormattedErrors()
    {
        $fabric = new HTTPMultiRequestFactory();

        $contract1 = new HTTPRequestContract('https://example.com/file1.gz');
        $contract1->error_msg = 'Connection timeout';

        $contract2 = new HTTPRequestContract('https://example.com/file2.gz');
        $contract2->success = true;
        $contract2->content = 'content';

        $contract3 = new HTTPRequestContract('https://example.com/file3.gz');
        $contract3->error_msg = '404 Not Found';

        $fabric->contracts = [$contract1, $contract2, $contract3];

        $errors = $fabric->getContractsErrors();

        $this->assertIsString($errors);
        $this->assertStringContainsString('file1.gz', $errors);
        $this->assertStringContainsString('Connection timeout', $errors);
        $this->assertStringContainsString('file3.gz', $errors);
        $this->assertStringContainsString('404 Not Found', $errors);
        $this->assertStringNotContainsString('file2', $errors);
    }

    /**
     * @test
     */
    public function testGetContractsErrorsReturnsFalseWhenNoErrors()
    {
        $fabric = new HTTPMultiRequestFactory();

        $contract1 = new HTTPRequestContract('https://example.com/file1.gz');
        $contract1->success = true;
        $contract1->content = 'content1';

        $contract2 = new HTTPRequestContract('https://example.com/file2.gz');
        $contract2->success = true;
        $contract2->content = 'content2';

        $fabric->contracts = [$contract1, $contract2];

        $errors = $fabric->getContractsErrors();

        $this->assertFalse($errors);
    }

    /**
     * @test
     */
    public function testWriteSuccessURLsContentWritesFiles()
    {
        $fabric = new HTTPMultiRequestFactory();

        $contract1 = new HTTPRequestContract('https://example.com/file1.gz');
        $contract1->success = true;
        $contract1->content = 'content1';

        $contract2 = new HTTPRequestContract('https://example.com/file2.gz');
        $contract2->success = true;
        $contract2->content = 'content2';

        $fabric->contracts = [$contract1, $contract2];

        $result = $fabric->writeSuccessURLsContent($this->testFolder);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertFileExists($this->testFolder . 'file1.gz');
        $this->assertFileExists($this->testFolder . 'file2.gz');
        $this->assertEquals('content1', file_get_contents($this->testFolder . 'file1.gz'));
        $this->assertEquals('content2', file_get_contents($this->testFolder . 'file2.gz'));
    }

    /**
     * @test
     */
    public function testWriteSuccessURLsContentSkipsFailedContracts()
    {
        $fabric = new HTTPMultiRequestFactory();

        $contract1 = new HTTPRequestContract('https://example.com/file1.gz');
        $contract1->success = true;
        $contract1->content = 'content1';

        $contract2 = new HTTPRequestContract('https://example.com/file2.gz');
        $contract2->success = false;

        $contract3 = new HTTPRequestContract('https://example.com/file3.gz');
        $contract3->success = true;
        $contract3->content = 'content3';

        $fabric->contracts = [$contract1, $contract2, $contract3];

        $result = $fabric->writeSuccessURLsContent($this->testFolder);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertFileExists($this->testFolder . 'file1.gz');
        $this->assertFileNotExists($this->testFolder . 'file2.gz');
        $this->assertFileExists($this->testFolder . 'file3.gz');
    }

    /**
     * @test
     */
    public function testWriteSuccessURLsContentReturnsErrorWhenDirectoryNotExists()
    {
        $fabric = new HTTPMultiRequestFactory();

        $contract1 = new HTTPRequestContract('https://example.com/file1.gz');
        $contract1->success = true;
        $contract1->content = 'content1';

        $fabric->contracts = [$contract1];

        $result = $fabric->writeSuccessURLsContent('/nonexistent/path/');

        $this->assertIsString($result);
        $this->assertStringContainsString('CAN NOT WRITE TO DIRECTORY', $result);
    }

    /**
     * @test
     */
    public function testWriteSuccessURLsContentReturnsErrorWhenDirectoryNotWritable()
    {
        $fabric = new HTTPMultiRequestFactory();

        $contract1 = new HTTPRequestContract('https://example.com/file1.gz');
        $contract1->success = true;
        $contract1->content = 'content1';

        $fabric->contracts = [$contract1];

        // Use root directory which is typically not writable
        $result = $fabric->writeSuccessURLsContent('/nonexist');

        $this->assertIsString($result);
        $this->assertStringContainsString('CAN NOT WRITE', $result);
    }

    /**
     * @test
     */
    public function testSetMultiContractResetsStateOnEachCall()
    {
        $fabric = $this->getMockBuilder(HTTPMultiRequestFactory::class)
            ->setMethods(['executeMultiContract'])
            ->getMock();

        // First call
        $fabric->setMultiContract(['https://example.com/file1.gz']);
        $this->assertCount(1, $fabric->contracts);
        $fabric->process_done = true;
        $fabric->suggest_batch_reduce_to = 5;
        $fabric->error_msg = 'some error';

        // Second call should reset everything
        $fabric->setMultiContract(['https://example.com/file2.gz', 'https://example.com/file3.gz']);

        $this->assertCount(2, $fabric->contracts);
        $this->assertFalse($fabric->suggest_batch_reduce_to);
        $this->assertNull($fabric->error_msg);
        $this->assertFalse($fabric->process_done);
    }

    /**
     * @test
     */
    public function testSetMultiContractReturnsItself()
    {
        $fabric = $this->getMockBuilder(HTTPMultiRequestFactory::class)
            ->setMethods(['executeMultiContract'])
            ->getMock();

        $result = $fabric->setMultiContract(['https://example.com/file1.gz']);

        $this->assertInstanceOf(HTTPMultiRequestFactory::class, $result);
        $this->assertSame($fabric, $result);
    }

    /**
     * @test
     */
    public function testFillMultiContractReturnsItself()
    {
        $fabric = new HTTPMultiRequestFactory();

        $result = $fabric->fillMultiContract([]);

        $this->assertInstanceOf(HTTPMultiRequestFactory::class, $result);
        $this->assertSame($fabric, $result);
    }

    /**
     * @test
     */
    public function testWriteSuccessURLsContentHandlesFileWriteFailure()
    {
        $fabric = $this->getMockBuilder(HTTPMultiRequestFactory::class)
            ->setMethods(['writeFile'])
            ->getMock();

        // Mock writeFile to return false (simulating write failure)
        $fabric->expects($this->once())
            ->method('writeFile')
            ->willReturn(false);

        $contract1 = new HTTPRequestContract('https://example.com/file1.gz');
        $contract1->success = true;
        $contract1->content = 'content1';

        $fabric->contracts = [$contract1];

        $result = $fabric->writeSuccessURLsContent($this->testFolder);

        $this->assertIsString($result);
        $this->assertStringContainsString('CAN NOT WRITE TO FILE', $result);
    }
}
