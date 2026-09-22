<?php

// ---------------------------------------------------------------------------
// Minimal \WC_Order stand-in with an update_status() spy - WooCommerce is never
// loaded in the test suite, so the real class does not exist. Declared once in
// the global namespace, guarded so it plays nicely with any other test file
// that needs the same bare "instanceof \WC_Order" stub.
// ---------------------------------------------------------------------------

namespace {

    if (! class_exists('WC_Order')) {
        class WC_Order
        {
            /**
             * @var int
             */
            public $id = 0;

            /**
             * @var array Recorded (order_id, new_status) calls, shared by every instance.
             */
            public static $update_status_calls = array();

            public function __construct($id = 0)
            {
                $this->id = $id;
            }

            public function update_status($new_status) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
            {
                self::$update_status_calls[] = array($this->id, $new_status);

                return true;
            }
        }
    }
}

namespace Antispam\IntegrationsByClass {

    use Cleantalk\Antispam\IntegrationsByClass\Woocommerce;
    use Cleantalk\ApbctWP\State;
    use Cleantalk\ApbctWP\Variables\Get;
    use Cleantalk\ApbctWP\Variables\Post;
    use PHPUnit\Framework\TestCase;

    /**
     * Unit tests for the admin/list-table hooks of the WooCommerce integration:
     * the HPOS 'Spam' view wiring, the legacy status filters and the bulk actions.
     */
    class TestWoocommerceAdminListTableHooks extends TestCase
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
            global $apbct, $wpdb;
            parent::setUp();

            $this->apbct_backup = $apbct;
            $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));

            $wpdb->query('TRUNCATE TABLE ' . APBCT_TBL_WC_SPAM_ORDERS);

            Get::getInstance()->variables = array();
            Post::getInstance()->variables = array();
            \WC_Order::$update_status_calls = array();

            $this->integration = new Woocommerce();
        }

        public function tearDown(): void
        {
            global $apbct, $wpdb;

            $apbct = $this->apbct_backup;
            $wpdb->query('TRUNCATE TABLE ' . APBCT_TBL_WC_SPAM_ORDERS);

            Get::getInstance()->variables = array();
            Post::getInstance()->variables = array();
            unset($GLOBALS['post_status'], $_GET['status']);

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
        // addOrdersListStatusViews()
        // -------------------------------------------------------------------

        public function testAddOrdersListStatusViewsReturnsEarlyWithoutAScreenId()
        {
            $this->assertNull(
                $this->integration->addOrdersListStatusViews(new \stdClass())
            );
        }

        public function testAddOrdersListStatusViewsReturnsEarlyWithoutHposFunction()
        {
            $this->assertFalse(function_exists('wc_get_page_screen_id'));

            $screen = new \stdClass();
            $screen->id = 'edit-shop_order';

            $this->assertNull($this->integration->addOrdersListStatusViews($screen));
        }

        // -------------------------------------------------------------------
        // addOrdersListStatusLinks()
        // -------------------------------------------------------------------

        public function testAddOrdersListStatusLinksReturnsNonArrayInputUnchanged()
        {
            $this->assertSame('not-an-array', $this->integration->addOrdersListStatusLinks('not-an-array'));
            $this->assertNull($this->integration->addOrdersListStatusLinks(null));
        }

        public function testAddOrdersListStatusLinksLeavesArrayUnchangedWithoutWooCommerce()
        {
            $this->assertFalse(function_exists('wc_get_order_statuses'));

            $views = array('all' => '<a>All</a>');

            $this->assertSame($views, $this->integration->addOrdersListStatusLinks($views));
        }

        // -------------------------------------------------------------------
        // getOrdersListViews() (private)
        // -------------------------------------------------------------------

        public function testGetOrdersListViewsReturnsEmptyArrayWithoutWooCommerce()
        {
            $this->assertFalse(function_exists('wc_get_order_statuses'));

            $result = $this->getMethod('getOrdersListViews')->invoke($this->integration);

            $this->assertSame(array(), $result);
        }

        // -------------------------------------------------------------------
        // keepOrdersListWhenSpamOrdersExist()
        // -------------------------------------------------------------------

        public function testKeepOrdersListWhenSpamOrdersExistPassesThroughWithNoSpamOrders()
        {
            $this->assertNull($this->integration->keepOrdersListWhenSpamOrdersExist(null));
            $this->assertTrue($this->integration->keepOrdersListWhenSpamOrdersExist(true));
        }

        public function testKeepOrdersListWhenSpamOrdersExistForcesFalseWithStoredSpamOrders()
        {
            global $wpdb;

            $wpdb->insert(
                APBCT_TBL_WC_SPAM_ORDERS,
                array(
                    'order_details'    => '[]',
                    'customer_details' => '[]',
                    'order_date'       => time(),
                )
            );

            $this->assertFalse($this->integration->keepOrdersListWhenSpamOrdersExist(true));
            $this->assertFalse($this->integration->keepOrdersListWhenSpamOrdersExist(null));
        }

        // -------------------------------------------------------------------
        // replaceOrdersListRenderer() (private)
        // -------------------------------------------------------------------

        public function testReplaceOrdersListRendererReturnsEarlyWhenPageHookIsUnknown()
        {
            $this->getMethod('replaceOrdersListRenderer')->invoke($this->integration, 'no_such_page_hook');

            $this->assertFalse(has_action('no_such_page_hook', array($this->integration, 'renderSpamOrdersPage')));
        }

        public function testReplaceOrdersListRendererReplacesThePageController()
        {
            $page_hook = 'apbct_test_page_hook';
            $controller = new WcOrdersPageControllerStub();

            add_action($page_hook, array($controller, 'output'));

            $this->getMethod('replaceOrdersListRenderer')->invoke($this->integration, $page_hook);

            $this->assertFalse(
                has_action($page_hook, array($controller, 'output')),
                'The original WooCommerce page controller must be removed.'
            );
            $this->assertNotFalse(
                has_action($page_hook, array($this->integration, 'renderSpamOrdersPage')),
                'The spam orders renderer must take over the page hook.'
            );

            remove_action($page_hook, array($this->integration, 'renderSpamOrdersPage'));
        }

        public function testReplaceOrdersListRendererLeavesUnrelatedCallbacksAlone()
        {
            $page_hook = 'apbct_test_page_hook_unrelated';
            $unrelated = new \stdClass();
            $unrelated_callback = function () {
            };

            add_action($page_hook, $unrelated_callback);

            $this->getMethod('replaceOrdersListRenderer')->invoke($this->integration, $page_hook);

            $this->assertNotFalse(has_action($page_hook, $unrelated_callback));
            $this->assertFalse(has_action($page_hook, array($this->integration, 'renderSpamOrdersPage')));

            remove_action($page_hook, $unrelated_callback);
        }

        // -------------------------------------------------------------------
        // addOrdersSpamStatus() / addOrdersSpamStatusSelect() / addOrdersSpamStatusHideFromList()
        // -------------------------------------------------------------------

        public function testAddOrdersSpamStatusRegistersTheSpamStatus()
        {
            $result = $this->integration->addOrdersSpamStatus(array('wc-processing' => 'Processing'));

            $this->assertArrayHasKey('wc-spamorder', $result);
            $this->assertSame('Spam', $result['wc-spamorder']['label']);
            $this->assertTrue($result['wc-spamorder']['show_in_admin_all_list']);
        }

        public function testAddOrdersSpamStatusSelectRegistersTheSpamOption()
        {
            $result = $this->integration->addOrdersSpamStatusSelect(array('wc-processing' => 'Processing'));

            $this->assertSame('Spam', $result['wc-spamorder']);
        }

        public function testAddOrdersSpamStatusHideFromListRemovesTheSpamStatusOnTheOrdersScreen()
        {
            global $pagenow;

            $pagenow_backup = $pagenow;
            $pagenow = 'edit.php';

            $query = new \stdClass();
            $query->query_vars = array(
                'post_type'   => 'shop_order',
                'post_status' => array('wc-processing', 'wc-spamorder'),
            );

            $this->integration->addOrdersSpamStatusHideFromList($query);

            $this->assertSame(array('wc-processing'), array_values($query->query_vars['post_status']));

            $pagenow = $pagenow_backup;
        }

        public function testAddOrdersSpamStatusHideFromListLeavesOtherScreensAlone()
        {
            global $pagenow;

            $pagenow_backup = $pagenow;
            $pagenow = 'index.php';

            $query = new \stdClass();
            $query->query_vars = array(
                'post_type'   => 'shop_order',
                'post_status' => array('wc-processing', 'wc-spamorder'),
            );

            $this->integration->addOrdersSpamStatusHideFromList($query);

            $this->assertContains('wc-spamorder', $query->query_vars['post_status']);

            $pagenow = $pagenow_backup;
        }

        // -------------------------------------------------------------------
        // addSpamActionToBulk()
        // -------------------------------------------------------------------

        public function testAddSpamActionToBulkOffersMarkAsSpamByDefault()
        {
            $result = $this->integration->addSpamActionToBulk(array());

            $this->assertArrayHasKey('spamorder', $result);
            $this->assertArrayNotHasKey('unspamorder', $result);
        }

        public function testAddSpamActionToBulkOffersUnmarkOnTheSpamScreen()
        {
            set_query_var('post_status', 'wc-spamorder');

            $result = $this->integration->addSpamActionToBulk(array());

            $this->assertArrayHasKey('unspamorder', $result);
            $this->assertArrayNotHasKey('spamorder', $result);

            set_query_var('post_status', null);
        }

        // -------------------------------------------------------------------
        // addSpamActionToBulkHandle()
        // -------------------------------------------------------------------

        public function testAddSpamActionToBulkHandleIgnoresUnrelatedActions()
        {
            $redirect = 'https://example.test/wp-admin/edit.php';

            $result = $this->integration->addSpamActionToBulkHandle($redirect, 'trash', array(1, 2));

            $this->assertSame($redirect, $result);
            $this->assertSame(array(), \WC_Order::$update_status_calls);
        }

        public function testAddSpamActionToBulkHandleMarksOrdersAsSpam()
        {
            $redirect = 'https://example.test/wp-admin/edit.php';

            $result = $this->integration->addSpamActionToBulkHandle($redirect, 'spamorder', array(11, 12));

            $this->assertSame(
                array(array(11, 'wc-spamorder'), array(12, 'wc-spamorder')),
                \WC_Order::$update_status_calls
            );
            $this->assertStringContainsString('bulk_action=marked_spamorder', $result);
            $this->assertStringContainsString('changed=2', $result);
        }

        public function testAddSpamActionToBulkHandleUnmarksOrders()
        {
            $redirect = 'https://example.test/wp-admin/edit.php';

            $result = $this->integration->addSpamActionToBulkHandle($redirect, 'unspamorder', array(21));

            $this->assertSame(array(array(21, 'wc-on-hold')), \WC_Order::$update_status_calls);
            $this->assertStringContainsString('bulk_action=marked_unspamorder', $result);
            $this->assertStringContainsString('changed=1', $result);
        }

        // -------------------------------------------------------------------
        // renderSpamOrdersPage()
        // -------------------------------------------------------------------

        public function testRenderSpamOrdersPagePrintsTheWrapperMarkup()
        {
            ob_start();
            $this->integration->renderSpamOrdersPage();
            $output = ob_get_clean();

            $this->assertStringContainsString('class="wrap"', $output);
            $this->assertStringContainsString('Spam orders', $output);
            $this->assertStringContainsString('<form', $output);
        }
    }

}

// ---------------------------------------------------------------------------
// Stand-in for WooCommerce's internal \Automattic\WooCommerce\Internal\Admin\Orders\PageController,
// matched by replaceOrdersListRenderer() via a strpos() check for the 'Admin\Orders\PageController'
// substring in get_class(). The namespace below is chosen purely to make that substring match.
// ---------------------------------------------------------------------------

namespace Admin\Orders {

    class PageController
    {
        public function output()
        {
        }
    }
}

namespace Antispam\IntegrationsByClass {

    class_alias(\Admin\Orders\PageController::class, __NAMESPACE__ . '\WcOrdersPageControllerStub');
}
