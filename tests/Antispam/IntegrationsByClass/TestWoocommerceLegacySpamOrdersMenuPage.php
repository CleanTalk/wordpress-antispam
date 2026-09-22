<?php

namespace Antispam\IntegrationsByClass;

use Cleantalk\Antispam\IntegrationsByClass\Woocommerce;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the legacy (non-HPOS) admin page fallback of the WooCommerce integration.
 *
 * WooCommerce is never loaded in the test environment, so wc_get_page_screen_id() does not
 * exist here - this always exercises the "can not resolve the HPOS screen" branch, which is
 * also the real-world behavior on stores where WooCommerce itself is inactive/outdated.
 * The "HPOS is active" branch is covered by a source-inspection regression test instead,
 * since function_exists() checks can not be undone once a function is defined via eval/runkit.
 */
class TestWoocommerceLegacySpamOrdersMenuPage extends TestCase
{
    /**
     * @var Woocommerce
     */
    private $integration;

    /**
     * @var array
     */
    private $submenu_backup;

    public function setUp(): void
    {
        global $submenu;
        parent::setUp();

        $this->submenu_backup = $submenu;
        $submenu = array();

        // Grant the capability required by add_submenu_page() regardless of the current user.
        add_filter('user_has_cap', array($this, 'grantActivatePluginsCap'));

        $this->integration = new Woocommerce();
    }

    public function tearDown(): void
    {
        global $submenu;

        $submenu = $this->submenu_backup;
        remove_filter('user_has_cap', array($this, 'grantActivatePluginsCap'));

        parent::tearDown();
    }

    /**
     * @param array $allcaps
     *
     * @return array
     */
    public function grantActivatePluginsCap($allcaps)
    {
        $allcaps['activate_plugins'] = true;

        return $allcaps;
    }

    /**
     * Without wc_get_page_screen_id() (older WooCommerce, or the function unavailable
     * as is the case in this test environment) the fallback page must still be registered.
     */
    public function testAddsTheFallbackPageWhenTheHposScreenIdCanNotBeResolved()
    {
        $this->assertFalse(function_exists('wc_get_page_screen_id'));

        $this->integration->addLegacySpamOrdersMenuPage();

        global $submenu;

        $this->assertArrayHasKey('woocommerce', $submenu);

        $found = false;
        foreach ( $submenu['woocommerce'] as $item ) {
            if ( $item[2] === 'apbct_wc_spam_orders' ) {
                $found = true;
            }
        }

        $this->assertTrue($found, 'The apbct_wc_spam_orders submenu page was not registered.');
    }

    /**
     * Regression guard: the fallback must stay conditional on the HPOS screen id, otherwise
     * two competing "Spam" views would be registered on HPOS stores.
     */
    public function testFallbackStaysConditionalOnTheHposScreenId()
    {
        $source = file_get_contents(
            CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/Antispam/IntegrationsByClass/Woocommerce.php'
        );

        $this->assertStringContainsString(
            "function_exists('wc_get_page_screen_id') && wc_get_page_screen_id('shop_order') !== 'shop_order'",
            $source
        );
    }

    public function testHandlerIsCallable()
    {
        $this->assertTrue(is_callable(array($this->integration, 'addLegacySpamOrdersMenuPage')));
    }
}
