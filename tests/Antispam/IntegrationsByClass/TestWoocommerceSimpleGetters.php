<?php

// ---------------------------------------------------------------------------
// Minimal WC_Order stand-in — WooCommerce is never loaded in the test suite,
// so \WC_Order does not exist and needs to be declared in the global namespace
// for the "instanceof \WC_Order" branches exercised below.
// ---------------------------------------------------------------------------

namespace {

    if (! class_exists('WC_Order')) {
        class WC_Order
        {
        }
    }

    class WcOrderStub extends \WC_Order
    {
        public $formatted_total = '';
        public $payment_method_title = '';

        public function get_formatted_order_total() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
        {
            return $this->formatted_total;
        }

        public function get_payment_method_title() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
        {
            return $this->payment_method_title;
        }
    }
}

namespace Antispam\IntegrationsByClass {

    use Cleantalk\Antispam\IntegrationsByClass\Woocommerce;
    use Cleantalk\ApbctWP\State;
    use Cleantalk\ApbctWP\Variables\Post;
    use PHPUnit\Framework\TestCase;
    use WcOrderStub;

    /**
     * Unit tests for the small getter methods of the WooCommerce integration.
     *
     * WooCommerce itself is never loaded in the test environment, so every getter is exercised
     * through the "WooCommerce is not active" branch, plus the WC_Order branch using the
     * minimal WcOrderStub declared at the top of this file.
     */
    class TestWoocommerceSimpleGetters extends TestCase
    {
        /**
         * @var Woocommerce
         */
        private $integration;

        /**
         * @var mixed
         */
        private $apbct_backup;

        /**
         * @var array Backup of the 'active_plugins' option to restore after the test.
         */
        private $active_plugins_backup;

        public function setUp(): void
        {
            global $apbct;
            parent::setUp();

            $this->apbct_backup = $apbct;
            $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));

            $this->active_plugins_backup = get_option('active_plugins');

            // Reflected variables cache their values for the process lifetime, drop it
            // before every test so $_POST changes actually take effect.
            Post::getInstance()->variables = array();

            $this->integration = new Woocommerce();
        }

        public function tearDown(): void
        {
            global $apbct;

            $apbct = $this->apbct_backup;
            update_option('active_plugins', $this->active_plugins_backup);
            Post::getInstance()->variables = array();
            unset($_POST['payment_method']);

            parent::tearDown();
        }

        /**
         * @return \ReflectionMethod
         */
        private function getMethod($name)
        {
            $method = new \ReflectionMethod(Woocommerce::class, $name);
            $method->setAccessible(true);

            return $method;
        }

        // -------------------------------------------------------------------
        // getCompletedOrders()
        // -------------------------------------------------------------------

        public function testGetCompletedOrdersReturnsEmptyStringWhenWooCommerceIsNotActive()
        {
            update_option('active_plugins', array());

            $this->assertSame('', Woocommerce::getCompletedOrders());
        }

        public function testGetCompletedOrdersReturnsSqlFragmentWhenWooCommerceIsActive()
        {
            update_option('active_plugins', array('woocommerce/woocommerce.php'));

            $result = Woocommerce::getCompletedOrders();

            $this->assertIsString($result);
            $this->assertNotSame('', $result);
            $this->assertStringContainsString('wc-completed', $result);
        }

        // -------------------------------------------------------------------
        // getBlockedOrderTotal()
        // -------------------------------------------------------------------

        public function testGetBlockedOrderTotalReturnsEmptyStringWithoutAnOrderAndWithoutWooCommerce()
        {
            $result = $this->getMethod('getBlockedOrderTotal')->invoke($this->integration, null);

            $this->assertSame('', $result);
        }

        public function testGetBlockedOrderTotalUsesTheOrderWhenGiven()
        {
            $order = new WcOrderStub();
            $order->formatted_total = '$25.00';

            $result = $this->getMethod('getBlockedOrderTotal')->invoke($this->integration, $order);

            $this->assertSame('$25.00', $result);
        }

        // -------------------------------------------------------------------
        // getBlockedOrderPaymentMethod()
        // -------------------------------------------------------------------

        public function testGetBlockedOrderPaymentMethodReturnsEmptyStringWithoutAnOrderAndWithoutAChosenMethod()
        {
            $result = $this->getMethod('getBlockedOrderPaymentMethod')->invoke($this->integration, null);

            $this->assertSame('', $result);
        }

        public function testGetBlockedOrderPaymentMethodReturnsEmptyStringWithoutAnOrderWhenWooCommerceIsNotActive()
        {
            $_POST['payment_method'] = 'bacs';

            $result = $this->getMethod('getBlockedOrderPaymentMethod')->invoke($this->integration, null);

            // WC() is never defined in the test environment, so the gateway title can not be resolved.
            $this->assertSame('', $result);
        }

        public function testGetBlockedOrderPaymentMethodUsesTheOrderWhenGiven()
        {
            $order = new WcOrderStub();
            $order->payment_method_title = 'Direct bank transfer';

            $result = $this->getMethod('getBlockedOrderPaymentMethod')->invoke($this->integration, $order);

            $this->assertSame('Direct bank transfer', $result);
        }

        // -------------------------------------------------------------------
        // getOrdersListStatusCount()
        // -------------------------------------------------------------------

        public function testGetOrdersListStatusCountReturnsZeroWhenWooCommerceIsNotActive()
        {
            $result = $this->getMethod('getOrdersListStatusCount')->invoke($this->integration, 'wc-processing');

            $this->assertSame(0, $result);
        }

        public function testGetOrdersListStatusCountReadsTheSpamTableForTheSpamStatus()
        {
            $result = $this->getMethod('getOrdersListStatusCount')->invoke($this->integration, 'wc-spamorder');

            // No WooCommerce/spam table is guaranteed in the test environment, only the type matters here.
            $this->assertIsInt($result);
            $this->assertGreaterThanOrEqual(0, $result);
        }

        // -------------------------------------------------------------------
        // getOrdersListStatusLink()
        // -------------------------------------------------------------------

        public function testGetOrdersListStatusLinkRendersAnAnchorWithTheGivenCount()
        {
            $result = $this->getMethod('getOrdersListStatusLink')->invoke(
                $this->integration,
                'wc-processing',
                'Processing',
                false,
                7
            );

            $this->assertStringContainsString('<a href=', $result);
            $this->assertStringContainsString('status=wc-processing', $result);
            $this->assertStringContainsString('Processing', $result);
            $this->assertStringContainsString('(7)', $result);
            $this->assertStringNotContainsString('class="current"', $result);
        }

        public function testGetOrdersListStatusLinkMarksTheCurrentStatus()
        {
            $result = $this->getMethod('getOrdersListStatusLink')->invoke(
                $this->integration,
                'wc-spamorder',
                'Spam',
                true,
                3
            );

            $this->assertStringContainsString('class="current"', $result);
        }

        public function testGetOrdersListStatusLinkOmitsTheStatusQueryArgForAll()
        {
            $result = $this->getMethod('getOrdersListStatusLink')->invoke(
                $this->integration,
                '',
                'All',
                true,
                42
            );

            $this->assertStringNotContainsString('status=', $result);
            $this->assertStringContainsString('(42)', $result);
        }

        public function testGetOrdersListStatusLinkCountsTheStatusWhenNotGiven()
        {
            $result = $this->getMethod('getOrdersListStatusLink')->invoke(
                $this->integration,
                'wc-processing',
                'Processing',
                false,
                null
            );

            // Without WooCommerce active the count resolves to 0 via getOrdersListStatusCount().
            $this->assertStringContainsString('(0)', $result);
        }
    }
}
