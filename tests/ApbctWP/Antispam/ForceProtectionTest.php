<?php

namespace ApbctWP\Antispam;

use Cleantalk\ApbctWP\Antispam\ForceProtection;
use Cleantalk\ApbctWP\RequestParameters\RequestParameters;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\NoCookie;
use Cleantalk\ApbctWP\Variables\Server;

class ForceProtectionTest extends \ApbctTestCase
{
    private $protection;
    private $original_cookies;
    private $original_server;
    private $original_no_cookies;
    private $original_user_id;
    private $original_cookies_type;

    private const IFRAME = '<iframe src="https://form.typeform.com/to/test"></iframe>';

    protected function setUp(): void
    {
        parent::setUp();
        global $apbct;

        $this->original_cookies = $_COOKIE;
        $this->original_server = $_SERVER;
        $this->original_no_cookies = NoCookie::$no_cookies_data;
        $this->original_user_id = get_current_user_id();
        $this->original_cookies_type = $apbct->data['cookies_type'];
        wp_set_current_user(0);
        $_COOKIE = array();
        NoCookie::$no_cookies_data = array();
        $this->resetRequestCache();
        $this->protection = new ForceProtection();
    }

    protected function tearDown(): void
    {
        global $apbct;

        remove_filter('the_content', array($this->protection, 'modifyContent'));
        $_COOKIE = $this->original_cookies;
        $_SERVER = $this->original_server;
        NoCookie::$no_cookies_data = $this->original_no_cookies;
        $apbct->data['cookies_type'] = $this->original_cookies_type;
        wp_set_current_user($this->original_user_id);
        $this->resetRequestCache();
        parent::tearDown();
    }

    private function resetRequestCache()
    {
        Cookie::getInstance()->variables = array();
        Server::getInstance()->variables = array();
    }

    private function setPassToken($mode, $token)
    {
        global $apbct;
        $apbct->data['cookies_type'] = $mode;
        if ( $mode === 'native' ) {
            $_COOKIE[apbct__get_cookie_prefix() . 'apbct_force_protection_check'] = $token;
            $this->resetRequestCache();
        } else {
            $this->assertNotFalse(RequestParameters::set('apbct_force_protection_check', $token));
        }
    }

    private function signToken($expires)
    {
        $method = new \ReflectionMethod(ForceProtection::class, 'getPassSignature');
        $method->setAccessible(true);

        return $expires . '.' . $method->invoke($this->protection, $expires);
    }

    public function storageModes()
    {
        return array(
            'native' => array('native'),
            'alternative' => array('alternative'),
            'no cookies' => array('none'),
        );
    }

    /**
     * @dataProvider storageModes
     */
    public function testUnsignedAndInvalidPassTokensAreRejected($mode)
    {
        $expires = time() + HOUR_IN_SECONDS;
        $valid_token = $this->signToken($expires);
        $invalid_tokens = array(
            '0',
            '1',
            'forged',
            $expires . '.' . str_repeat('0', 64),
            $this->signToken(time() - 1),
            $this->signToken(time() + 2 * DAY_IN_SECONDS),
            ($expires + 1) . substr($valid_token, strpos($valid_token, '.')),
            $valid_token . '.extra',
        );

        foreach ( $invalid_tokens as $token ) {
            $this->setPassToken($mode, $token);
            $this->assertStringContainsString(
                'ct-encoded-form-wrapper',
                $this->protection->modifyContent(self::IFRAME),
                $token
            );
        }
    }

    /**
     * @dataProvider storageModes
     */
    public function testValidPassTokenPreservesIframe($mode)
    {
        $this->setPassToken($mode, $this->signToken(time() + HOUR_IN_SECONDS));

        $this->assertSame(self::IFRAME, $this->protection->modifyContent(self::IFRAME));
    }

    /**
     * @dataProvider storageModes
     */
    public function testPassTokenCannotBeReusedByAnotherVisitor($mode)
    {
        $this->setPassToken($mode, $this->signToken(time() + HOUR_IN_SECONDS));
        $_SERVER['HTTP_USER_AGENT'] = 'another-visitor';
        $this->resetRequestCache();

        $this->assertStringContainsString(
            'ct-encoded-form-wrapper',
            $this->protection->modifyContent(self::IFRAME)
        );
    }

    public function testPassTokenIsBoundToSite()
    {
        $this->setPassToken('native', $this->signToken(time() + HOUR_IN_SECONDS));
        $other_site = function () {
            return 'https://another-site.example';
        };
        add_filter('home_url', $other_site);
        try {
            $this->assertStringContainsString(
                'ct-encoded-form-wrapper',
                $this->protection->modifyContent(self::IFRAME)
            );
        } finally {
            remove_filter('home_url', $other_site);
        }
    }

    public function testForgedLoginCookieDoesNotBypassProtection()
    {
        $_COOKIE[LOGGED_IN_COOKIE] = 'x';
        $_COOKIE['wordpress_logged_in_' . md5(get_site_option('siteurl'))] = 'x';

        $this->assertStringContainsString(
            'ct-encoded-form-wrapper',
            $this->protection->modifyContent(self::IFRAME)
        );
    }

    public function testAuthenticatedUserStillSkipsProtection()
    {
        $user_id = wp_insert_user(array(
            'user_login' => 'force-protection-auth-test',
            'user_pass' => 'test-password',
        ));
        $this->assertFalse(is_wp_error($user_id));
        wp_set_current_user($user_id);
        try {
            $this->assertSame(self::IFRAME, $this->protection->modifyContent(self::IFRAME));
        } finally {
            wp_set_current_user(0);
            wp_delete_user($user_id);
        }
    }

    public function testUnrelatedIframeIsUnchanged()
    {
        $iframe = '<iframe src="https://example.com/video"></iframe>';

        $this->assertSame($iframe, $this->protection->modifyContent($iframe));
    }
}
