<?php

namespace Inc;

use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Request;
use PHPUnit\Framework\TestCase;

class TestContactIsSkipRequest extends TestCase
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
        $GLOBALS['current_screen'] = null;
    }

    public function testElementorBuilderSkipIsAdmin()
    {
        $this->assertTrue(self::skipped(
            'actions_save_builder_action',
            'save_builder',
            'elementor/elementor.php',
            function() {
                $GLOBALS['current_screen'] = new Mock_WP_Screen(); //is admin page
            },
            'elementor_skip'
        ));
    }

    public function testElementorBuilderSkipNotAdmin()
    {
        $this->assertFalse(self::skipped(
            'actions_save_builder_action',
            'save_builder',
            'elementor/elementor.php',
            function() {
                $GLOBALS['current_screen'] = null; //is not admin page
            },
            'elementor_skip'
        ));
    }

    public function testSkip__woocommerceAbandonedCart()
    {
        $this->assertTrue(self::checkSkipMutations(
            'action',
            'save_data',
            'woocommerce-abandoned-cart\woocommerce-ac.php',
            'woocommerce-abandoned-cart'
        ));
    }
    public function testSkip__wooAbandonedCartRecovery()
    {
        $this->assertTrue(self::checkSkipMutations(
            'action',
            'wacv_get_info',
            'woo-abandoned-cart-recovery/woo-abandoned-cart-recovery.php',
            'woo-abandoned-cart-recovery'
        ));
    }
    public function testSkip__ElementorLoginWidget()
    {
        $this->assertTrue(self::checkSkipMutations(
            'action',
            'elementor_woocommerce_checkout_login_user',
            'elementor/elementor.php',
            'elementor_skip'
        ));
    }
    public function testSkip__abandonedCartCapture()
    {
        $this->assertTrue(self::checkSkipMutations(
            'action',
            'acc_save_data',
            'abandoned-cart-capture/abandoned-cart-capture.php',
            'abandoned-cart-capture'
        ));
    }
    public function testSkip__WpMultiStepCheckout()
    {
        $this->assertTrue(self::checkSkipMutations(
            'action',
            'wpms_checkout_errors',
            'wp-multi-step-checkout/wp-multi-step-checkout.php',
            'wp-multi-step-checkout'
        ));
    }

    /**
     * Check if skipped on all data complied, and every case if not.
     * @param string $expected_key expected POST key
     * @param string $expected_action expected POST value
     * @param string $plugin_slug plugin slug string
     * @param string $expected_reason expected string from apbct_is_skip_request
     * @return true
     */
    private static function checkSkipMutations(string $expected_key, string $expected_action, string $plugin_slug, string $expected_reason): bool
    {
        //anything is OK - should be skipped
        self::assertTrue(
            self::skipped(
                $expected_key,
                $expected_action,
                $plugin_slug,
                function () {},
                $expected_reason
            )
        );
        //wrong post key
        self::assertFalse(
            self::skipped(
                'not_valid_key',
                $expected_action,
                $plugin_slug,
                function () {},
                $expected_reason
            )
        );
        //wrong post value
        self::assertFalse(
            self::skipped(
                $expected_key,
                'not_valid_action',
                $plugin_slug,
                function () {},
                $expected_reason
            )
        );
        //wrong slug
        self::assertFalse(
            self::skipped(
                $expected_key,
                $expected_action,
                'invalid_plugin/plugin.php',
                function () {},
                $expected_reason
            )
        );
        //wrong reason
        self::assertFalse(
            self::skipped(
                $expected_key,
                $expected_action,
                $plugin_slug,
                function () {},
                'not_valid_reason'
            )
        );
        //success
        return true;
    }

    /**
     * Returns true if apbct_is_skip_request returns the expected string, false otherwise
     * @param string $expected_key expected POST key
     * @param string $expected_action expected POST value
     * @param string $plugin_slug plugin slug string
     * @param mixed $prepare_function logic run before check
     * @param string $expected_reason expected string from apbct_is_skip_request
     * @return bool
     */
    private static function skipped(string $expected_key, string $expected_action, string $plugin_slug, $prepare_function, string $expected_reason): bool
    {
        $prepare_function();

        update_option( 'active_plugins', [$plugin_slug], false );

        $_POST = array (
            $expected_key => $expected_action,
        );

        Post::getInstance()->variables = $_POST;

        $result = apbct_is_skip_request(true);

        update_option( 'active_plugins', array(), false );

        return $result === $expected_reason;
    }
}

class Mock_WP_Screen {
    public $in_admin = true;

    public function in_admin($context = null) {
        if ($context === null) {
            return $this->in_admin;
        }
        return $this->in_admin && $context === 'your_context';
    }
}
