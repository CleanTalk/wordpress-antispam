<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

class TestSmartForms extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = new SmartForms();
    }

    protected function tearDown(): void
    {
        $_POST = [];
        Post::getInstance()->variables = [];
        parent::tearDown();
    }

    public function testGetDataForCheckingReturnsNullForWrongAction()
    {
        $_POST['action'] = 'some_other_action';

        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    public function testGetDataForCheckingWithValidFormString()
    {
        $_POST['action'] = 'rednao_smart_forms_save_form_values';
        $_POST['form_id'] = '1';
        $_POST['formString'] = json_encode([
            '1' => 'John Smith',
            '2' => 'john@example.com',
            '3' => 'Sample message',
        ]);

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('john@example.com', $result['email']);
    }

    public function testGetDataForCheckingWithEscapedJson()
    {
        $_POST['action'] = 'rednao_smart_forms_save_form_values';
        $_POST['form_id'] = '1';
        $_POST['formString'] = '{\"1\":\"Jane Doe\",\"2\":\"jane@example.com\"}';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('jane@example.com', $result['email']);
    }

    public function testGetDataForCheckingWithNestedFieldValues()
    {
        $_POST['action'] = 'rednao_smart_forms_save_form_values';
        $_POST['form_id'] = '1';
        $_POST['formString'] = json_encode([
            'rnField1' => ['value' => 'John Smith'],
            'rnField2' => ['value' => 'john@example.com'],
            'rnField3' => ['value' => 'Sample message'],
        ]);

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('john@example.com', $result['email']);
    }

    public function testGetDataForCheckingWithInvalidFormString()
    {
        $_POST['action'] = 'rednao_smart_forms_save_form_values';
        $_POST['form_id'] = '1';
        $_POST['formString'] = 'not-a-json';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }
}
