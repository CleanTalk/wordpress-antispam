<?php

use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the "Hide rejection message to customers" WooCommerce option.
 *
 * The option keeps the legacy checkout behavior available: when it is disabled the blocked
 * visitor gets the rejection reason, otherwise the visitor is silently redirected.
 */
class TestWcHideRejectionMessageOption extends TestCase
{
    const OPTION = 'forms__wc_hide_rejection_message';

    const STORE_BLOCKED_ORDERS_OPTION = 'data__wc_store_blocked_orders';

    /**
     * @var mixed
     */
    private $apbct_backup;

    public function setUp(): void
    {
        global $apbct;
        parent::setUp();

        require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-settings.php');

        $this->apbct_backup = $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
    }

    public function tearDown(): void
    {
        global $apbct;

        $apbct = $this->apbct_backup;

        parent::tearDown();
    }

    /**
     * @return array
     */
    private function getWcFields()
    {
        $fields = apbct_settings__set_fields();

        $this->assertArrayHasKey('wc', $fields);
        $this->assertArrayHasKey('fields', $fields['wc']);

        return $fields['wc']['fields'];
    }

    public function testOptionIsEnabledByDefault()
    {
        $state = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));

        $this->assertArrayHasKey(self::OPTION, $state->default_settings);
        $this->assertSame(1, $state->default_settings[self::OPTION]);
    }

    public function testStoreBlockedOrdersStaysEnabledByDefault()
    {
        $state = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));

        $this->assertArrayHasKey(self::STORE_BLOCKED_ORDERS_OPTION, $state->default_settings);
        $this->assertSame(1, $state->default_settings[self::STORE_BLOCKED_ORDERS_OPTION]);
    }

    public function testOptionIsRegisteredInWooCommerceSettingsSection()
    {
        $wc_fields = $this->getWcFields();

        $this->assertArrayHasKey(self::OPTION, $wc_fields);
        $this->assertSame(
            'Hide rejection message from customers',
            $wc_fields[self::OPTION]['title']
        );
    }

    public function testOptionHasOnAndOffChoicesOnly()
    {
        $wc_fields = $this->getWcFields();

        $this->assertArrayHasKey('options', $wc_fields[self::OPTION]);

        $values = array_column($wc_fields[self::OPTION]['options'], 'val');

        $this->assertSame(array(1, 0), $values);
    }

    public function testOptionIsRenderedAsSubFieldOfTheCheckoutGroup()
    {
        $wc_fields = $this->getWcFields();

        $this->assertSame(
            'apbct_settings-field_wrapper--sub-sub',
            $wc_fields[self::OPTION]['class']
        );
    }

    public function testOptionIsPlacedRightAfterStoreBlockedOrders()
    {
        $wc_fields = $this->getWcFields();
        $keys = array_keys($wc_fields);

        $store_position = array_search(self::STORE_BLOCKED_ORDERS_OPTION, $keys, true);
        $message_position = array_search(self::OPTION, $keys, true);

        $this->assertNotFalse($store_position);
        $this->assertNotFalse($message_position);
        $this->assertSame($store_position + 1, $message_position);
    }
}
