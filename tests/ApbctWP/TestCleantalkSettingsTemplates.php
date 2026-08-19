<?php

use Cleantalk\ApbctWP\CleantalkSettingsTemplates;
use Cleantalk\ApbctWP\State;

/**
 * Tests for \Cleantalk\ApbctWP\CleantalkSettingsTemplates
 *
 * The test does not perform any real API call, so it does not need CLEANTALK_TEST_API_KEY
 * and behaves the same way locally and on CI (even without network access).
 */
class TestCleantalkSettingsTemplates extends PHPUnit\Framework\TestCase
{
    /**
     * Fake key. It is never sent anywhere because the API response is mocked.
     */
    const TEST_API_KEY = 'test_api_key';

    private $apbct_copy;

    public function setUp(): void
    {
        global $apbct;
        $this->apbct_copy = $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));

        $this->mockApiTemplatesGetResponse(null);
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct = $this->apbct_copy;

        $this->mockApiTemplatesGetResponse(null);

        parent::tearDown();
    }

    public function testGetOptionsTemplateReturnsMockedApiResponse()
    {
        $mocked_response = $this->getApiTemplatesGetResponseFixture();
        $this->mockApiTemplatesGetResponse($mocked_response);

        $templates = CleantalkSettingsTemplates::getOptionsTemplate(self::TEST_API_KEY);

        $this->assertIsArray($templates);
        $this->assertCount(2, $templates);
        $this->assertSame($mocked_response, $templates);
    }

    /**
     * Templates are stored in the static cache, so no API request is performed
     * and the API key value does not matter at all.
     */
    public function testGetOptionsTemplateDoesNotCallApiWhenTemplatesAreLoaded()
    {
        $mocked_response = $this->getApiTemplatesGetResponseFixture();
        $this->mockApiTemplatesGetResponse($mocked_response);

        $this->assertSame($mocked_response, CleantalkSettingsTemplates::getOptionsTemplate('wrong_key'));
        $this->assertSame($mocked_response, CleantalkSettingsTemplates::getOptionsTemplate(''));
    }

    public function testGetHtmlContentContainsMockedTemplates()
    {
        $this->mockApiTemplatesGetResponse($this->getApiTemplatesGetResponseFixture());

        $settings_templates = new CleantalkSettingsTemplates(self::TEST_API_KEY);
        $html               = $settings_templates->getHtmlContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('apbct_settings_templates_import', $html);
        $this->assertStringContainsString('apbct_settings_templates_export', $html);
        $this->assertStringContainsString('apbct_settings_templates_reset_button', $html);
        $this->assertStringContainsString('template_with_options', $html);
        // The template with a filled options_site is available for import
        $this->assertStringContainsString("data-id='1234'", $html);
        // The template without options_site is skipped in the import list
        $this->assertStringNotContainsString("data-id='5678'", $html);
    }

    public function testGetHtmlContentImportOnly()
    {
        $this->mockApiTemplatesGetResponse($this->getApiTemplatesGetResponseFixture());

        $settings_templates = new CleantalkSettingsTemplates(self::TEST_API_KEY);
        $html               = $settings_templates->getHtmlContent(true);

        $this->assertStringContainsString('apbct_settings_templates_import_button', $html);
        $this->assertStringNotContainsString('apbct_settings_templates_export_button', $html);
        $this->assertStringNotContainsString('apbct_settings_templates_reset_button', $html);
    }

    /**
     * Emulates an API response without any usable template (the same output the plugin
     * shows when the API key is wrong and the response contains no templates).
     */
    public function testGetHtmlContentWithoutSuitableTemplates()
    {
        $mocked_response = $this->getApiTemplatesGetResponseFixture();
        // Keep only the template without options_site
        $mocked_response = array(1 => $mocked_response[1]);
        $this->mockApiTemplatesGetResponse($mocked_response);

        $settings_templates = new CleantalkSettingsTemplates(self::TEST_API_KEY);
        $html               = $settings_templates->getHtmlContent();

        $this->assertStringContainsString('There are no settings templates', $html);
        $this->assertStringNotContainsString('apbct_settings_templates_import_button', $html);
    }

    /**
     * Mocks the result of \Cleantalk\ApbctWP\API::methodServicesTemplatesGet()
     * by filling the private static templates cache of CleantalkSettingsTemplates.
     *
     * @param array|null $templates Templates to return, null resets the cache
     *
     * @return void
     * @throws ReflectionException
     */
    private function mockApiTemplatesGetResponse($templates)
    {
        $templates_property = new ReflectionProperty(CleantalkSettingsTemplates::class, 'templates');
        $templates_property->setAccessible(true);
        $templates_property->setValue(null, $templates);
    }

    /**
     * Fixture of a real services_templates_get API response.
     *
     * @return array
     */
    private function getApiTemplatesGetResponseFixture()
    {
        return array(
            0 => array(
                'template_id'          => 1234,
                'product_id'           => 1,
                'name'                 => 'template_with_options',
                'options_cloud'        => '{"response_lang":"en","stop_list_enable":0,"move_to_spam_enable":1}',
                'options_site'         => '{"sfw__enabled":"1","forms__comments_test":"1","data__use_ajax":"1"}',
                'created'              => '2022-10-05 11:15:43',
                'updated'              => '2022-10-05 11:15:43',
                'service_id_last_used' => null,
                'user_id'              => 351994,
                'set_as_default'       => 0,
            ),
            1 => array(
                'template_id'          => 5678,
                'product_id'           => 1,
                'name'                 => 'template_without_options',
                'options_cloud'        => '',
                'options_site'         => '',
                'created'              => '2022-10-05 11:15:43',
                'updated'              => '2022-10-05 11:15:43',
                'service_id_last_used' => null,
                'user_id'              => 351994,
                'set_as_default'       => 0,
            ),
        );
    }
}