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
        $GLOBALS['current_screen'] = null;
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

    public function testElementorBuilderSkipIsAdmin()
    {
        update_option( 'active_plugins', array('elementor/elementor.php'), false );

        $_POST = array (
            'actions_save_builder_action' => 'save_builder',
        );
        $GLOBALS['current_screen'] = new Mock_WP_Screen();

        Post::getInstance()->variables = $_POST;

        $result = apbct_is_skip_request(true);
        $this->assertEquals('elementor_skip', $result);

        update_option( 'active_plugins', array(), false );
    }

    public function testElementorBuilderSkipIsAdminNoActivePlugin()
    {
        update_option( 'active_plugins', [], false );

        $_POST = array (
            'actions_save_builder_action' => 'save_builder',
        );
        $GLOBALS['current_screen'] = new Mock_WP_Screen();

        Post::getInstance()->variables = $_POST;

        $result = apbct_is_skip_request(true);
        $this->assertFalse($result);

        update_option( 'active_plugins', array(), false );
    }

    public function testElementorBuilderSkipNotAdmin()
    {
        update_option( 'active_plugins', array('elementor/elementor.php'), false );

        $_POST = array (
            'actions_save_builder_action' => 'save_builder',
        );
        $GLOBALS['current_screen'] = null;

        Post::getInstance()->variables = $_POST;

        $result = apbct_is_skip_request(true);
        $this->assertFalse($result);

        update_option( 'active_plugins', array(), false );
    }

    public function testElementorLoginWidgetSkip()
    {
        update_option( 'active_plugins', array('elementor/elementor.php'), false );

        $_POST = array (
            'action' => 'elementor_woocommerce_checkout_login_user',
        );

        Post::getInstance()->variables = $_POST;

        $result = apbct_is_skip_request(true);
        $this->assertEquals('elementor_skip', $result);

        update_option( 'active_plugins', array(), false );
    }

    public function testElementorLoginWidgetSkipNoActivePlugin()
    {
        update_option( 'active_plugins', [], false );

        $_POST = array (
            'action' => 'elementor_woocommerce_checkout_login_user',
        );

        Post::getInstance()->variables = $_POST;

        $result = apbct_is_skip_request(true);
        $this->assertFalse($result);

        update_option( 'active_plugins', array(), false );
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
