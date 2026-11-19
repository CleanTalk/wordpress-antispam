<?php

namespace Inc;

use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Request;
use PHPUnit\Framework\TestCase;

class TestContactFormValidate extends TestCase
{
    private $apbctBackup;
    protected function setUp(): void
    {
        global $apbct;

        $this->apbctBackup = $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct = $this->apbctBackup;
    }

    public static function tearDownAfterClass(): void
    {
        Post::getInstance()->variables = [];
        Get::getInstance()->variables = [];
        Request::getInstance()->variables = [];
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
    }

    public function testContactFormValidateEmptyPost()
    {
        $_POST = [];
        Post::getInstance()->variables = $_POST;
        add_action('apbct_skipped_request', array($this, 'skippedEmptyPost'));
        $result = ct_contact_form_validate();
        $this->assertNull($result);
        remove_action('apbct_skipped_request', array($this, 'skippedEmptyPost'));
    }

    public static function skippedEmptyPost(...$arg)
    {
        self::assertStringContainsString('ct_contact_form_validate():SKIP_FOR_CT_CONTACT_FORM_VALIDATE', $arg[0]);
    }

    public function testBothFormHandlersExcludeTheStripeService()
    {
        $plugins = function () {
            return array('woocommerce/woocommerce.php', 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php');
        };
        $request = $_REQUEST;
        $post = $_POST;
        $get_cache = Get::getInstance()->variables;
        $reasons = array();
        $log = function ($reason) use (&$reasons) {
            $reasons[] = $reason;
        };
        $validate = function () {
            ct_contact_form_validate();
            ct_contact_form_validate_postdata();
        };

        add_filter('pre_option_active_plugins', $plugins);
        add_action('wc_ajax_wc_stripe_normalize_address', $validate);
        add_action('apbct_skipped_request', $log);
        try {
            Get::getInstance()->variables = array('wc-ajax' => 'wc_stripe_normalize_address');
            $_POST = $_REQUEST = array('security' => wp_create_nonce('wc-stripe-express-checkout-normalize-address'));

            do_action('wc_ajax_wc_stripe_normalize_address');

            $this->assertCount(2, $reasons);
            $this->assertStringContainsString('ct_contact_form_validate():WOOCOMMERCE_SERVICES', $reasons[0]);
            $this->assertStringContainsString('ct_contact_form_validate_postdata()', $reasons[1]);
        } finally {
            remove_action('apbct_skipped_request', $log);
            remove_action('wc_ajax_wc_stripe_normalize_address', $validate);
            remove_filter('pre_option_active_plugins', $plugins);
            Get::getInstance()->variables = $get_cache;
            $_REQUEST = $request;
            $_POST = $post;
        }
    }

    public function testRequestUrlMatchesTheConfiguredPathAndQuery()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? null;
        $get = $_GET;
        $url = 'https://example.test/store/account/?page_id=42';
        try {
            $_GET = array('page_id' => '42');
            $_SERVER['REQUEST_URI'] = '/store/account?page_id=42';
            $this->assertTrue(apbct_matches_request_url($url));

            $_SERVER['REQUEST_URI'] = '/store/account/settings/?page_id=42';
            $this->assertFalse(apbct_matches_request_url($url));

            $_GET = array('page_id' => '43');
            $_SERVER['REQUEST_URI'] = '/store/account/?page_id=43';
            $this->assertFalse(apbct_matches_request_url($url));
        } finally {
            if ($uri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $uri;
            }
            $_GET = $get;
        }
    }
}
