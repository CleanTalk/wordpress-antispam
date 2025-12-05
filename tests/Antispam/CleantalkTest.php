<?php

use Cleantalk\Antispam\Cleantalk;
use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\Antispam\CleantalkResponse;
use Cleantalk\ApbctWP\State;


class CleantalkTest extends \PHPUnit\Framework\TestCase
{
    protected $ct;
    protected $ct_request;

    public function setUp()
    {
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));

        $this->ct_request = new CleantalkRequest();
        $this->ct_request->auth_key = 'mockKeyAny';
    }

    public function testIsAllowMessageWithMock()
    {
        // Создаем мок объекта Cleantalk
        $ctMock = $this->getMockBuilder(Cleantalk::class)
                       ->setMethods(['httpRequest']) // Мокаем только метод httpRequest
                       ->getMock();

        // Настраиваем ожидаемый ответ
        $expectedResponse = new \stdClass();
        $expectedResponse->allow = 0;
        $expectedResponse->comment = "Test comment";
        $expectedResponse->errno = 0;
        $expectedResponse->errstr = "";

        // Ожидаем, что httpRequest будет вызван 1 раз с любыми параметрами
        // и вернет наш фиктивный ответ
        $ctMock->expects($this->once())
               ->method('httpRequest')
               ->willReturn(new CleantalkResponse($expectedResponse, null));

        // Устанавливаем свойства для теста
        $ctMock->server_url = 'https://example.com';

        // Выполняем тест
        $this->ct_request->sender_email = 's@cleantalk.org';
        $this->ct_request->message = 'stop_word bad message';
        $result = $ctMock->isAllowMessage($this->ct_request);

        // Проверяем результат
        $this->assertEquals(0, $result->allow);
    }

    // Альтернативный вариант с более полным контролем
    public function testIsAllowMessageWithFullControl()
    {
        // Создаем мок с полным контролем над процессом
        $ctMock = $this->getMockBuilder(Cleantalk::class)
                       ->setMethods(['createMsg', 'httpRequest'])
                       ->getMock();

        // Мокаем createMsg чтобы он возвращал ожидаемый запрос
        $expectedRequest = new CleantalkRequest();
        $expectedRequest->auth_key = $this->ct_request->auth_key;
        $expectedRequest->sender_email = 's@cleantalk.org';
        $expectedRequest->message = 'stop_word bad message';
        $expectedRequest->method_name = 'check_message';
        // ... установите другие необходимые свойства ...

        $ctMock->expects($this->once())
               ->method('createMsg')
               ->with('check_message', $this->isInstanceOf(CleantalkRequest::class))
               ->willReturn($expectedRequest);

        // Мокаем httpRequest для возврата фиктивного ответа
        $mockResponse = new \stdClass();
        $mockResponse->allow = 0;
        $mockResponse->comment = "Forbidden";
        $mockResponse->errno = 0;
        $mockResponse->errstr = "";

        $ctMock->expects($this->once())
               ->method('httpRequest')
               ->with($expectedRequest)
               ->willReturn(new CleantalkResponse($mockResponse, null));

        // Выполняем тест
        $this->ct_request->sender_email = 's@cleantalk.org';
        $this->ct_request->message = 'stop_word bad message';
        $result = $ctMock->isAllowMessage($this->ct_request);

        $this->assertEquals(0, $result->allow);
        $this->assertEquals("Forbidden", $result->comment);
    }

    public function testIsAllowMessageAllow()
    {
        // Тест для разрешенного сообщения
        $ctMock = $this->getMockBuilder(Cleantalk::class)
                       ->setMethods(['httpRequest'])
                       ->getMock();

        $expectedResponse = new \stdClass();
        $expectedResponse->allow = 1; // Сообщение разрешено
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
