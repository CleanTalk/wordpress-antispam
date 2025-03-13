<?php

namespace Cleantalk\Antispam;

class IntegrationsByClass
{
    /**
     * @var array<string, array{plugin_path: string, plugin_class: string}>
     * @psalm-suppress UnusedVariable, UnusedProperty
     */
    private $integrations;
    private $active_plugins;
    private $active_plugins_wpms;

    /**
     * Integrations constructor.
     *
     * @param array $integrations
     */
    public function __construct($integrations)
    {
        $this->integrations = $integrations;
        $this->active_plugins = get_option('active_plugins', array());
        $this->active_plugins_wpms = get_site_option('active_sitewide_plugins', array());
        $this->active_plugins = is_array($this->active_plugins) ? $this->active_plugins : array();
        $this->active_plugins_wpms = is_array($this->active_plugins_wpms) ? $this->active_plugins_wpms : array();

        foreach ($this->integrations as $integration_name => $integration_info) {
            // pre-check to skip integration by plugin path
            if ( isset($integration_info['plugin_path']) && !$this->isPluginActive($integration_info['plugin_path']) ) {
                continue;
            }

            // pre-check to skip integration by plugin class
            if ( isset($integration_info['plugin_class']) && !class_exists($integration_info['plugin_class']) ) {
                continue;
            }

            $class = '\\Cleantalk\\Antispam\\IntegrationsByClass\\' . $integration_name;
            if (!class_exists($class)) {
                continue;
            }

            $integration = new $class();

            // Ajax work
            if (apbct_is_ajax()) {
                $integration->doAjaxWork();
                continue;
            }

            // Admin work
            if (is_admin() || is_network_admin()) {
                $integration->doAdminWork();
                continue;
            }

            // Public work
            $skip_integration = $integration->isSkipIntegration();
            if ($skip_integration) {
                continue;
            }

            $integration->doPublicWork();
        }
    }

    private function isPluginActive($plugin_path)
    {
        return in_array($plugin_path, $this->active_plugins) || in_array($plugin_path, $this->active_plugins_wpms);
    }
}
