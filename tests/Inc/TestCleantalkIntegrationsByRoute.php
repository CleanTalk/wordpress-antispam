<?php

namespace Inc;

use PHPUnit\Framework\TestCase;

class TestCleantalkIntegrationsByRoute extends TestCase
{
    public function testApbctActiveRestIntegrationsHasValidStructure()
    {
        $apbct_active_rest_integrations = apbctGetActiveRestIntegrations();

        $this->assertIsArray($apbct_active_rest_integrations);
        $this->assertNotEmpty($apbct_active_rest_integrations);

        $routes = [];

        foreach ($apbct_active_rest_integrations as $name => $data) {
            $this->assertArrayHasKey('rest_route', $data, "Missing rest_route in $name");
            $this->assertArrayHasKey('setting', $data, "Missing setting in $name");
            $this->assertArrayHasKey('rest', $data, "Missing rest in $name");

            $this->assertIsString($data['rest_route'], "rest_route in $name must be string");
            $this->assertIsString($data['setting'], "setting in $name must be string");
            $this->assertIsBool($data['rest'], "rest in $name must be boolean");

            $this->assertStringStartsWith('/', $data['rest_route'], "rest_route in $name must start with /");

            $this->assertNotContains($data['rest_route'], $routes, "Duplicate rest_route: {$data['rest_route']} in $name");
            $routes[] = $data['rest_route'];
        }
    }
}
