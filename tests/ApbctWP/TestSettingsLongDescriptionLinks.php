<?php

use Cleantalk\ApbctWP\LinkConstructor;

/**
 * Unit tests for links in apbct_settings__get__long_description() and other settings links.
 *
 * These tests ensure that:
 * 1. Migrated links produce the correct URLs via LinkConstructor
 * 2. UTM presets exist and contain expected values
 * 3. Future migrations will not break link structure
 */
class TestSettingsLongDescriptionLinks extends \PHPUnit\Framework\TestCase
{
    // =========================================================================
    // Tests for links already migrated to LinkConstructor
    // (inside apbct_settings__get__long_description)
    // =========================================================================

    /**
     * data__set_cookies: help/set-cookies-option
     * Original: https://cleantalk.org/help/set-cookies-option?utm_source=apbct_hint_data__set_cookies&utm_medium=WordPress&utm_campaign=ABPCT_Settings
     */
    public function testLinkDataSetCookies()
    {
        $link = LinkConstructor::buildCleanTalkLink('apbct_hint_data__set_cookies', 'help/set-cookies-option');

        $this->assertStringStartsWith('https://cleantalk.org/help/set-cookies-option?', $link);
        $this->assertStringContainsString('utm_source=apbct_hint_data__set_cookies', $link);
        $this->assertStringContainsString('utm_medium=WordPress', $link);
        $this->assertStringContainsString('utm_campaign=ABPCT_Settings', $link);
    }

    public function testPresetDataSetCookiesExists()
    {
        $this->assertArrayHasKey('apbct_hint_data__set_cookies', LinkConstructor::$utm_presets);
        $preset = LinkConstructor::$utm_presets['apbct_hint_data__set_cookies'];
        $this->assertSame('apbct_hint_data__set_cookies', $preset['utm_source']);
        $this->assertSame('WordPress', $preset['utm_medium']);
        $this->assertSame('ABPCT_Settings', $preset['utm_campaign']);
    }

    /**
     * comments__hide_website_field: help/how-to-hide-website-field-in-wordpress-comments
     * Original: https://cleantalk.org/help/how-to-hide-website-field-in-wordpress-comments?utm_source=apbct_hint_comments__hide_website_field&utm_medium=hide_website_field_hint&utm_campaign=apbct_links
     */
    public function testLinkCommentsHideWebsiteField()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'apbct_hint_comments__hide_website_field',
            'help/how-to-hide-website-field-in-wordpress-comments'
        );

        $this->assertStringStartsWith('https://cleantalk.org/help/how-to-hide-website-field-in-wordpress-comments?', $link);
        $this->assertStringContainsString('utm_source=apbct_hint_comments__hide_website_field', $link);
        $this->assertStringContainsString('utm_medium=hide_website_field_hint', $link);
        $this->assertStringContainsString('utm_campaign=apbct_links', $link);
    }

    public function testPresetCommentsHideWebsiteFieldExists()
    {
        $this->assertArrayHasKey('apbct_hint_comments__hide_website_field', LinkConstructor::$utm_presets);
        $preset = LinkConstructor::$utm_presets['apbct_hint_comments__hide_website_field'];
        $this->assertSame('apbct_hint_comments__hide_website_field', $preset['utm_source']);
        $this->assertSame('hide_website_field_hint', $preset['utm_medium']);
        // utm_campaign not in preset — should fall back to static::$utm_campaign = 'apbct_links'
        $this->assertArrayNotHasKey('utm_campaign', $preset);
    }

    /**
     * comments__the_real_person: the-real-person (was already using LinkConstructor)
     * Original: https://cleantalk.org/the-real-person?...&utm_source=admin_side&utm_medium=trp_badge&utm_content=trp_badge_link_click&utm_campaign=apbct_links
     */
    public function testLinkCommentsTheRealPerson()
    {
        $link = LinkConstructor::buildCleanTalkLink('trp_learn_more_link', 'the-real-person');

        $this->assertStringStartsWith('https://cleantalk.org/the-real-person?', $link);
        $this->assertStringContainsString('utm_source=admin_side', $link);
        $this->assertStringContainsString('utm_medium=trp_badge', $link);
        $this->assertStringContainsString('utm_content=trp_badge_link_click', $link);
        $this->assertStringContainsString('utm_campaign=apbct_links', $link);
    }

    /**
     * sfw__anti_crawler: help/anti-flood-and-anti-crawler + #anticrawl anchor
     * Original: https://cleantalk.org/help/anti-flood-and-anti-crawler?utm_source=apbct_hint_sfw__anti_crawler&utm_medium=WordPress&utm_campaign=ABPCT_Settings
     * Note: #anticrawl is appended outside of buildCleanTalkLink
     */
    public function testLinkSfwAntiCrawler()
    {
        $link = LinkConstructor::buildCleanTalkLink('apbct_hint_sfw__anti_crawler', 'help/anti-flood-and-anti-crawler');

        $this->assertStringStartsWith('https://cleantalk.org/help/anti-flood-and-anti-crawler?', $link);
        $this->assertStringContainsString('utm_source=apbct_hint_sfw__anti_crawler', $link);
        $this->assertStringContainsString('utm_medium=WordPress', $link);
        $this->assertStringContainsString('utm_campaign=ABPCT_Settings', $link);

        // Verify anchor can be appended
        $linkWithAnchor = $link . '#anticrawl';
        $this->assertStringEndsWith('#anticrawl', $linkWithAnchor);
    }

    public function testPresetSfwAntiCrawlerExists()
    {
        $this->assertArrayHasKey('apbct_hint_sfw__anti_crawler', LinkConstructor::$utm_presets);
        $preset = LinkConstructor::$utm_presets['apbct_hint_sfw__anti_crawler'];
        $this->assertSame('apbct_hint_sfw__anti_crawler', $preset['utm_source']);
        $this->assertSame('WordPress', $preset['utm_medium']);
        $this->assertSame('ABPCT_Settings', $preset['utm_campaign']);
    }

    /**
     * sfw__anti_flood: help/anti-flood-and-anti-crawler + #antiflood anchor
     * Original: https://cleantalk.org/help/anti-flood-and-anti-crawler?utm_source=apbct_hint_sfw__anti_flood&utm_medium=WordPress&utm_campaign=ABPCT_Settings
     */
    public function testLinkSfwAntiFlood()
    {
        $link = LinkConstructor::buildCleanTalkLink('apbct_hint_sfw__anti_flood', 'help/anti-flood-and-anti-crawler');

        $this->assertStringStartsWith('https://cleantalk.org/help/anti-flood-and-anti-crawler?', $link);
        $this->assertStringContainsString('utm_source=apbct_hint_sfw__anti_flood', $link);
        $this->assertStringContainsString('utm_medium=WordPress', $link);
        $this->assertStringContainsString('utm_campaign=ABPCT_Settings', $link);

        // Verify anchor can be appended
        $linkWithAnchor = $link . '#antiflood';
        $this->assertStringEndsWith('#antiflood', $linkWithAnchor);
    }

    public function testPresetSfwAntiFloodExists()
    {
        $this->assertArrayHasKey('apbct_hint_sfw__anti_flood', LinkConstructor::$utm_presets);
        $preset = LinkConstructor::$utm_presets['apbct_hint_sfw__anti_flood'];
        $this->assertSame('apbct_hint_sfw__anti_flood', $preset['utm_source']);
        $this->assertSame('WordPress', $preset['utm_medium']);
        $this->assertSame('ABPCT_Settings', $preset['utm_campaign']);
    }

    /**
     * data__pixel: introducing-cleantalk-pixel (blog.cleantalk.org domain)
     * Original: https://blog.cleantalk.org/introducing-cleantalk-pixel?utm_source=apbct_hint_data__pixel&utm_medium=WordPress&utm_campaign=ABPCT_Settings
     */
    public function testLinkDataPixel()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'apbct_hint_data__pixel',
            'introducing-cleantalk-pixel',
            array(),
            'https://blog.cleantalk.org'
        );

        $this->assertStringStartsWith('https://blog.cleantalk.org/introducing-cleantalk-pixel?', $link);
        $this->assertStringContainsString('utm_source=apbct_hint_data__pixel', $link);
        $this->assertStringContainsString('utm_medium=WordPress', $link);
        $this->assertStringContainsString('utm_campaign=ABPCT_Settings', $link);
    }

    public function testPresetDataPixelExists()
    {
        $this->assertArrayHasKey('apbct_hint_data__pixel', LinkConstructor::$utm_presets);
        $preset = LinkConstructor::$utm_presets['apbct_hint_data__pixel'];
        $this->assertSame('apbct_hint_data__pixel', $preset['utm_source']);
        $this->assertSame('WordPress', $preset['utm_medium']);
        $this->assertSame('ABPCT_Settings', $preset['utm_campaign']);
    }

    /**
     * data__honeypot_field: help/honeypot-field
     * Original: https://cleantalk.org/help/honeypot-field?utm_source=apbct_hint_data__honeypot_field&utm_medium=WordPress&utm_campaign=ABPCT_Settings
     */
    public function testLinkDataHoneypotField()
    {
        $link = LinkConstructor::buildCleanTalkLink('apbct_hint_data__honeypot_field', 'help/honeypot-field');

        $this->assertStringStartsWith('https://cleantalk.org/help/honeypot-field?', $link);
        $this->assertStringContainsString('utm_source=apbct_hint_data__honeypot_field', $link);
        $this->assertStringContainsString('utm_medium=WordPress', $link);
        $this->assertStringContainsString('utm_campaign=ABPCT_Settings', $link);
    }

    public function testPresetDataHoneypotFieldExists()
    {
        $this->assertArrayHasKey('apbct_hint_data__honeypot_field', LinkConstructor::$utm_presets);
        $preset = LinkConstructor::$utm_presets['apbct_hint_data__honeypot_field'];
        $this->assertSame('apbct_hint_data__honeypot_field', $preset['utm_source']);
        $this->assertSame('WordPress', $preset['utm_medium']);
        $this->assertSame('ABPCT_Settings', $preset['utm_campaign']);
    }

    /**
     * sfw__enabled: help/anti-flood-and-anti-crawler
     * Original: https://cleantalk.org/help/anti-flood-and-anti-crawler?utm_source=apbct_hint_sfw__enabled&utm_medium=WordPress&utm_campaign=ABPCT_Settings
     */
    public function testLinkSfwEnabled()
    {
        $link = LinkConstructor::buildCleanTalkLink('apbct_hint_sfw__enabled', 'help/anti-flood-and-anti-crawler');

        $this->assertStringStartsWith('https://cleantalk.org/help/anti-flood-and-anti-crawler?', $link);
        $this->assertStringContainsString('utm_source=apbct_hint_sfw__enabled', $link);
        $this->assertStringContainsString('utm_medium=WordPress', $link);
        $this->assertStringContainsString('utm_campaign=ABPCT_Settings', $link);
    }

    public function testPresetSfwEnabledExists()
    {
        $this->assertArrayHasKey('apbct_hint_sfw__enabled', LinkConstructor::$utm_presets);
        $preset = LinkConstructor::$utm_presets['apbct_hint_sfw__enabled'];
        $this->assertSame('apbct_hint_sfw__enabled', $preset['utm_source']);
        $this->assertSame('WordPress', $preset['utm_medium']);
        $this->assertSame('ABPCT_Settings', $preset['utm_campaign']);
    }

    /**
     * exclusions__form_signs: help/exclusion-by-form-signs (was already using LinkConstructor)
     * Original: https://cleantalk.org/help/exclusion-by-form-signs?...&utm_source=admin_panel&utm_medium=settings&utm_content=apbct_hint_exclusions__form_signs&utm_campaign=apbct_links
     */
    public function testLinkExclusionsFormSigns()
    {
        $link = LinkConstructor::buildCleanTalkLink('exclusion_by_form_signs', 'help/exclusion-by-form-signs');

        $this->assertStringStartsWith('https://cleantalk.org/help/exclusion-by-form-signs?', $link);
        $this->assertStringContainsString('utm_source=admin_panel', $link);
        $this->assertStringContainsString('utm_medium=settings', $link);
        $this->assertStringContainsString('utm_content=apbct_hint_exclusions__form_signs', $link);
        $this->assertStringContainsString('utm_campaign=apbct_links', $link);
    }



    // =========================================================================
    // Structural tests: verify LinkConstructor correctly builds URLs
    // =========================================================================

    /**
     * Ensure all hint presets produce URLs with correct domain
     */
    public function testAllHintPresetsUseCleantalkDomain()
    {
        $hintPresets = array(
            'apbct_hint_data__set_cookies',
            'apbct_hint_comments__hide_website_field',
            'apbct_hint_sfw__anti_crawler',
            'apbct_hint_sfw__anti_flood',
            'apbct_hint_data__honeypot_field',
            'apbct_hint_sfw__enabled',
        );

        foreach ($hintPresets as $preset) {
            $link = LinkConstructor::buildCleanTalkLink($preset, 'test-uri');
            $this->assertStringStartsWith(
                'https://cleantalk.org/test-uri?',
                $link,
                "Preset '$preset' should produce URL with cleantalk.org domain"
            );
        }
    }

    /**
     * Ensure data__pixel preset works with blog domain
     */
    public function testPixelPresetWithBlogDomain()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'apbct_hint_data__pixel',
            'test-uri',
            array(),
            'https://blog.cleantalk.org'
        );
        $this->assertStringStartsWith('https://blog.cleantalk.org/test-uri?', $link);
        $this->assertStringNotContainsString('https://cleantalk.org', $link);
    }

    /**
     * Verify that all long description presets exist in LinkConstructor
     */
    public function testAllLongDescriptionPresetsExist()
    {
        $requiredPresets = array(
            'apbct_hint_data__set_cookies',
            'apbct_hint_comments__hide_website_field',
            'apbct_hint_sfw__anti_crawler',
            'apbct_hint_sfw__anti_flood',
            'apbct_hint_data__pixel',
            'apbct_hint_data__honeypot_field',
            'apbct_hint_sfw__enabled',
            'trp_learn_more_link',
            'exclusion_by_form_signs',
        );

        foreach ($requiredPresets as $preset) {
            $this->assertArrayHasKey(
                $preset,
                LinkConstructor::$utm_presets,
                "UTM preset '$preset' must exist in LinkConstructor::\$utm_presets"
            );
        }
    }

    /**
     * Verify that UTM campaign defaults to 'apbct_links' when not specified in preset
     */
    public function testDefaultUtmCampaignFallback()
    {
        // comments__hide_website_field preset has no utm_campaign
        $link = LinkConstructor::buildCleanTalkLink(
            'apbct_hint_comments__hide_website_field',
            'test'
        );
        $this->assertStringContainsString('utm_campaign=apbct_links', $link);
    }

    /**
     * Verify that UTM campaign from preset overrides default
     */
    public function testCustomUtmCampaignInPreset()
    {
        // data__set_cookies preset has utm_campaign=ABPCT_Settings
        $link = LinkConstructor::buildCleanTalkLink(
            'apbct_hint_data__set_cookies',
            'test'
        );
        $this->assertStringContainsString('utm_campaign=ABPCT_Settings', $link);
        $this->assertStringNotContainsString('utm_campaign=apbct_links', $link);
    }

    /**
     * Verify invalid preset throws exception
     */
    public function testInvalidPresetThrowsException()
    {
        $this->expectException(\Exception::class);
        LinkConstructor::buildCleanTalkLink('nonexistent_preset', 'test');
    }

    /**
     * Full URL exact match tests for regression detection.
     * These are the canonical URLs that must not change.
     */
    public function testExactUrlDataSetCookies()
    {
        $link = LinkConstructor::buildCleanTalkLink('apbct_hint_data__set_cookies', 'help/set-cookies-option');
        $this->assertSame(
            'https://cleantalk.org/help/set-cookies-option?utm_id=&utm_term=&utm_source=apbct_hint_data__set_cookies&utm_medium=WordPress&utm_campaign=ABPCT_Settings',
            $link
        );
    }

    public function testExactUrlCommentsHideWebsiteField()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'apbct_hint_comments__hide_website_field',
            'help/how-to-hide-website-field-in-wordpress-comments'
        );
        $this->assertSame(
            'https://cleantalk.org/help/how-to-hide-website-field-in-wordpress-comments?utm_id=&utm_term=&utm_source=apbct_hint_comments__hide_website_field&utm_medium=hide_website_field_hint&utm_campaign=apbct_links',
            $link
        );
    }

    public function testExactUrlSfwAntiCrawler()
    {
        $link = LinkConstructor::buildCleanTalkLink('apbct_hint_sfw__anti_crawler', 'help/anti-flood-and-anti-crawler');
        $this->assertSame(
            'https://cleantalk.org/help/anti-flood-and-anti-crawler?utm_id=&utm_term=&utm_source=apbct_hint_sfw__anti_crawler&utm_medium=WordPress&utm_campaign=ABPCT_Settings',
            $link
        );
    }

    public function testExactUrlSfwAntiFlood()
    {
        $link = LinkConstructor::buildCleanTalkLink('apbct_hint_sfw__anti_flood', 'help/anti-flood-and-anti-crawler');
        $this->assertSame(
            'https://cleantalk.org/help/anti-flood-and-anti-crawler?utm_id=&utm_term=&utm_source=apbct_hint_sfw__anti_flood&utm_medium=WordPress&utm_campaign=ABPCT_Settings',
            $link
        );
    }

    public function testExactUrlDataPixel()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'apbct_hint_data__pixel',
            'introducing-cleantalk-pixel',
            array(),
            'https://blog.cleantalk.org'
        );
        $this->assertSame(
            'https://blog.cleantalk.org/introducing-cleantalk-pixel?utm_id=&utm_term=&utm_source=apbct_hint_data__pixel&utm_medium=WordPress&utm_campaign=ABPCT_Settings',
            $link
        );
    }

    public function testExactUrlDataHoneypotField()
    {
        $link = LinkConstructor::buildCleanTalkLink('apbct_hint_data__honeypot_field', 'help/honeypot-field');
        $this->assertSame(
            'https://cleantalk.org/help/honeypot-field?utm_id=&utm_term=&utm_source=apbct_hint_data__honeypot_field&utm_medium=WordPress&utm_campaign=ABPCT_Settings',
            $link
        );
    }

    public function testExactUrlSfwEnabled()
    {
        $link = LinkConstructor::buildCleanTalkLink('apbct_hint_sfw__enabled', 'help/anti-flood-and-anti-crawler');
        $this->assertSame(
            'https://cleantalk.org/help/anti-flood-and-anti-crawler?utm_id=&utm_term=&utm_source=apbct_hint_sfw__enabled&utm_medium=WordPress&utm_campaign=ABPCT_Settings',
            $link
        );
    }

    public function testExactUrlExclusionByFormSigns()
    {
        $link = LinkConstructor::buildCleanTalkLink('exclusion_by_form_signs', 'help/exclusion-by-form-signs');
        $this->assertSame(
            'https://cleantalk.org/help/exclusion-by-form-signs?utm_id=&utm_term=&utm_source=admin_panel&utm_medium=settings&utm_content=apbct_hint_exclusions__form_signs&utm_campaign=apbct_links',
            $link
        );
    }

    public function testExactUrlTrpLearnMoreLink()
    {
        $link = LinkConstructor::buildCleanTalkLink('trp_learn_more_link', 'the-real-person');
        $this->assertSame(
            'https://cleantalk.org/the-real-person?utm_id=&utm_term=&utm_source=admin_side&utm_medium=trp_badge&utm_content=trp_badge_link_click&utm_campaign=apbct_links',
            $link
        );
    }

    // =========================================================================
    // Tests for settings page links (second batch migration)
    // =========================================================================

    /**
     * Line 116: Support link (SFW empty warning)
     */
    public function testLinkSettingsSupportOpen()
    {
        $link = LinkConstructor::buildCleanTalkLink('settings_support_open', 'my/support/open');

        $this->assertStringStartsWith('https://cleantalk.org/my/support/open?', $link);
        $this->assertStringContainsString('utm_source=admin_panel', $link);
        $this->assertStringContainsString('utm_medium=settings', $link);
        $this->assertStringContainsString('utm_content=sfw_support_link', $link);
        $this->assertStringContainsString('utm_campaign=apbct_links', $link);
    }

    public function testExactUrlSettingsSupportOpen()
    {
        $link = LinkConstructor::buildCleanTalkLink('settings_support_open', 'my/support/open');
        $this->assertSame(
            'https://cleantalk.org/my/support/open?utm_id=&utm_term=&utm_source=admin_panel&utm_medium=settings&utm_content=sfw_support_link&utm_campaign=apbct_links',
            $link
        );
    }

    /**
     * Line 291: Blog search form protection
     */
    public function testLinkBlogSearchFormProtection()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'blog_search_form_protection',
            'how-to-protect-website-search-from-spambots',
            array(),
            'https://blog.cleantalk.org'
        );

        $this->assertStringStartsWith('https://blog.cleantalk.org/how-to-protect-website-search-from-spambots?', $link);
        $this->assertStringContainsString('utm_source=admin_panel', $link);
        $this->assertStringContainsString('utm_medium=settings', $link);
        $this->assertStringContainsString('utm_content=search_form_protection', $link);
    }

    public function testExactUrlBlogSearchFormProtection()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'blog_search_form_protection',
            'how-to-protect-website-search-from-spambots',
            array(),
            'https://blog.cleantalk.org'
        );
        $this->assertSame(
            'https://blog.cleantalk.org/how-to-protect-website-search-from-spambots?utm_id=&utm_term=&utm_source=admin_panel&utm_medium=settings&utm_content=search_form_protection&utm_campaign=apbct_links',
            $link
        );
    }

    /**
     * Line 530: Dashboard link with user_token and cp_mode
     */
    public function testLinkSettingsDashboard()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'settings_dashboard_link',
            'my',
            array('user_token' => 'test_token_123', 'cp_mode' => 'antispam')
        );

        $this->assertStringStartsWith('https://cleantalk.org/my?', $link);
        $this->assertStringContainsString('user_token=test_token_123', $link);
        $this->assertStringContainsString('cp_mode=antispam', $link);
        $this->assertStringContainsString('utm_source=wp-backend', $link);
        $this->assertStringContainsString('utm_medium=admin-bar', $link);
        $this->assertStringContainsString('utm_content=settings_dashboard_link', $link);
    }

    /**
     * Line 668: Email existence alert help link
     */
    public function testLinkSettingsEmailExistenceAlert()
    {
        $link = LinkConstructor::buildCleanTalkLink('settings_email_existence_alert', 'help/show-email-existence-alert');

        $this->assertStringStartsWith('https://cleantalk.org/help/show-email-existence-alert?', $link);
        $this->assertStringContainsString('utm_source=admin_panel', $link);
        $this->assertStringContainsString('utm_medium=settings', $link);
        $this->assertStringContainsString('utm_content=email_existence_alert', $link);
    }

    public function testExactUrlSettingsEmailExistenceAlert()
    {
        $link = LinkConstructor::buildCleanTalkLink('settings_email_existence_alert', 'help/show-email-existence-alert');
        $this->assertSame(
            'https://cleantalk.org/help/show-email-existence-alert?utm_id=&utm_term=&utm_source=admin_panel&utm_medium=settings&utm_content=email_existence_alert&utm_campaign=apbct_links',
            $link
        );
    }

    /**
     * Line 1129: Hoster API key profile link with #api_keys anchor
     */
    public function testLinkSettingsHosterApiKey()
    {
        $link = LinkConstructor::buildCleanTalkLink('settings_hoster_api_key', 'my/profile');

        $this->assertStringStartsWith('https://cleantalk.org/my/profile?', $link);
        $this->assertStringContainsString('utm_source=admin_panel', $link);
        $this->assertStringContainsString('utm_medium=settings', $link);
        $this->assertStringContainsString('utm_content=hoster_api_key', $link);

        // Verify anchor can be appended
        $linkWithAnchor = $link . '#api_keys';
        $this->assertStringEndsWith('#api_keys', $linkWithAnchor);
    }

    /**
     * Line 1374: Cloud Dashboard button with user_token
     */
    public function testLinkSettingsCloudDashboardButton()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'settings_cloud_dashboard_button',
            'my',
            array('user_token' => 'abc123', 'cp_mode' => 'antispam')
        );

        $this->assertStringStartsWith('https://cleantalk.org/my?', $link);
        $this->assertStringContainsString('user_token=abc123', $link);
        $this->assertStringContainsString('cp_mode=antispam', $link);
        $this->assertStringContainsString('utm_source=admin_panel', $link);
        $this->assertStringContainsString('utm_medium=settings', $link);
        $this->assertStringContainsString('utm_content=cloud_dashboard_button', $link);
    }

    /**
     * Line 1879: Blog email validation status link
     */
    public function testLinkBlogEmailValidationStatus()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'blog_email_validation_status',
            'real-time-email-address-existence-validation',
            array(),
            'https://blog.cleantalk.org'
        );

        $this->assertStringStartsWith('https://blog.cleantalk.org/real-time-email-address-existence-validation?', $link);
        $this->assertStringContainsString('utm_source=admin_panel', $link);
        $this->assertStringContainsString('utm_medium=settings', $link);
        $this->assertStringContainsString('utm_content=email_validation_status', $link);
    }

    public function testExactUrlBlogEmailValidationStatus()
    {
        $link = LinkConstructor::buildCleanTalkLink(
            'blog_email_validation_status',
            'real-time-email-address-existence-validation',
            array(),
            'https://blog.cleantalk.org'
        );
        $this->assertSame(
            'https://blog.cleantalk.org/real-time-email-address-existence-validation?utm_id=&utm_term=&utm_source=admin_panel&utm_medium=settings&utm_content=email_validation_status&utm_campaign=apbct_links',
            $link
        );
    }

    /**
     * Line 2013: Public offer link
     */
    public function testLinkSettingsPublicOffer()
    {
        $link = LinkConstructor::buildCleanTalkLink('settings_public_offer', 'publicoffer');

        $this->assertStringStartsWith('https://cleantalk.org/publicoffer?', $link);
        $this->assertStringContainsString('utm_source=admin_panel', $link);
        $this->assertStringContainsString('utm_medium=settings', $link);
        $this->assertStringContainsString('utm_content=public_offer', $link);
    }

    public function testExactUrlSettingsPublicOffer()
    {
        $link = LinkConstructor::buildCleanTalkLink('settings_public_offer', 'publicoffer');
        $this->assertSame(
            'https://cleantalk.org/publicoffer?utm_id=&utm_term=&utm_source=admin_panel&utm_medium=settings&utm_content=public_offer&utm_campaign=apbct_links',
            $link
        );
    }

    /**
     * Verify all new settings page presets exist
     */
    public function testAllSettingsPagePresetsExist()
    {
        $requiredPresets = array(
            'settings_support_open',
            'blog_search_form_protection',
            'settings_dashboard_link',
            'settings_email_existence_alert',
            'settings_hoster_api_key',
            'settings_cloud_dashboard_button',
            'blog_email_validation_status',
            'settings_public_offer',
        );

        foreach ($requiredPresets as $preset) {
            $this->assertArrayHasKey(
                $preset,
                LinkConstructor::$utm_presets,
                "UTM preset '$preset' must exist in LinkConstructor::\$utm_presets"
            );
        }
    }
}
