<?php

namespace Antispam\IntegrationsByClass;

use Cleantalk\Antispam\IntegrationsByClass\Woocommerce;
use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

/**
 * Guards for WooCommerce Add payment method.
 *
 * The cloud call needs a logged-in customer, so these tests stay on the returns
 * that must happen before that call.
 */
class TestWoocommerceAddPaymentMethod extends TestCase
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
     * The check is tied to "Protect logged in Users", not to the checkout option.
     */
    public function testAddPaymentMethodIsSkippedWhenLoggedInProtectionIsOff()
    {
        global $apbct;
        $apbct->settings['data__protect_logged_in'] = 0;
        $apbct->settings['forms__wc_checkout_test'] = 1;

        $this->assertTrue($this->integration->isAddPaymentMethodAllowed());
        $this->assertTrue($this->integration->filterAddPaymentMethodFormIsValid(true));
    }

    /**
     * Guests cannot reach the form, and a missing session must not call the cloud.
     */
    public function testAddPaymentMethodIsSkippedWhenNobodyIsLoggedIn()
    {
        global $apbct;
        $apbct->settings['data__protect_logged_in'] = 1;

        $this->assertFalse(is_user_logged_in());
        $this->assertTrue($this->integration->isAddPaymentMethodAllowed());
    }

    /**
     * WooCommerce already rejected the form. Do not replace that decision.
     */
    public function testFilterKeepsAnInvalidFormInvalid()
    {
        global $apbct;
        $apbct->settings['data__protect_logged_in'] = 1;

        $this->assertFalse($this->integration->filterAddPaymentMethodFormIsValid(false));
    }

    /**
     * Checkout setup intents must not be treated as Add payment method.
     */
    public function testStripeAjaxFromCheckoutIsLeftAlone()
    {
        $_SERVER['HTTP_REFERER'] = 'http://blog.loc/checkout/';
        $_SERVER['REQUEST_URI'] = '/?wc-ajax=wc_stripe_init_setup_intent';

        $this->assertNull($this->integration->blockStripeAddPaymentMethodAjax());
    }

    /**
     * The UPE confirm action belongs to Add payment method even without that slug in the URL.
     * Logged-out requests must not call the cloud.
     */
    public function testUpeConfirmSetupIntentIsSkippedWhenNobodyIsLoggedIn()
    {
        global $apbct;
        $apbct->settings['data__protect_logged_in'] = 1;
        $_SERVER['HTTP_REFERER'] = 'http://blog.loc/';
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
        $_POST['action'] = 'wc_stripe_create_and_confirm_setup_intent';

        $this->assertFalse(is_user_logged_in());
        $this->assertNull($this->integration->blockStripeCreateAndConfirmSetupIntentAjax());
    }
}
