<?php

namespace Antispam\IntegrationsByClass;

use Cleantalk\Antispam\IntegrationsByClass\Woocommerce;
use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the checkout guards of the WooCommerce integration.
 *
 * The rejection message option only takes effect after the anti-spam call, so these tests
 * cover the guards that must keep returning before any call is made.
 */
class TestWoocommerceCheckoutGuards extends TestCase
{
    /**
     * @var Woocommerce
     */
    private $integration;

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

        $this->integration = new Woocommerce();
    }

    public function tearDown(): void
    {
        global $apbct;

        $apbct = $this->apbct_backup;

        parent::tearDown();
    }

    /**
     * Minimal stand-in for the WP_Error object passed by woocommerce_after_checkout_validation.
     *
     * @param array $errors
     *
     * @return object
     */
    private function makeErrors($errors = array())
    {
        $holder = new \stdClass();
        $holder->errors = $errors;

        return $holder;
    }

    /**
     * WooCommerce reports its own validation errors first, the anti-spam check is skipped then.
     */
    public function testCheckoutCheckReturnsEarlyWhenWooCommerceAlreadyFoundErrors()
    {
        global $apbct;
        $apbct->settings['data__wc_store_blocked_orders'] = 1;

        $this->assertNull(
            $this->integration->checkoutCheck(
                array(),
                $this->makeErrors(array('billing_email' => array('Invalid email')))
            )
        );
    }

    /**
     * The storage option must not disable the anti-spam check itself, it only controls
     * whether a blocked order is kept in the Spam folder.
     */
    public function testCheckoutCheckIsNotGatedByTheStorageOption()
    {
        $source = file_get_contents(
            CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/Antispam/IntegrationsByClass/Woocommerce.php'
        );

        $this->assertStringNotContainsString(
            'if ( ! $apbct->settings[\'data__wc_store_blocked_orders\'] ) {',
            $source
        );
    }

    /**
     * Anything that is not a WC_Order can not be checked, so the call is dropped.
     */
    public function testCheckoutCheckFromRestReturnsEarlyWithoutAnOrder()
    {
        global $apbct;
        $apbct->settings['data__wc_store_blocked_orders'] = 1;

        $this->assertNull($this->integration->checkoutCheckFromRest(null));
        $this->assertNull($this->integration->checkoutCheckFromRest(new \stdClass()));
    }

    /**
     * The order is not checkable regardless of the storage option value.
     */
    public function testCheckoutCheckFromRestReturnsEarlyWithoutAnOrderWhenStorageIsOff()
    {
        global $apbct;
        $apbct->settings['data__wc_store_blocked_orders'] = 0;

        $this->assertNull($this->integration->checkoutCheckFromRest(null));
        $this->assertNull($this->integration->checkoutCheckFromRest(new \stdClass()));
    }

    /**
     * Both checkout handlers stay attached to their WooCommerce hooks.
     */
    public function testCheckoutHandlersAreCallable()
    {
        $this->assertTrue(is_callable(array($this->integration, 'checkoutCheck')));
        $this->assertTrue(is_callable(array($this->integration, 'checkoutCheckFromRest')));
    }
}
