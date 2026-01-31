<?php

namespace Inc;

use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Request;
use PHPUnit\Framework\TestCase;

class TestContactFormValidate extends TestCase
{
    private $apbct;
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

    public function testContactFormValidateWCExpressCheckout()
    {
        update_option( 'active_plugins', array('woocommerce-gateway-stripe/woocommerce-gateway-stripe.php'), true );

        $_GET = array (
            'wc-ajax' => 'wc_stripe_normalize_address',
        );
        Get::getInstance()->variables = $_GET;

        $_POST = array (
            'security' => wp_create_nonce('wc-stripe-express-checkout-normalize-address'),
            'data' =>
                array (
                    'billing_address' =>
                        array (
                            'first_name' => 'Test',
                            'last_name' => 'Glynn',
                            'company' => '',
                            'email' => 's@cleantalk.org',
                            'phone' => '2222',
                            'country' => 'AU',
                            'address_1' => '34A Wongawilli St',
                            'address_2' => '',
                            'city' => 'Tullimbar',
                            'state' => 'NSW',
                            'postcode' => '2527',
                        ),
                    'shipping_address' =>
                        array (
                            'first_name' => 'Test',
                            'last_name' => 'Glynn',
                            'company' => '',
                            'phone' => '2222',
                            'country' => 'AU',
                            'address_1' => '25 Brushgrove Cct',
                            'address_2' => '',
                            'city' => 'Calderwood',
                            'state' => 'NSW',
                            'postcode' => '2527',
                            'method' =>
                                array (
                                    0 => 'free_shipping:21',
                                ),
                        ),
                ),
        );
        Post::getInstance()->variables = $_POST;

        $_REQUEST['security'] = wp_create_nonce('wc-stripe-express-checkout-normalize-address');
        Request::getInstance()->variables = $_REQUEST;

        add_action('apbct_skipped_request', function (...$arg) {
            $this->assertStringContainsString('ct_contact_form_validate():WOOCOMMERCE_SERVICES', $arg[0]);
        }, 10);
        $result = ct_contact_form_validate();
        $this->assertNull($result);

        update_option( 'active_plugins', array(), true );
    }
}
