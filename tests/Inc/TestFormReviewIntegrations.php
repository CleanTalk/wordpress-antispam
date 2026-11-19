<?php

namespace Inc;

use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class TestFormReviewIntegrations extends \WP_UnitTestCase
{
    public function set_up()
    {
        parent::set_up();
        require __DIR__ . '/FormReviewFixtures.inc';

        add_filter('pre_option_active_plugins', static function () {
            return array('woocommerce/woocommerce.php');
        });
    }

    public function testCartFormUsesWooCommerceValidation()
    {
        global $apbct;
        $apbct->settings['forms__wc_add_to_cart'] = 1;
        $cart_handler = array('WC_Form_Handler', 'add_to_cart_action');
        add_action('wp_loaded', $cart_handler);
        add_filter('woocommerce_add_to_cart_validation', '__return_true');

        $this->setRequest('/product/fixture/', array('add-to-cart' => '42', 'quantity' => '1'));
        $this->assertTrue(apbct_is_woocommerce_service_request());

        remove_action('wp_loaded', $cart_handler);
        $this->assertFalse(apbct_is_woocommerce_service_request());
    }

    public function testRegisteredGatewayOnPrettyAndPlainUrls()
    {
        $this->setRequest('/wc-api/fixture_gateway/', array('order_note' => 'Fixture payment'));
        $this->assertSame(25, skip_for_ct_contact_form_validate());
        $this->assertTrue(skip_for_ct_contact_form_validate_postdata());

        $this->setRequest('/?wc-api=Fixture_Gateway', array('order_note' => 'Fixture payment'));
        $this->assertSame(25, skip_for_ct_contact_form_validate());
        $this->assertTrue(skip_for_ct_contact_form_validate_postdata());
    }

    public function testBothWooCommercePasswordFormsUseTheirOwnNonce()
    {
        $uri = '/my-account/lost-password/';
        $this->setRequest($uri, array(
            'wc_reset_password' => 'true',
            'user_login' => 'fixture-customer',
            'woocommerce-lost-password-nonce' => wp_create_nonce('lost_password'),
        ));
        $this->assertSame(31, skip_for_ct_contact_form_validate());
        $this->assertTrue(skip_for_ct_contact_form_validate_postdata());

        $this->setRequest($uri, array(
            'wc_reset_password' => 'true',
            'password_1' => 'fixture-password',
            'password_2' => 'fixture-password',
            'reset_key' => 'fixture-key',
            'reset_login' => 'fixture-customer',
            'woocommerce-reset-password-nonce' => wp_create_nonce('reset_password'),
        ));
        $this->assertSame(31, skip_for_ct_contact_form_validate());
        $this->assertTrue(skip_for_ct_contact_form_validate_postdata());
    }

    public function testRenderedGravityFormUsesDedicatedValidation()
    {
        global $apbct;
        $apbct->settings['forms__contact_forms_test'] = 1;
        add_filter('gform_entry_is_spam', 'apbct_form__gravityForms__testSpam', 999, 3);

        $uri = '/contact/?lang=en';
        $this->setRequest($uri);
        $html = apbct_form__gravityForms__addField('<form></form>', array('id' => 11, 'lastPageButton' => array()));
        $this->assertSame(1, preg_match('/name="apbct_gform_route" value="([^"]+)"/', $html, $match));
        $this->assertStringContainsString('ct_multipage_form', $html);
        $this->assertNotSame($match[1], apbct_gravity_forms_route_token(12));

        $this->setRequest('/contact/?lang=de');
        $this->assertNotSame($match[1], apbct_gravity_forms_route_token(11));

        $this->setRequest($uri, array('gform_submit' => '11', 'apbct_gform_route' => $match[1]));
        $this->assertSame(38, skip_for_ct_contact_form_validate());
        $this->assertTrue(skip_for_ct_contact_form_validate_postdata());

        remove_filter('gform_entry_is_spam', 'apbct_form__gravityForms__testSpam', 999);
        $this->assertFalse(apbct_is_gravity_forms_submission());
    }

    private function setRequest($uri, array $post = array())
    {
        $_SERVER['REQUEST_URI'] = $uri;
        Server::getInstance()->variables['REQUEST_URI'] = $uri;
        parse_str((string) wp_parse_url($uri, PHP_URL_QUERY), $_GET);
        $_POST = $post;
        $_REQUEST = array_merge($_GET, $_POST);
        Get::getInstance()->variables = $_GET;
        Post::getInstance()->variables = $_POST;
    }
}
