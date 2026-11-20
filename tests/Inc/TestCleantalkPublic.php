<?php

namespace Inc;

use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class TestCleantalkPublic extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
    }

    protected function tearDown(): void
    {
        global $apbct;
        unset($apbct);
        parent::tearDown();
    }

    public function testApbctInitPixelUrlLoad()
    {
        // Arrange
        global $apbct;
        $apbct->data['bot_detector_enabled'] = 0;
        $apbct->settings['data__pixel'] = '3';

        // Act
        apbct_init();

        // Assert
        $this->assertNotNull($apbct->pixel_url);
    }

    public function testApbctInitPixelUrlNotLoad()
    {
        // Arrange
        global $apbct;
        $apbct->data['bot_detector_enabled'] = 1;
        $apbct->settings['data__pixel'] = '3';

        // Act
        apbct_init();

        // Assert
        $this->assertNull($apbct->pixel_url);
    }

    public function testApbctHeadSetsNativeJsCheckCookie()
    {
        $this->configureJsCheckCookie('native', '1', '1');
        $this->assertSame(1, has_action('wp_head', 'apbct_hook__wp_head__set_cookie__ct_checkjs'));

        $nonce = static function () {
            return 'js-check-test-nonce';
        };
        add_filter('apbct_csp_nonce', $nonce);

        ob_start();
        try {
            apbct_hook__wp_head__set_cookie__ct_checkjs();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
            remove_filter('apbct_csp_nonce', $nonce);
        }

        $cookie = apbct__get_cookie_prefix() . 'ct_checkjs=' . ct_get_checkjs_value() . '; path=/; samesite=lax';
        $this->assertStringContainsString('id="apbct-init-js-check"', $output);
        $this->assertStringContainsString('nonce="js-check-test-nonce"', $output);
        $this->assertStringContainsString(
            '/* apbct-init-js-check */ document.cookie = ' . wp_json_encode($cookie)
            . ' + (location.protocol === "https:" ? "; secure" : "");',
            $output
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testApbctHeadJsCheckCookieIncludesCookiebotExemption()
    {
        $this->configureJsCheckCookie('native', '1', '1');
        if (!class_exists('Cookiebot_WP')) {
            class_alias(self::class, 'Cookiebot_WP');
        }

        $this->expectOutputRegex('/<script\b[^>]*\bdata-cookieconsent="ignore"/');
        apbct_hook__wp_head__set_cookie__ct_checkjs();
    }

    public function testApbctHeadJsCheckCookieIsExcludedFromScriptDelay()
    {
        $marker = 'apbct-init-js-check';
        $this->assertContains($marker, apply_filters('rocket_delay_js_exclusions', array()));
        $this->assertContains($marker, apply_filters('perfmatters_delay_js_exclusions', array()));
        $this->assertSame(
            array('other-script'),
            apply_filters('perfmatters_delayed_scripts', array('other-script', $marker))
        );
    }

    /**
     * @dataProvider earlyJsCheckCookieDisabledProvider
     */
    public function testApbctHeadSkipsEarlyJsCheckCookie($mode, $cookie_setting, $search_test)
    {
        $this->configureJsCheckCookie($mode, $cookie_setting, $search_test);

        $this->expectOutputString('');
        apbct_hook__wp_head__set_cookie__ct_checkjs();
    }

    public function earlyJsCheckCookieDisabledProvider(): array
    {
        return array(
            'alternative cookies' => array('alternative', '2', '1'),
            'cookies disabled' => array('none', '0', '1'),
            'search protection disabled' => array('native', '1', '0'),
        );
    }

    public function testApbctHeadSkipsEarlyJsCheckCookieForUnprotectedLoggedInUser()
    {
        global $apbct;
        $this->configureJsCheckCookie('native', '1', '1');
        $apbct->settings['data__protect_logged_in'] = '0';
        $original_user_id = get_current_user_id();

        wp_set_current_user(1);
        try {
            $this->assertTrue(is_user_logged_in());
            $this->expectOutputString('');
            apbct_hook__wp_head__set_cookie__ct_checkjs();
        } finally {
            wp_set_current_user($original_user_id);
        }
    }

    private function configureJsCheckCookie($mode, $cookie_setting, $search_test): void
    {
        global $apbct;
        $apbct->data['cookies_type'] = $mode;
        $apbct->settings['data__set_cookies'] = $cookie_setting;
        $apbct->settings['forms__search_test'] = $search_test;
        $apbct->settings['data__use_static_js_key'] = '1';
        $apbct->settings['data__protect_logged_in'] = '1';
        $apbct->settings['exclusions__urls'] = '';
    }

    public function testApbctHookWpFooterShowPixel()
    {
        // Arrange
        global $apbct;
        $apbct->data['bot_detector_enabled'] = 0;
        $apbct->settings['data__pixel'] = '3';

        // Act
        ob_start();
        apbct_hook__wp_footer();
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('img alt="Cleantalk Pixel" title="Cleantalk Pixel" id="apbct_pixel" style="display: none;"', $output);
    }

    public function testApbctEnqueueAndLocalizePublicScripts()
    {
        // Arrange
        global $apbct;
        $apbct->data['bot_detector_enabled'] = 1;

        // Act
        apbct_enqueue_and_localize_public_scripts();

        // Assert
        $this->assertTrue(wp_script_is('ct_bot_detector'));
    }
}
