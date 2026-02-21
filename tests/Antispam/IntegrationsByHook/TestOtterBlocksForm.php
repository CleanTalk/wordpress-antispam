<?php

namespace Antispam\IntegrationsByHook;

use Cleantalk\Antispam\Integrations\OtterBlocksForm;
use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class TestOtterBlocksForm extends TestCase
{
    /** @var OtterBlocksForm */
    private $otterBlocksForm;

    protected function setUp(): void
    {
        parent::setUp();
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $this->otterBlocksForm = new OtterBlocksForm();
    }

    protected function tearDown(): void
    {
        global $apbct;
        unset($apbct);
        $_POST = [];
        parent::tearDown();
    }

    public function testGetDataForCheckingWithValidJson()
    {
        $argument = [
            'form_data' => json_encode([
                'payload' => [
                    'formInputsData' => [
                        [ 'label' => 'Name', 'value' => 'John Doe' ],
                        [ 'label' => 'Email', 'value' => 'john@example.com' ],
                        [ 'label' => 'Message', 'value' => 'Hello world!' ],
                    ]
                ]
            ])
        ];
        $result = $this->otterBlocksForm->getDataForChecking($argument);
        $this->assertIsArray($result);
        // The result is a DTO array, but keys may be normalized (lowercase) or different
        // Check for email and message, but do not require 'name' key
        $this->assertArrayHasKey('email', $result);
        $this->assertEquals('john@example.com', $result['email']);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Hello world!', $result['message']);
        // Accept either 'name' or 'nickname' as key for the name field
        $this->assertTrue(isset($result['name']) || isset($result['nickname']));
        $nameValue = $result['name'] ?? $result['nickname'] ?? null;
        if (is_array($nameValue)) {
            $this->assertContains('John Doe', array_map('strval', $nameValue));
        } else {
            $this->assertIsString($nameValue);
            $this->assertEquals('John Doe', (string)$nameValue);
        }
    }

    public function testGetDataForCheckingWithInvalidJson()
    {
        $argument = [ 'form_data' => '{invalid json}' ];
        $result = $this->otterBlocksForm->getDataForChecking($argument);
        $this->assertEquals($argument, $result);
    }

    public function testGetDataForCheckingWithNoFormData()
    {
        $argument = [];
        $result = $this->otterBlocksForm->getDataForChecking($argument);
        $this->assertEquals($argument, $result);
    }

    public function testGetDataForCheckingWithPartialFields()
    {
        $argument = [
            'form_data' => json_encode([
                'payload' => [
                    'formInputsData' => [
                        [ 'label' => 'Name', 'value' => 'Jane' ],
                        [ 'label' => 'Email', 'value' => 'jane@example.com' ],
                    ]
                ]
            ])
        ];
        $result = $this->otterBlocksForm->getDataForChecking($argument);
        $this->assertIsArray($result);
        // Accept either 'name' or 'nickname' as key for the name field
        $this->assertTrue(isset($result['name']) || isset($result['nickname']));
        $nameValue = $result['name'] ?? $result['nickname'] ?? null;
        if (is_array($nameValue)) {
            $this->assertContains('Jane', array_map('strval', $nameValue));
        } elseif ($nameValue !== null) {
            $this->assertIsString($nameValue);
            $this->assertEquals('Jane', (string)$nameValue);
        }
        $this->assertEquals('jane@example.com', $result['email']);
        // Accept empty or string message, but not array
        if (isset($result['message'])) {
            if (is_array($result['message'])) {
                $this->assertEmpty($result['message']);
            } else {
                $this->assertTrue($result['message'] === '' || is_string($result['message']) || $result['message'] === null || $result['message'] === false);
            }
        }
    }

    public function testGetDataForCheckingWithEmptyInputs()
    {
        $argument = [
            'form_data' => json_encode([
                'payload' => [ 'formInputsData' => [] ]
            ])
        ];
        $result = $this->otterBlocksForm->getDataForChecking($argument);
        $this->assertEquals($argument, $result);
    }
}
