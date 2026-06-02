<?php

namespace Cleantalk\Antispam\ScriptsIntegration;

class CleantalkScriptsIntegrator
{
    /**
     * List of plugins selected for inline script integration,
     * indexed by their corresponding WordPress hook name.
     *
     * @var ScriptIntegrationPlugin[]
     */
    public $plugins_loaded = [];

    /**
     * Executes the integration process.
     *
     * This method:
     * - Retrieves available integrations
     * - Filters plugins that should be loaded
     * - Registers WordPress hooks to execute plugin integration logic
     *
     * @return void
     */
    public function run()
    {
        $integrations = $this->getIntegrations();

        $this->plugins_loaded = !empty($integrations)
            ? $this->getPluginsToInline($integrations)
            : [];

        if (!empty($this->plugins_loaded)) {
            foreach ($this->plugins_loaded as $_hook => $plugin) {
                if ($plugin instanceof ScriptIntegrationPlugin) {
                    add_action($_hook, function () use ($plugin) {
                        $plugin->integrate();
                    }, 100);
                }
            }
        }
    }

    /**
     * Filters plugins that are eligible for inline script integration.
     *
     * A plugin is included only if:
     * - It is active
     * - It matches the current URI context
     * - It passes additional runtime checks
     *
     * Each hook can only be assigned to one plugin (first match wins).
     *
     * @param ScriptIntegrationPlugin[] $integrations List of available plugin integrations.
     * @return ScriptIntegrationPlugin[] Filtered plugins indexed by hook name.
     */
    public function getPluginsToInline($integrations)
    {
        $plugins_loaded = [];

        foreach ($integrations as $plugin) {
            if (
                $plugin->is_plugin_active &&
                $plugin->is_in_uri &&
                $plugin->additional_checks_passed
            ) {
                if (!isset($plugins_loaded[$plugin->hook_name])) {
                    $plugins_loaded[$plugin->hook_name] = $plugin;
                }
            }
        }

        return $plugins_loaded;
    }

    /**
     * Returns a list of all available plugin integrations.
     *
     * Each integration defines:
     * - Activation rules
     * - Context conditions (URI, environment, etc.)
     * - Hook target for script injection
     *
     * @return ScriptIntegrationPlugin[]
     */
    public function getIntegrations()
    {
        try {
            $integrations = [
                new GiveWPScript(),
                new FluentFormScript(),
            ];
        } catch (\Exception $e) {
            $integrations = [];
        }

        return $integrations;
    }
}
