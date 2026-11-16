<?php

namespace Antispam\IntegrationsByClass;

use Cleantalk\Antispam\IntegrationsByClass\Woocommerce;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Request;
use PHPUnit\Framework\TestCase;

/**
 * The option "Spam test for registration during checkout" (forms__wc_register_from_order).
 *
 * When it is off, the account created during checkout must not be checked as a registration,
 * the request has to reach the order check. The block checkout (Store API) does not carry
 * the wc-ajax parameter, so the checkout is detected by the WooCommerce actions fired before
 * the customer is created.
 */
class TestWoocommerceRegistrationFromCheckout extends TestCase
{
    const CHECKOUT_ACTIONS = array(
        'woocommerce_checkout_process',
        'woocommerce_store_api_checkout_update_customer_from_request',
    );

    /**
     * @var Woocommerce
     */
    private $integration;

    /**
     * @var mixed
     */
    private $apbct_backup;

    /**
     * @var int
     */
    private $registration_check_calls = 0;

    public function setUp(): void
    {
        global $apbct;
        parent::setUp();

        $this->apbct_backup = $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        // The registration check stops on this option before any cloud call and reports the skip.
        $apbct->settings['forms__registrations_test'] = 0;

        $this->resetCheckoutActions();
        $this->setWcAjax(null);
        $_POST = array();

        $this->registration_check_calls = 0;
        add_action('apbct_skipped_request', array($this, 'countRegistrationCheck'));

        $this->integration = new Woocommerce();
    }

    public function tearDown(): void
    {
        global $apbct;

        remove_action('apbct_skipped_request', array($this, 'countRegistrationCheck'));
        remove_all_filters('woocommerce_registration_errors');
        $this->resetCheckoutActions();
        $this->setWcAjax(null);

        $apbct = $this->apbct_backup;

        parent::tearDown();
    }

    public function countRegistrationCheck()
    {
        $this->registration_check_calls++;
    }

    private function resetCheckoutActions()
    {
        global $wp_actions;

        foreach ( self::CHECKOUT_ACTIONS as $action ) {
            unset($wp_actions[$action]);
        }
    }

    /**
     * Mark the action as done without running its callbacks, did_action() reads this counter.
     *
     * @param string $action
     */
    private function markActionFired($action)
    {
        global $wp_actions;

        $wp_actions[$action] = 1;
    }

    /**
     * Set the wc-ajax request parameter, the variables classes cache what they have read.
     *
     * @param string|null $value
     */
    private function setWcAjax($value)
    {
        $_GET = array();
        $_REQUEST = array();
        if ( $value !== null ) {
            $_GET['wc-ajax'] = $_REQUEST['wc-ajax'] = $value;
        }
        Get::getInstance()->variables = array();
        Request::getInstance()->variables = array();
    }

    private function runRegistrationFilter()
    {
        $errors = new \WP_Error();
        $result = $this->integration->registrationErrors($errors, 'john.doe', 'john@example.com');
        $this->assertSame($errors, $result);
    }

    public function testFilterIsAttachedToWooCommerceRegistration()
    {
        $this->integration->doPublicWork();

        $this->assertSame(
            1,
            has_filter('woocommerce_registration_errors', array($this->integration, 'registrationErrors'))
        );
        $this->assertFalse(has_filter('woocommerce_registration_errors', 'ct_registration_errors'));
    }

    public function testFilterIsAttachedEvenForClassicCheckoutRequestWithOptionOff()
    {
        global $apbct;
        $apbct->settings['forms__wc_register_from_order'] = 0;
        $this->setWcAjax('checkout');

        $this->integration->doPublicWork();

        $this->assertSame(
            1,
            has_filter('woocommerce_registration_errors', array($this->integration, 'registrationErrors'))
        );
    }

    /**
     * The bug: block checkout with "Create an account" was checked as a registration.
     */
    public function testBlockCheckoutSkipsRegistrationCheckWhenOptionIsOff()
    {
        global $apbct;
        $apbct->settings['forms__wc_register_from_order'] = 0;

        $this->markActionFired('woocommerce_store_api_checkout_update_customer_from_request');
        $this->runRegistrationFilter();

        $this->assertSame(0, $this->registration_check_calls);
    }

    public function testClassicCheckoutSkipsRegistrationCheckWhenOptionIsOff()
    {
        global $apbct;
        $apbct->settings['forms__wc_register_from_order'] = 0;

        $this->markActionFired('woocommerce_checkout_process');
        $this->runRegistrationFilter();

        $this->assertSame(0, $this->registration_check_calls);
    }

    public function testWcAjaxCheckoutSkipsRegistrationCheckWhenOptionIsOff()
    {
        global $apbct;
        $apbct->settings['forms__wc_register_from_order'] = 0;

        foreach ( array('checkout', 'complete_order') as $wc_ajax ) {
            $this->setWcAjax($wc_ajax);
            $this->runRegistrationFilter();
        }

        $this->assertSame(0, $this->registration_check_calls);
    }

    public function testBlockCheckoutRunsRegistrationCheckWhenOptionIsOn()
    {
        global $apbct;
        $apbct->settings['forms__wc_register_from_order'] = 1;

        $this->markActionFired('woocommerce_store_api_checkout_update_customer_from_request');
        $this->runRegistrationFilter();

        $this->assertSame(1, $this->registration_check_calls);
    }

    /**
     * The My Account registration form is not a checkout, it is checked with the option off too.
     */
    public function testMyAccountRegistrationIsCheckedWhenOptionIsOff()
    {
        global $apbct;
        $apbct->settings['forms__wc_register_from_order'] = 0;

        $this->assertFalse(Woocommerce::isCheckoutInProgress());
        $this->runRegistrationFilter();

        $this->assertSame(1, $this->registration_check_calls);
    }
}
