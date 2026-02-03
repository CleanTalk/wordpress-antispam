<?php

use Cleantalk\Antispam\Cleantalk;
use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\Antispam\CleantalkResponse;
use Cleantalk\ApbctWP\State;

class CleantalkTest extends \PHPUnit\Framework\TestCase
{
    protected $ct;
    protected $ct_request;

    public function setUp(): void
    {
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));

        $this->ct_request = new CleantalkRequest();
        $this->ct_request->auth_key = 'mockKeyAny';
    }

    public function testIsAllowMessageWithMock()
    {
        // Create mock of Cleantalk object
        $ctMock = $this->getMockBuilder(Cleantalk::class)
                       ->setMethods(['httpRequest']) // Mock only httpRequest method
                       ->getMock();

        // Set expected response
        $expectedResponse = new \stdClass();
        $expectedResponse->allow = 0;
        $expectedResponse->comment = "Test comment";
        $expectedResponse->errno = 0;
        $expectedResponse->errstr = "";

        // Expect that httpRequest will be called 1 time with any parameters
        // and return our fake response
        $ctMock->expects($this->once())
               ->method('httpRequest')
               ->willReturn(new CleantalkResponse($expectedResponse, null));

        // Set properties for test
        $ctMock->server_url = 'https://example.com';

        // Run test
        $this->ct_request->sender_email = 's@cleantalk.org';
        $this->ct_request->message = 'stop_word bad message';
        $result = $ctMock->isAllowMessage($this->ct_request);

        // Check result
        $this->assertEquals(0, $result->allow);
    }

    // Alternative variant with more full control
    public function testIsAllowMessageWithFullControl()
    {
        // Create mock with full control over the process
        $ctMock = $this->getMockBuilder(Cleantalk::class)
                       ->setMethods(['createMsg', 'httpRequest'])
                       ->getMock();

        // Mock createMsg to return expected request
        $expectedRequest = new CleantalkRequest();
        $expectedRequest->auth_key = $this->ct_request->auth_key;
        $expectedRequest->sender_email = 's@cleantalk.org';
        $expectedRequest->message = 'stop_word bad message';
        $expectedRequest->method_name = 'check_message';
        // ... set other necessary properties ...

        $ctMock->expects($this->once())
               ->method('createMsg')
               ->with('check_message', $this->isInstanceOf(CleantalkRequest::class))
               ->willReturn($expectedRequest);

        // Mock httpRequest to return fake response
        $mockResponse = new \stdClass();
        $mockResponse->allow = 0;
        $mockResponse->comment = "Forbidden";
        $mockResponse->errno = 0;
        $mockResponse->errstr = "";

        $ctMock->expects($this->once())
               ->method('httpRequest')
               ->with($expectedRequest)
               ->willReturn(new CleantalkResponse($mockResponse, null));

        // Run test
        $this->ct_request->sender_email = 's@cleantalk.org';
        $this->ct_request->message = 'stop_word bad message';
        $result = $ctMock->isAllowMessage($this->ct_request);

        $this->assertEquals(0, $result->allow);
        $this->assertEquals("Forbidden", $result->comment);
    }

    public function testIsAllowMessageAllow()
    {
        // Test for allowed message
        $ctMock = $this->getMockBuilder(Cleantalk::class)
                       ->setMethods(['httpRequest'])
                       ->getMock();

        $expectedResponse = new \stdClass();
        $expectedResponse->allow = 1; // Message allowed
        $expectedResponse->comment = "";
        $expectedResponse->errno = 0;
        $expectedResponse->errstr = "";

        $ctMock->expects($this->once())
               ->method('httpRequest')
               ->willReturn(new CleantalkResponse($expectedResponse, null));

        $ctMock->server_url = 'https://example.com';

        $this->ct_request->sender_email = 'good@example.com';
        $this->ct_request->message = 'Normal message';
        $result = $ctMock->isAllowMessage($this->ct_request);

        $this->assertEquals(1, $result->allow);
        $this->assertEquals("", $result->comment);
    }
}
