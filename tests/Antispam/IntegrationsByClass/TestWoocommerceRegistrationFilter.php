<?php

namespace Antispam\IntegrationsByClass;

use Cleantalk\Antispam\IntegrationsByClass\Woocommerce;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Request;
use PHPUnit\Framework\TestCase;

/**
 * A service wc-ajax used to drop the registration check before WooCommerce
 * dispatched the AJAX action. The My Account form posts "register" and is
 * processed on wp_loaded.
 */
class TestWoocommerceRegistrationFilter extends TestCase
{
    /**
     * @var mixed
     */
    private $apbct_backup;

    public function setUp(): void
    {
        global $apbct;
        parent::setUp();

        $this->apbct_backup = $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $apbct->settings['forms__wc_register_from_order'] = 0;
        $apbct->settings['forms__wc_checkout_test'] = 0;
        $apbct->settings['forms__wc_add_to_cart'] = 0;
        $this->resetRequest();
        remove_all_filters('woocommerce_registration_errors');
    }

    public function tearDown(): void
    {
        global $apbct;

        remove_all_filters('woocommerce_registration_errors');
        $this->resetRequest();
        $apbct = $this->apbct_backup;

        parent::tearDown();
    }

    private function resetRequest()
    {
        $_GET = array();
        $_POST = array();
        $_REQUEST = array();
        Get::getInstance()->variables = array();
        Post::getInstance()->variables = array();
        Request::getInstance()->variables = array();
    }

    /**
     * @param array $get
     * @param array $post
     */
    private function attach($get, $post)
    {
        $this->resetRequest();
        $_GET = $get;
        $_POST = $post;
        $_REQUEST = array_merge($get, $post);
        (new Woocommerce())->doPublicWork();
    }

    public function testServiceAjaxKeepsRegistrationCheckWhenRegisterIsPosted()
    {
        $this->attach(
            array('wc-ajax' => 'get_refreshed_fragments'),
            array('wc-ajax' => 'checkout', 'register' => 'Register')
        );

        $this->assertSame(1, has_filter('woocommerce_registration_errors', 'ct_registration_errors'));
    }

    public function testCheckoutAjaxDropsRegistrationCheckWhenOptionIsOff()
    {
        $this->attach(array('wc-ajax' => 'checkout'), array());

        $this->assertFalse(has_filter('woocommerce_registration_errors', 'ct_registration_errors'));
    }

    public function testCompleteOrderWithRegisterKeepsTheCheck()
    {
        $this->attach(array(), array('wc-ajax' => 'complete_order', 'register' => 'Register'));

        $this->assertSame(1, has_filter('woocommerce_registration_errors', 'ct_registration_errors'));
    }
}
