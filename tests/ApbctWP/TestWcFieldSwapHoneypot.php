<?php

use Cleantalk\ApbctWP\WcFieldSwapHoneypot;
use PHPUnit\Framework\TestCase;

class TestWcFieldSwapHoneypot extends TestCase
{
    protected function setUp(): void
    {
        if ( ! defined('ABSPATH') ) {
            define('ABSPATH', '/var/www/wordpress/');
        }
        if ( ! defined('NONCE_SALT') ) {
            define('NONCE_SALT', 'test-nonce-salt');
        }
    }

    public function testGetSecretNameIsStableAndStartsWithLetter()
    {
        $secret = WcFieldSwapHoneypot::getSecretName('billing_email');

        $this->assertSame(10, strlen($secret));
        $this->assertSame($secret, WcFieldSwapHoneypot::getSecretName('billing_email'));
        $this->assertFalse(is_numeric($secret[0]));
    }

    public function testEvaluateFromPostedDataReturnsNullWhenSwapFieldsMissing()
    {
        $result = WcFieldSwapHoneypot::evaluateFromPostedData(array());

        $this->assertNull($result['status']);
        $this->assertNull($result['value']);
        $this->assertNull($result['source']);
    }

    public function testEvaluateFromPostedDataDetectsFilledTrap()
    {
        $result = WcFieldSwapHoneypot::evaluateFromPostedData(array(
            'billing_email' => 'bot@spam.test',
        ));

        $this->assertSame(0, $result['status']);
        $this->assertSame('bot@spam.test', $result['value']);
        $this->assertSame('billing_email', $result['source']);
    }

    public function testEvaluateFromPostedDataDetectsCleanTrap()
    {
        $secret = WcFieldSwapHoneypot::getSecretName('billing_email');

        $result = WcFieldSwapHoneypot::evaluateFromPostedData(array(
            'billing_email' => '',
            $secret         => 'user@example.com',
        ));

        $this->assertSame(1, $result['status']);
        $this->assertNull($result['value']);
        $this->assertSame('billing_email', $result['source']);
    }

    public function testCaptureCheckoutResultDetectsTrapFromRawPost()
    {
        global $apbct;
        $apbct = (object) array(
            'settings' => array(
                'data__honeypot_field' => 1,
            ),
        );

        $secret = WcFieldSwapHoneypot::getSecretName('billing_email');
        $_POST = array(
            'wc-ajax' => 'checkout',
            'billing_email' => 'bot@spam.test',
            $secret => '',
        );

        $wc_posted_data = array(
            $secret => '',
        );

        WcFieldSwapHoneypot::captureCheckoutResult($wc_posted_data);
        $result = WcFieldSwapHoneypot::getCheckoutResult();

        $this->assertSame(0, $result['status']);
        $this->assertSame('bot@spam.test', $result['value']);
        $this->assertSame('billing_email', $result['source']);

        unset($_POST);
        unset($apbct);
    }

    public function testCaptureCheckoutResultDoesNotChangePostedDataKeys()
    {
        global $apbct;
        $apbct = (object) array(
            'settings' => array(
                'data__honeypot_field' => 1,
            ),
        );

        $secret = WcFieldSwapHoneypot::getSecretName('billing_email');
        $_POST = array(
            'wc-ajax' => 'checkout',
            'billing_email' => '',
            $secret => 'user@example.com',
        );

        $posted_data = array(
            'billing_email' => '',
            $secret         => 'user@example.com',
        );

        $captured = WcFieldSwapHoneypot::captureCheckoutResult($posted_data);

        $this->assertSame('user@example.com', $captured[$secret]);
        $this->assertArrayHasKey($secret, $captured);
        $this->assertSame(1, WcFieldSwapHoneypot::getCheckoutResult()['status']);

        unset($_POST);
        unset($apbct);
    }

    public function testEnrichInputArrayForCleanTalkAddsBillingEmail()
    {
        global $apbct;
        $apbct = (object) array(
            'settings' => array(
                'data__honeypot_field' => 1,
            ),
        );

        $secret = WcFieldSwapHoneypot::getSecretName('billing_email');
        $_POST = array(
            'wc-ajax' => 'checkout',
            'billing_email' => '',
            $secret => 'user@example.com',
        );

        $enriched = WcFieldSwapHoneypot::enrichInputArrayForCleanTalk(array(
            'billing_email' => '',
            $secret         => 'user@example.com',
        ));

        $this->assertSame('user@example.com', $enriched['billing_email']);
        $this->assertSame('user@example.com', $enriched[$secret]);

        unset($_POST);
        unset($apbct);
    }

    public function testSwapCheckoutFieldsRenamesBillingEmail()
    {
        global $apbct;
        $apbct = (object) array(
            'settings' => array(
                'data__honeypot_field' => 1,
            ),
        );

        $fields = array(
            'billing' => array(
                'billing_email' => array(
                    'type'     => 'email',
                    'label'    => 'Email',
                    'required' => true,
                ),
            ),
        );

        $swapped = WcFieldSwapHoneypot::swapCheckoutFields($fields);
        $secret = WcFieldSwapHoneypot::getSecretName('billing_email');

        $this->assertArrayNotHasKey('billing_email', $swapped['billing']);
        $this->assertArrayHasKey($secret, $swapped['billing']);
        $this->assertSame($secret, $swapped['billing'][$secret]['id']);

        unset($apbct);
    }

    public function testSwapCheckoutFieldsLeavesBillingEmailWhenFluidCheckoutIsActive()
    {
        if ( ! class_exists('FluidCheckout') ) {
            $this->markTestSkipped('Fluid Checkout is not installed.');
        }

        $this->assertBillingEmailIsNotSwappedOnUnsupportedCheckout();
    }

    public function testSwapCheckoutFieldsLeavesBillingEmailWhenXStoreThemeIsActive()
    {
        if ( ! function_exists('get_template') || get_template() !== 'xstore' ) {
            $this->markTestSkipped('XStore theme is not active.');
        }

        $this->assertBillingEmailIsNotSwappedOnUnsupportedCheckout();
    }

    private function assertBillingEmailIsNotSwappedOnUnsupportedCheckout()
    {
        global $apbct;
        $apbct = (object) array(
            'settings' => array(
                'data__honeypot_field' => 1,
            ),
        );

        $fields = array(
            'billing' => array(
                'billing_email' => array(
                    'type'     => 'email',
                    'label'    => 'Email',
                    'required' => true,
                ),
            ),
        );

        $swapped = WcFieldSwapHoneypot::swapCheckoutFields($fields);

        $this->assertArrayHasKey('billing_email', $swapped['billing']);
        $this->assertFalse(WcFieldSwapHoneypot::isActive());

        unset($apbct);
    }
}
