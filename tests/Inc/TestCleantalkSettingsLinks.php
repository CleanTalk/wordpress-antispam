<?php

namespace Inc;

use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests that EXECUTE code in cleantalk-settings.php
 * to provide codecov coverage for LinkConstructor calls.
 *
 * These tests call actual functions from cleantalk-settings.php,
 * unlike the unit tests in TestSettingsLongDescriptionLinks.php
 * which only test LinkConstructor directly.
 */
class TestCleantalkSettingsLinks extends TestCase
{
    /**
     * @var State
     */
    private $apbct;

    protected function setUp(): void
    {
        parent::setUp();
        require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-settings.php');

        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $apbct->data['bot_detector_enabled'] = 1;
        $apbct->data['wl_brandname'] = 'Anti-Spam by CleanTalk';
        $apbct->data['wl_mode_enabled'] = false;
        $apbct->data['moderate'] = 1;
        $apbct->data['moderate_ip'] = 0;
        $apbct->data['ip_license'] = 0;
        $this->apbct = $apbct;
    }

    protected function tearDown(): void
    {
        global $apbct;
        unset($apbct);

        // Reset Post singleton cache so $_POST changes are visible between tests
        $instance = Post::getInstance();
        $instance->variables = array();

        parent::tearDown();
    }

    // =========================================================================
    // apbct_settings__set_fields() — covers lines 115, 290, 530, 668
    // =========================================================================

    /**
     * Line 290 (blog_search_form_protection) - inside `! $apbct->white_label || is_main_site()`
     * Line 530 (settings_dashboard_link) - inside `! $apbct->white_label && ! $apbct->data["wl_mode_enabled"]`
     * Line 668 (settings_email_existence_alert) - unconditional
     */
    public function testSetFieldsCoversSearchAndDashboardAndEmailLinks()
    {
        $fields = apbct_settings__set_fields();

        // Line 290: forms__search_test description should contain the blog link
        $this->assertArrayHasKey('forms_protection', $fields);
        $search_field = $fields['forms_protection']['fields']['forms__search_test'];
        $this->assertStringContainsString('blog.cleantalk.org', $search_field['description']);
        $this->assertStringContainsString('utm_content=search_form_protection', $search_field['description']);

        // Line 530: data__general_postdata_test description should contain dashboard link
        $this->assertArrayHasKey('data_processing', $fields);
        $postdata_field = $fields['data_processing']['fields']['data__general_postdata_test'];
        $this->assertStringContainsString('cleantalk.org/my', $postdata_field['description']);
        $this->assertStringContainsString('utm_content=settings_dashboard_link', $postdata_field['description']);

        // Line 668: data__email_check_exist_post description should contain email alert link
        $email_field = $fields['data_processing']['fields']['data__email_check_exist_post'];
        $this->assertStringContainsString('help/show-email-existence-alert', $email_field['description']);
        $this->assertStringContainsString('utm_content=email_existence_alert', $email_field['description']);
    }

    /**
     * Line 115 (settings_support_open) - inside condition:
     * $apbct->api_key && is_null($apbct->fw_stats['firewall_updating_id'])
     *   && $apbct->settings['sfw__enabled'] && ! $apbct->stats['sfw']['entries']
     */
    public function testSetFieldsCoversSupportOpenLink()
    {
        global $apbct;

        // Set up conditions to hit line 115
        $apbct->storage['api_key'] = 'test_key_12345678';
        $apbct->storage['fw_stats'] = array('firewall_updating_id' => null);
        $apbct->storage['settings'] = array_merge(
            $apbct->default_settings,
            array('sfw__enabled' => 1)
        );
        $apbct->storage['stats'] = array('sfw' => array('entries' => 0));

        $fields = apbct_settings__set_fields();

        // The anti_crawler title should contain the support link
        $anti_crawler = $fields['advanced_settings']['fields']['sfw__anti_crawler'];
        $this->assertStringContainsString('my/support/open', $anti_crawler['title']);
        $this->assertStringContainsString('utm_content=sfw_support_link', $anti_crawler['title']);
    }

    // =========================================================================
    // apbct_settings__field__state() — covers line 1873
    // =========================================================================

    /**
     * Line 1873 (blog_email_validation_status) - inside `! $apbct->white_label || is_main_site()`
     */
    public function testFieldStateCoversEmailValidationLink()
    {
        ob_start();
        apbct_settings__field__state();
        $output = ob_get_clean();

        $this->assertStringContainsString('blog.cleantalk.org', $output);
        $this->assertStringContainsString('real-time-email-address-existence-validation', $output);
        $this->assertStringContainsString('utm_content=email_validation_status', $output);
    }

    // =========================================================================
    // apbct_settings__field__apikey() — signup wizard link on settings page
    // =========================================================================

    /**
     * When no valid key exists, apikey field shows GET ACCESS KEY linking to signup wizard.
     * Public offer / license agreement moved to the React signup wizard.
     */
    public function testFieldApikeyCoversSignupWizardLink()
    {
        global $apbct;
        $apbct->data['moderate_ip'] = 0;
        $apbct->data['ip_license'] = 0;
        $apbct->data['key_is_ok'] = false;
        $apbct->settings['apikey'] = '';

        ob_start();
        apbct_settings__field__apikey();
        $output = ob_get_clean();

        $this->assertStringContainsString('signup_wizard=1', $output);
        $this->assertStringContainsString('apbct_button__get_key_auto', $output);
        $this->assertStringContainsString('GET ACCESS KEY', $output);
        $this->assertStringNotContainsString('publicoffer', $output);
        $this->assertStringNotContainsString('apbct_button__get_key_manual_chunk', $output);
    }

    // =========================================================================
    // apbct_settings__get_long_descriptions_data() — covers lines 3045-3104
    // =========================================================================

    /**
     * Covers all LinkConstructor calls in long descriptions array.
     * Lines: 3045, 3052, 3057, 3070, 3079, 3086, 3093, 3104
     *
     * Calls the extracted helper function directly (no die/wp_die involved).
     */
    public function testLongDescriptionCoversAllLinks()
    {
        $descriptions = apbct_settings__get_long_descriptions_data();

        $this->assertIsArray($descriptions);
        $this->assertArrayHasKey('data__set_cookies', $descriptions);
        $this->assertStringContainsString('help/set-cookies-option', $descriptions['data__set_cookies']['desc']);
        $this->assertStringContainsString('apbct_hint_data__set_cookies', $descriptions['data__set_cookies']['desc']);
    }

    /**
     * Test anti_crawler long description link with anchor.
     */
    public function testLongDescriptionAntiCrawlerLink()
    {
        $descriptions = apbct_settings__get_long_descriptions_data();

        $this->assertArrayHasKey('sfw__anti_crawler', $descriptions);
        $this->assertStringContainsString('help/anti-flood-and-anti-crawler', $descriptions['sfw__anti_crawler']['desc']);
        $this->assertStringContainsString('#anticrawl', $descriptions['sfw__anti_crawler']['desc']);
    }

    /**
     * Test sfw__enabled long description (line 3104).
     */
    public function testLongDescriptionSfwEnabledLink()
    {
        $descriptions = apbct_settings__get_long_descriptions_data();

        $this->assertArrayHasKey('sfw__enabled', $descriptions);
        $this->assertStringContainsString('help/anti-flood-and-anti-crawler', $descriptions['sfw__enabled']['desc']);
        $this->assertStringContainsString('apbct_hint_sfw__enabled', $descriptions['sfw__enabled']['desc']);
    }

    /**
     * Test data__pixel long description (line 3086) with blog.cleantalk.org domain.
     */
    public function testLongDescriptionPixelLink()
    {
        $descriptions = apbct_settings__get_long_descriptions_data();

        $this->assertArrayHasKey('data__pixel', $descriptions);
        $this->assertStringContainsString('blog.cleantalk.org', $descriptions['data__pixel']['desc']);
        $this->assertStringContainsString('introducing-cleantalk-pixel', $descriptions['data__pixel']['desc']);
    }

    /**
     * Test data__honeypot_field long description (line 3093).
     */
    public function testLongDescriptionHoneypotLink()
    {
        $descriptions = apbct_settings__get_long_descriptions_data();

        $this->assertArrayHasKey('data__honeypot_field', $descriptions);
        $this->assertStringContainsString('help/honeypot-field', $descriptions['data__honeypot_field']['desc']);
        $this->assertStringContainsString('apbct_hint_data__honeypot_field', $descriptions['data__honeypot_field']['desc']);
    }
}
