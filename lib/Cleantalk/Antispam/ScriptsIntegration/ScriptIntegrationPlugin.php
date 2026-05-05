<?php

namespace Cleantalk\Antispam\ScriptsIntegration;

abstract class ScriptIntegrationPlugin
{
    public $is_plugin_active;
    public $is_in_uri;
    public $additional_checks_passed;

    public $plugin_file;
    public $uri_chunk;
    public $hook_name;

    public function __construct()
    {
        if (
            !isset($this->plugin_file) ||
            !is_string($this->plugin_file) ||
            !isset($this->uri_chunk) ||
            !is_string($this->uri_chunk) ||
            !isset($this->hook_name) ||
            !is_string($this->hook_name)
        ) {
            throw new \Exception('Plugin file, URI chunk and hook name must be set');
        }

        $this->is_plugin_active = $this->isPluginActive($this->plugin_file);
        $this->is_in_uri = $this->isInUri($this->uri_chunk);
        $this->additional_checks_passed = $this->additionalChecks();
    }

    /**
     * Executes the plugin integration logic.
     *
     * This method must be implemented by each concrete integration class
     * and is responsible for registering scripts, hooks, or other behaviors.
     *
     * @return void
     */
    abstract public function integrate();

    /**
     * Checks whether a given WordPress plugin is active.
     *
     * @param string $plugin_file Path to the plugin main file.
     * @return bool True if the plugin is active, false otherwise.
     */
    public function isPluginActive($plugin_file)
    {
        return apbct_is_plugin_active($plugin_file);
    }

    /**
     * Checks whether the current request URI contains a specific substring.
     *
     * @param string $uri_chunk URI fragment to search for in the current request URI.
     * @return bool True if the URI fragment is found, false otherwise.
     */
    public function isInUri($uri_chunk)
    {
        return apbct_is_in_uri($uri_chunk);
    }

    /**
     * Performs additional runtime checks required for plugin activation.
     *
     * This method can be overridden in child classes to implement
     * custom validation logic.
     *
     * @return bool True if all additional checks pass, false otherwise.
     */
    public function additionalChecks()
    {
        return true;
    }
}
