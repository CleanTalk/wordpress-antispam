<?php

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Antispam\Integrations\CleantalkExternalForms;
use PHPUnit\Framework\TestCase;

class TestCleantalkExternalForms extends TestCase
{
    /**
     * @var CleantalkExternalForms
     */
    private $integration;

    /**
     * @var array
     */
    private $post_global;

    /**
     * @var array
     */
    private $server_global;

    protected function setUp(): void
    {
        $this->integration = new CleantalkExternalForms();
        $this->post_global = $_POST;
        $this->server_global = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->resetVariableCaches();
    }

    protected function tearDown(): void
    {
        $_POST = $this->post_global;
        $_SERVER = $this->server_global;
        $this->resetVariableCaches();
    }

    private function resetVariableCaches()
    {
        Post::getInstance()->variables = [];
        Server::getInstance()->variables = [];
    }

    public function testDoPrepareActionsRejectsJavascriptAction()
    {
        $_POST = array(
            'cleantalk_hidden_method' => 'POST',
            'cleantalk_hidden_action' => 'javascript:alert(String.fromCharCode(80,83,67,45,88,83,83));void 0',
        );
        $this->resetVariableCaches();

        $this->assertFalse($this->integration->doPrepareActions(null));
    }

    public function testDoPrepareActionsRejectsJavascriptHttpsBypass()
    {
        $_POST = array(
            'cleantalk_hidden_method' => 'POST',
            'cleantalk_hidden_action' => 'javascript://https://evil.com%0aalert(1)',
        );
        $this->resetVariableCaches();

        $this->assertFalse($this->integration->doPrepareActions(null));
    }

    public function testDoPrepareActionsRejectsDataUri()
    {
        $_POST = array(
            'cleantalk_hidden_method' => 'POST',
            'cleantalk_hidden_action' => 'data:text/html,<script>alert(1)</script>',
        );
        $this->resetVariableCaches();

        $this->assertFalse($this->integration->doPrepareActions(null));
    }

    public function testDoPrepareActionsRejectsInvalidMethod()
    {
        $_POST = array(
            'cleantalk_hidden_method' => 'PUT',
            'cleantalk_hidden_action' => 'https://example.com/form',
        );
        $this->resetVariableCaches();

        $this->assertFalse($this->integration->doPrepareActions(null));
    }

    public function testDoPrepareActionsAllowsHttpHttps()
    {
        $_POST = array(
            'cleantalk_hidden_method' => 'POST',
            'cleantalk_hidden_action' => 'https://example.com/form',
        );
        $this->resetVariableCaches();

        $this->assertTrue($this->integration->doPrepareActions(null));

        $_POST['cleantalk_hidden_action'] = 'http://45.137.81.184/';
        $this->resetVariableCaches();

        $this->assertTrue($this->integration->doPrepareActions(null));
    }
}
