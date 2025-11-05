<?php

use PHPUnit\Framework\TestCase;
use Cleantalk\ApbctWP\ServiceConstants;
use Cleantalk\ApbctWP\ApbctConstant;

class ServiceConstantsTest extends TestCase
{
    private $serviceConstants;

    protected function setUp(): void
    {
        $this->serviceConstants = new ServiceConstants();
    }

    public function testGetDefinitionsReturnsAllConstants()
    {
        $definitions = $this->serviceConstants->getDefinitions();

        $this->assertIsArray($definitions);
        $this->assertNotEmpty($definitions);
        foreach ($definitions as $definition) {
            $this->assertArrayHasKey('is_defined', $definition);
            $this->assertArrayHasKey('value', $definition);
            $this->assertArrayHasKey('description', $definition);
        }
    }

    public function testGetDefinitionsActiveReturnsOnlyActiveConstants()
    {
        // Mocking an active constant
        $mockConstant = $this->createMock(ApbctConstant::class);
        $mockConstant->method('getData')->willReturn([
                                                         'name' => 'APBCT_SERVICE__ACTIVE_CONSTANT',
                                                         'description' => 'Active constant description',
                                                         'is_defined' => true,
                                                     ]);

        $this->serviceConstants->disable_empty_email_exception = $mockConstant;

        $activeDefinitions = $this->serviceConstants->getDefinitionsActive();

        $this->assertIsArray($activeDefinitions);
        $this->assertCount(1, $activeDefinitions);
        $this->assertEquals('APBCT_SERVICE__ACTIVE_CONSTANT', $activeDefinitions[0]['name']);
    }

    public function testGetDefinitionsActiveReturnsOnlyActiveConstantsLive()
    {
        define('APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION', true);
        $this->serviceConstants = new ServiceConstants();
        $activeDefinitions = $this->serviceConstants->getDefinitionsActive();
        $this->assertIsArray($activeDefinitions);
        $this->assertCount(1, $activeDefinitions);
        $this->assertEquals('APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION', $activeDefinitions[0]['is_defined']);
        $this->assertEquals(true, $this->serviceConstants->disable_empty_email_exception->isDefined());
        $this->assertEquals(true, $activeDefinitions[0]['value']);
        $this->assertEquals(true, $this->serviceConstants->disable_empty_email_exception->getValue());
        $this->assertNotEmpty($activeDefinitions[0]['description']);

        define('APBCT_ANTICRAWLER_EXLC_FEED', true);
        $this->serviceConstants = new ServiceConstants();
        $activeDefinitions = $this->serviceConstants->getDefinitionsActive();
        $this->assertIsArray($activeDefinitions);
        $this->assertCount(2, $activeDefinitions);
        $this->assertEquals('APBCT_ANTICRAWLER_EXLC_FEED', $activeDefinitions[1]['is_defined']);
        $this->assertEquals('APBCT_ANTICRAWLER_EXLC_FEED', $this->serviceConstants->skip_anticrawler_on_rss_feed->isDefined());
        $this->assertEquals(true, $activeDefinitions[1]['value']);
        $this->assertEquals(true, $this->serviceConstants->skip_anticrawler_on_rss_feed->getValue());
        $this->assertNotEmpty($activeDefinitions[1]['description']);

        define('APBCT_SET_AJAX_ROUTE_TYPE', 'admin_ajax');
        $this->serviceConstants = new ServiceConstants();
        $activeDefinitions = $this->serviceConstants->getDefinitionsActive();
        $this->assertIsArray($activeDefinitions);
        $this->assertCount(3, $activeDefinitions);
        $this->assertEquals('APBCT_SET_AJAX_ROUTE_TYPE', $activeDefinitions[2]['is_defined']);
        $this->assertEquals(true, $this->serviceConstants->set_ajax_route_type->isDefinedAndTypeOK());
        $this->assertEquals('admin_ajax', $activeDefinitions[2]['value']);
        $this->assertEquals('admin_ajax', $this->serviceConstants->set_ajax_route_type->getValue());
        $this->assertNotEmpty($activeDefinitions[2]['description']);

        define('CLEANTALK_ACCESS_KEY', 'asdasdasd');
        $this->serviceConstants = new ServiceConstants();
        $activeDefinitions = $this->serviceConstants->getDefinitionsActive();
        $this->assertIsArray($activeDefinitions);
        $this->assertCount(4, $activeDefinitions);
        $this->assertEquals('CLEANTALK_ACCESS_KEY', $activeDefinitions[3]['is_defined']);
        $this->assertEquals(true, $this->serviceConstants->self_owned_access_key->isDefinedAndTypeOK());
        $this->assertEquals('asdasdasd', $activeDefinitions[3]['value']);
        $this->assertEquals('asdasdasd', $this->serviceConstants->self_owned_access_key->getValue());
        $this->assertNotEmpty($activeDefinitions[3]['description']);

        define('CLEANTALK_CHECK_COMMENTS_NUMBER', false);
        $this->serviceConstants = new ServiceConstants();
        $activeDefinitions = $this->serviceConstants->getDefinitionsActive();
        $this->assertIsArray($activeDefinitions);
        $this->assertCount(5, $activeDefinitions);
        $this->assertEquals('CLEANTALK_CHECK_COMMENTS_NUMBER', $activeDefinitions[4]['is_defined']);
        $this->assertEquals(false, $this->serviceConstants->skip_on_approved_comments_number->isDefinedAndTypeOK());
        $this->assertEquals(false, $activeDefinitions[4]['value']);
        $this->assertEquals(false, $this->serviceConstants->skip_on_approved_comments_number->getValue());
        $this->assertNotEmpty($activeDefinitions[4]['description']);
    }
}
