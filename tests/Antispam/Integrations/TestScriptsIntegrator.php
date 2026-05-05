<?php

use Cleantalk\Antispam\ScriptsIntegration\CleantalkScriptsIntegrator;
use Cleantalk\Antispam\ScriptsIntegration\ScriptIntegrationPlugin;
use PHPUnit\Framework\TestCase;

class TestScriptsIntegrator extends TestCase
{
    protected function setUp(): void
    {
        remove_all_actions('test_hook');
        remove_all_actions('hook1');
        remove_all_actions('hook2');
        remove_all_actions('same_hook');
    }

    /**
     * getPluginsToInline
     */
    public function testGetPluginsToInlineLoadsPlugin()
    {
        $obj = new CleantalkScriptsIntegrator();

        $plugins = [
            new TestPlugin(true, true, true, 'hook1'),
        ];

        $result = $obj->getPluginsToInline($plugins);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('hook1', $result);
    }

    public function testGetPluginsToInlineSkipsInvalidPlugin()
    {
        $obj = new CleantalkScriptsIntegrator();

        $plugins = [
            new TestPlugin(false, true, true, 'hook1'),
        ];

        $result = $obj->getPluginsToInline($plugins);

        $this->assertEmpty($result);
    }

    public function testFirstPluginWinsForSameHook()
    {
        $obj = new CleantalkScriptsIntegrator();

        $plugins = [
            new TestPlugin(true, true, true, 'same_hook'),
            new TestPlugin(true, true, true, 'same_hook'),
        ];

        $result = $obj->getPluginsToInline($plugins);

        $this->assertCount(1, $result);
    }

    public function testMultipleHooksLoaded()
    {
        $obj = new CleantalkScriptsIntegrator();

        $plugins = [
            new TestPlugin(true, true, true, 'hook1'),
            new TestPlugin(true, true, true, 'hook2'),
        ];

        $result = $obj->getPluginsToInline($plugins);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('hook1', $result);
        $this->assertArrayHasKey('hook2', $result);
    }

    /**
     * run()
     */
    public function testRunRegistersHooks()
    {
        $obj = $this->getMockBuilder(CleantalkScriptsIntegrator::class)
            ->onlyMethods(['getIntegrations'])
            ->getMock();

        $obj->method('getIntegrations')->willReturn([
            new TestPlugin(true, true, true, 'hook1'),
        ]);

        $obj->run();

        $this->assertTrue(has_action('hook1') !== false);
    }

    /**
     * run()
     */
    public function testRunDoesNothingIfNoPlugins()
    {
        $obj = $this->getMockBuilder(CleantalkScriptsIntegrator::class)
            ->onlyMethods(['getIntegrations'])
            ->getMock();

        $obj->method('getIntegrations')->willReturn([]);

        $obj->run();

        $this->assertEmpty($obj->plugins_loaded);
        $this->assertFalse(has_action('hook1'));
    }

    /**
     * run()
     */
    public function testRunResetsState()
    {
        $obj = new CleantalkScriptsIntegrator();

        $obj->plugins_loaded = [
            'old_hook' => new TestPlugin()
        ];

        $mock = $this->getMockBuilder(CleantalkScriptsIntegrator::class)
            ->onlyMethods(['getIntegrations'])
            ->getMock();

        $mock->plugins_loaded = $obj->plugins_loaded;

        $mock->method('getIntegrations')->willReturn([]);

        $mock->run();

        $this->assertEmpty($mock->plugins_loaded);
    }

    /**
     * run()
     */
    public function testRunActuallyExecutesIntegrate()
    {
        $plugin = new TestPlugin(true, true, true, 'hook1');

        $obj = $this->getMockBuilder(CleantalkScriptsIntegrator::class)
            ->onlyMethods(['getIntegrations'])
            ->getMock();

        $obj->method('getIntegrations')->willReturn([$plugin]);

        $obj->run();

        $callbacks = $this->get_registered_hooks('hook1');

        $this->assertNotEmpty($callbacks);

        $callback = null;

        foreach ($callbacks as $priority => $group) {
            foreach ($group as $cb) {
                $callback = $cb['function'];
                break 2;
            }
        }

        $this->assertIsCallable($callback);

        $callback();

        $this->assertTrue($plugin->called);
    }

    /**
     * helper: достать callbacks из WP
     */
    private function get_registered_hooks($hook)
    {
        global $wp_filter;
        return isset($wp_filter[$hook]) ? $wp_filter[$hook]->callbacks : [];
    }
}


/**
 * Test plugin
 */
class TestPlugin extends ScriptIntegrationPlugin
{
    public $hook_name = 'test_hook';
    public $plugin_file = 'test/plugin.php';
    public $uri_chunk = 'test';

    public $called = false;

    public function __construct($active = true, $in_uri = true, $additional = true, $hook = 'test_hook')
    {
        $this->plugin_file = 'test/plugin.php';
        $this->uri_chunk = 'test';
        $this->hook_name = $hook;

        $this->is_plugin_active = $active;
        $this->is_in_uri = $in_uri;
        $this->additional_checks_passed = $additional;
    }

    public function integrate()
    {
        $this->called = true;
    }
}
