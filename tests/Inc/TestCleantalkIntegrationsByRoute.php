<?php

namespace Inc;

use PHPUnit\Framework\TestCase;

class TestCleantalkIntegrationsByRoute extends TestCase
{
    public function testApbctActiveRestIntegrationsHasValidStructure()
    {
        $apbct_active_rest_integrations = null;
        include CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-integrations-by-route.php';

        $this->assertIsArray($apbct_active_rest_integrations);
        $this->assertNotEmpty($apbct_active_rest_integrations);

        $this->assertArrayHasKey('HivePress', $apbct_active_rest_integrations);

        $this->assertArrayHasKey('rest_route', $apbct_active_rest_integrations['HivePress']);
        $this->assertArrayHasKey('setting', $apbct_active_rest_integrations['HivePress']);
        $this->assertArrayHasKey('rest', $apbct_active_rest_integrations['HivePress']);

        $this->assertEquals($apbct_active_rest_integrations['HivePress']['rest_route'], '/hivepress/v1/listings/');
        $this->assertEquals($apbct_active_rest_integrations['HivePress']['setting'], 'forms__contact_forms_test');
        $this->assertEquals($apbct_active_rest_integrations['HivePress']['rest'], true);
    }
}
