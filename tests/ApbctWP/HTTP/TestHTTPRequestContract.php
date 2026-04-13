<?php

namespace Cleantalk\ApbctWP\Tests\HTTP;

use Cleantalk\ApbctWP\HTTP\HTTPRequestContract;
use PHPUnit\Framework\TestCase;

class TestHTTPRequestContract extends TestCase
{
    /**
     * @test
     */
    public function testConstructorSetsUrlAndDefaultValues()
    {
        $url = 'https://example.com/file1.gz';

        $contract = new HTTPRequestContract($url);

        $this->assertEquals($url, $contract->url);
        $this->assertEquals('', $contract->content);
        $this->assertFalse($contract->success);
        $this->assertNull($contract->error_msg);
    }

    /**
     * @test
     */
    public function testPropertiesCanBeModified()
    {
        $contract = new HTTPRequestContract('https://example.com/file1.gz');

        $contract->content = 'test content';
        $contract->success = true;
        $contract->error_msg = 'test error';

        $this->assertEquals('test content', $contract->content);
        $this->assertTrue($contract->success);
        $this->assertEquals('test error', $contract->error_msg);
    }

    /**
     * @test
     */
    public function testSuccessStateWithContent()
    {
        $contract = new HTTPRequestContract('https://example.com/file1.gz');

        $contract->success = true;
        $contract->content = 'downloaded content';

        $this->assertTrue($contract->success);
        $this->assertNotEmpty($contract->content);
        $this->assertNull($contract->error_msg);
    }

    /**
     * @test
     */
    public function testFailureStateWithError()
    {
        $contract = new HTTPRequestContract('https://example.com/file1.gz');

        $contract->success = false;
        $contract->error_msg = 'Connection timeout';

        $this->assertFalse($contract->success);
        $this->assertEquals('Connection timeout', $contract->error_msg);
        $this->assertEmpty($contract->content);
    }
}
