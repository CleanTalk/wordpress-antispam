<?php

class AuthenticationTest extends WP_UnitTestCase
{
    private $original_cookies;

    public function set_up()
    {
        parent::set_up();
        $this->original_cookies = $_COOKIE;
        $_COOKIE = array();
        wp_set_current_user(0);
    }

    public function tear_down()
    {
        $_COOKIE = $this->original_cookies;
        wp_set_current_user(0);
        parent::tear_down();
    }

    private function reloadCurrentUser()
    {
        unset($GLOBALS['current_user']);
    }

    public function testForgedCookieDoesNotAuthenticate()
    {
        $_COOKIE['wordpress_logged_in_' . md5(get_site_option('siteurl'))] = 'x';
        $_COOKIE[LOGGED_IN_COOKIE] = 'x';
        $this->reloadCurrentUser();

        $this->assertFalse(apbct_is_user_logged_in());
    }

    public function testValidCookieAuthenticates()
    {
        $user_id = self::factory()->user->create();
        $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie($user_id, time() + HOUR_IN_SECONDS, 'logged_in');
        $this->reloadCurrentUser();

        $this->assertTrue(apbct_is_user_logged_in());
        $this->assertSame($user_id, get_current_user_id());
    }

    public function testExpiredCookieDoesNotAuthenticate()
    {
        $user_id = self::factory()->user->create();
        $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie($user_id, time() - DAY_IN_SECONDS, 'logged_in');
        $this->reloadCurrentUser();

        $this->assertFalse(apbct_is_user_logged_in());
    }

    public function testRevokedSessionDoesNotAuthenticate()
    {
        $user_id = self::factory()->user->create();
        $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie($user_id, time() + HOUR_IN_SECONDS, 'logged_in');
        WP_Session_Tokens::get_instance($user_id)->destroy_all();
        $this->reloadCurrentUser();

        $this->assertFalse(apbct_is_user_logged_in());
    }

    public function testFallbackValidatorRejectsRevokedTokenWhileOtherSessionIsActive()
    {
        $user_id = self::factory()->user->create();
        $expiration = time() + HOUR_IN_SECONDS;
        $manager = WP_Session_Tokens::get_instance($user_id);
        $revoked_token = $manager->create($expiration);
        $active_token = $manager->create($expiration);
        $revoked_cookie = wp_generate_auth_cookie($user_id, $expiration, 'logged_in', $revoked_token);
        $active_cookie = wp_generate_auth_cookie($user_id, $expiration, 'logged_in', $active_token);

        $manager->destroy($revoked_token);

        $this->assertFalse(apbct_wp_validate_auth_cookie($revoked_cookie, 'logged_in'));
        $this->assertSame($user_id, apbct_wp_validate_auth_cookie($active_cookie, 'logged_in'));
    }

    public function contactFormVisitors()
    {
        return array(
            'forged cookie' => array(false),
            'authenticated' => array(true),
        );
    }

    /**
     * @dataProvider contactFormVisitors
     */
    public function testContactFormValidationSkipsOnlyAuthenticatedUsers($authenticated)
    {
        global $apbct;

        $settings = $apbct->settings;
        $post = $_POST;
        $skipped = array();
        $record_skip = function ($reason) use (&$skipped) {
            $skipped[] = $reason;
        };
        add_action('apbct_skipped_request', $record_skip);

        try {
            $apbct->settings['data__protect_logged_in'] = 0;
            $_POST = array('email' => 'visitor@example.com', 'message' => 'Hello');
            \Cleantalk\ApbctWP\Variables\Post::getInstance()->variables = array();
            \Cleantalk\ApbctWP\Variables\Get::getInstance()->variables = array();
            if ( $authenticated ) {
                wp_set_current_user(self::factory()->user->create());
            } else {
                $_COOKIE[LOGGED_IN_COOKIE] = 'x';
                $this->reloadCurrentUser();
            }
            // The admin side skip goes right after the logged-in skip, so the cloud check is not reached.
            set_current_screen('dashboard');

            ct_contact_form_validate();

            $this->assertNotEmpty($skipped);
            if ( $authenticated ) {
                $this->assertStringNotContainsString('Admin side request.', $skipped[0]);
            } else {
                $this->assertStringContainsString('Admin side request.', $skipped[0]);
            }
        } finally {
            remove_action('apbct_skipped_request', $record_skip);
            $GLOBALS['current_screen'] = null;
            $apbct->settings = $settings;
            $_POST = $post;
            \Cleantalk\ApbctWP\Variables\Post::getInstance()->variables = array();
        }
    }

    public function testPublicEmailEncoderPassKeyAloneDoesNotSkipEncoding()
    {
        global $apbct;

        $cookies_type = $apbct->data['cookies_type'];
        $cookie_name = apbct__get_cookie_prefix() . 'apbct_email_encoder_passed';

        try {
            $apbct->data['cookies_type'] = 'native';
            \Cleantalk\ApbctWP\Variables\AltSessions::wipe();
            $_COOKIE[$cookie_name] = apbct_get_email_encoder_pass_key();
            \Cleantalk\ApbctWP\Variables\Cookie::getInstance()->variables = array();

            $this->assertFalse(apbct_is_email_encoder_passed());

            apbct_email_encoder_set_passed();
            $this->assertTrue(apbct_is_email_encoder_passed());

            unset($_COOKIE[$cookie_name]);
            \Cleantalk\ApbctWP\Variables\Cookie::getInstance()->variables = array();
            $this->assertFalse(apbct_is_email_encoder_passed());
        } finally {
            \Cleantalk\ApbctWP\Variables\AltSessions::wipe();
            \Cleantalk\ApbctWP\Variables\Cookie::getInstance()->variables = array();
            $apbct->data['cookies_type'] = $cookies_type;
        }
    }

    public function testCurrentUserDoesNotRequireDefaultCookieName()
    {
        wp_set_current_user(self::factory()->user->create());

        $this->assertEmpty($_COOKIE);
        $this->assertTrue(apbct_is_user_logged_in());
    }

    public function testEarlyCheckValidatesCookieWithoutResolvingCurrentUser()
    {
        global $wp_actions;

        $user_id = self::factory()->user->create();
        $actions = $wp_actions;
        try {
            unset($wp_actions['set_current_user']);

            $_COOKIE[LOGGED_IN_COOKIE] = 'x';
            $this->reloadCurrentUser();
            $this->assertFalse(apbct_is_user_logged_in());

            $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie($user_id, time() + HOUR_IN_SECONDS, 'logged_in');
            $this->assertTrue(apbct_is_user_logged_in());

            $this->assertArrayNotHasKey('current_user', $GLOBALS);
            $this->assertSame(0, did_action('set_current_user'));
        } finally {
            $wp_actions = $actions;
        }
    }

    public function testEncoderCreatedBeforeAuthenticationFollowsCurrentUser()
    {
        global $apbct;

        $settings = $apbct->settings;
        $api_key = $apbct->api_key;
        $instance = new ReflectionProperty(\Cleantalk\Common\ContactsEncoder\ContactsEncoder::class, 'instance');
        $instance->setAccessible(true);
        $original_encoder = $instance->getValue();
        $content = '<p>alice.secret@example.com</p>';

        try {
            $instance->setValue(null, null);
            $apbct->api_key = 'test-api-key';
            $apbct->settings['data__email_decoder_encode_email_addresses'] = 1;
            $apbct->settings['data__email_decoder_obfuscation_mode'] =
                \Cleantalk\Common\ContactsEncoder\Dto\Params::OBFUSCATION_MODE_BLUR;
            $encoder = apbctGetContactsEncoder();
            $this->assertStringNotContainsString('alice.secret@example.com', $encoder->modifyContent($content));

            wp_set_current_user(self::factory()->user->create());
            $this->assertSame($content, $encoder->modifyContent($content));

            wp_set_current_user(0);
            $this->assertStringNotContainsString('alice.secret@example.com', $encoder->modifyContent($content));
        } finally {
            if ( isset($encoder) ) {
                remove_action('set_current_user', array($encoder, 'syncLoggedInState'));
            }
            $instance->setValue(null, $original_encoder);
            $apbct->settings = $settings;
            $apbct->api_key = $api_key;
        }
    }

    public function encoderVisitors()
    {
        return array(
            'anonymous' => array(false, false),
            'forged cookie' => array(true, false),
            'authenticated' => array(false, true),
        );
    }

    /**
     * @dataProvider encoderVisitors
     */
    public function testContactEncoderUsesVerifiedAuthentication($forged_cookie, $authenticated)
    {
        global $apbct, $wp_filter, $shortcode_tags;

        $settings = $apbct->settings;
        $api_key = $apbct->api_key;
        $filters = $wp_filter;
        $shortcodes = $shortcode_tags;
        $instance = new ReflectionProperty(\Cleantalk\Common\ContactsEncoder\ContactsEncoder::class, 'instance');
        $instance->setAccessible(true);
        $original_encoder = $instance->getValue();

        try {
            $instance->setValue(null, null);
            $apbct->api_key = 'test-api-key';
            $apbct->settings['data__email_decoder_encode_email_addresses'] = 1;
            $apbct->settings['data__email_decoder_obfuscation_mode'] =
                \Cleantalk\Common\ContactsEncoder\Dto\Params::OBFUSCATION_MODE_BLUR;
            if ( $forged_cookie ) {
                $_COOKIE[LOGGED_IN_COOKIE] = 'x';
                $this->reloadCurrentUser();
            }
            if ( $authenticated ) {
                wp_set_current_user(self::factory()->user->create());
            }

            $content = '<p>alice.secret@example.com</p>';
            $result = apbctGetContactsEncoder()->modifyContent($content);

            if ( $authenticated ) {
                $this->assertSame($content, $result);
            } else {
                $this->assertStringNotContainsString('alice.secret@example.com', $result);
                $this->assertStringContainsString('apbct-email-encoder', $result);
                $this->assertStringContainsString('data-original-string', $result);
            }
        } finally {
            $instance->setValue(null, $original_encoder);
            $apbct->settings = $settings;
            $apbct->api_key = $api_key;
            $wp_filter = $filters;
            $shortcode_tags = $shortcodes;
        }
    }
}
