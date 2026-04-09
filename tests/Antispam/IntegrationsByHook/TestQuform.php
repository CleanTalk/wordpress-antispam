<?php

namespace Cleantalk\Antispam\Integrations;

use PHPUnit\Framework\TestCase;
use stdClass;

class TestQuform extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = new QuForm();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testGetDataForCheckingRegisterCommon()
    {
        $argument = new QuformArgument(true);

        $result = $this->integration->getDataForChecking($argument);

        $this->assertIsArray($result);
        $this->assertEquals('john.doe@example.com', $result['email']);
        $this->assertEquals('', $result['nickname']); // NickName not detected at that integration
        $this->assertFalse($result['register']);
    }

    public function testGetDataForCheckingArgumentErrorWrongClass()
    {
        $result = $this->integration->getDataForChecking(new StdClass());

        $this->assertNull($result);
    }

    public function testGetDataForCheckingArgumentErrorNoMethods()
    {
        $result = $this->integration->getDataForChecking(new StdClass());

        $this->assertNull($result);
    }

    public function testCustomCommentType()
    {
        $argument = new QuformArgument(true);
        $this->integration->getDataForChecking($argument);
        $this->assertEquals('quforms_multipage', $this->integration->custom_comment_type);

        $argument = new QuformArgument(false);
        $this->integration->getDataForChecking($argument);
        $this->assertNotEmpty($this->integration->custom_comment_type);
        $this->assertEquals('quforms_singlepage', $this->integration->custom_comment_type);
    }

    public function testNativeCommentType()
    {
        $this->integration = new FluentForm();
        $this->integration->getDataForChecking(null);
        $this->assertEmpty($this->integration->custom_comment_type);
    }
}

class QuformArgument
{
    public function __construct($has_pages, $data_array = null)
    {
        $this->has_pages = $has_pages;
        $this->data_array = $data_array;
    }
    public function hasPages() {
        return $this->has_pages;
    }

    public function getValues()
    {
        return $this->data_array ?? array(
            'edd_user_login' => 'John',
            'edd_user_email' => 'john.doe@example.com'
        );
    }
}
