<?php

namespace Antispam\IntegrationsByClass;

use Cleantalk\Antispam\IntegrationsByClass\Woocommerce;
use Cleantalk\ApbctWP\Variables\Get;
use PHPUnit\Framework\TestCase;

/**
 * The overview of a blocked order is printed on the confirmation page the visitor is redirected to.
 * Classic themes load checkout/thankyou.php, block themes render the woocommerce/order-confirmation-*
 * blocks instead, so both entry points have to produce the same markup.
 */
class TestWoocommerceBlockedOrderOverview extends TestCase
{
    const TRANSIENT_PREFIX = 'apbct_blocked_order_';
    const KEY              = 'wc_order_TestOverviewKey';
    const STATUS_BLOCK     = 'woocommerce/order-confirmation-status';

    /**
     * @var Woocommerce
     */
    private $integration;

    public function setUp(): void
    {
        parent::setUp();

        $this->integration = new Woocommerce();

        $this->forgetKey();
    }

    public function tearDown(): void
    {
        delete_transient(self::TRANSIENT_PREFIX . self::KEY);

        $this->forgetKey();

        parent::tearDown();
    }

    /**
     * The key is read through the cached variables storage, so both have to be set.
     *
     * @param string $key
     *
     * @return void
     */
    private function setKey($key)
    {
        $_GET['key'] = $key;

        Get::getInstance()->variables = array();
    }

    /**
     * @return void
     */
    private function forgetKey()
    {
        unset($_GET['key']);

        Get::getInstance()->variables = array();
    }

    /**
     * @return void
     */
    private function storeOverview()
    {
        set_transient(
            self::TRANSIENT_PREFIX . self::KEY,
            array(
                'date'           => 'September 23, 2026',
                'total'          => '<span class="amount">99.00</span>',
                'payment_method' => 'Cash on delivery',
            ),
            HOUR_IN_SECONDS
        );
    }

    // -------------------------------------------------------------------
    // appendBlockedOrderOverviewToBlock() - the block theme entry point
    // -------------------------------------------------------------------

    public function testOverviewIsAppendedToTheOrderConfirmationStatusBlock()
    {
        $this->storeOverview();
        $this->setKey(self::KEY);

        $result = $this->integration->appendBlockedOrderOverviewToBlock(
            'STATUS',
            array('blockName' => self::STATUS_BLOCK)
        );

        $this->assertStringStartsWith('STATUS', $result, 'The original block content must be kept.');
        $this->assertStringContainsString('woocommerce-order-overview', $result);
        $this->assertStringContainsString('September 23, 2026', $result);
        $this->assertStringContainsString('99.00', $result);
        $this->assertStringContainsString('Cash on delivery', $result);
    }

    public function testUnrelatedBlocksAreLeftUntouched()
    {
        $this->storeOverview();
        $this->setKey(self::KEY);

        $result = $this->integration->appendBlockedOrderOverviewToBlock(
            'PARAGRAPH',
            array('blockName' => 'core/paragraph')
        );

        $this->assertSame('PARAGRAPH', $result);
    }

    public function testBlockWithoutNameIsLeftUntouched()
    {
        $this->storeOverview();
        $this->setKey(self::KEY);

        $result = $this->integration->appendBlockedOrderOverviewToBlock('RAW', array());

        $this->assertSame('RAW', $result);
    }

    public function testNothingIsAppendedWithoutTheKey()
    {
        $this->storeOverview();
        $this->forgetKey();

        $result = $this->integration->appendBlockedOrderOverviewToBlock(
            'STATUS',
            array('blockName' => self::STATUS_BLOCK)
        );

        $this->assertSame('STATUS', $result);
    }

    public function testNothingIsAppendedWhenTheDetailsAreGone()
    {
        // The transient is deliberately not stored - it expires an hour after the block
        $this->setKey(self::KEY);

        $result = $this->integration->appendBlockedOrderOverviewToBlock(
            'STATUS',
            array('blockName' => self::STATUS_BLOCK)
        );

        $this->assertSame('STATUS', $result);
    }

    public function testPaymentMethodRowIsOmittedWhenUnknown()
    {
        set_transient(
            self::TRANSIENT_PREFIX . self::KEY,
            array('date' => 'September 23, 2026', 'total' => '10.00'),
            HOUR_IN_SECONDS
        );
        $this->setKey(self::KEY);

        $result = $this->integration->appendBlockedOrderOverviewToBlock(
            'STATUS',
            array('blockName' => self::STATUS_BLOCK)
        );

        $this->assertStringContainsString('woocommerce-order-overview__date', $result);
        $this->assertStringContainsString('woocommerce-order-overview__total', $result);
        $this->assertStringNotContainsString('woocommerce-order-overview__method', $result);
    }

    // -------------------------------------------------------------------
    // renderBlockedOrderOverview() - the classic theme entry point
    // -------------------------------------------------------------------

    public function testOverviewIsPrintedAfterTheThankYouTemplate()
    {
        $this->storeOverview();
        $this->setKey(self::KEY);

        ob_start();
        $this->integration->renderBlockedOrderOverview('checkout/thankyou.php', '', '', array('order' => false));
        $output = ob_get_clean();

        $this->assertStringContainsString('woocommerce-order-overview', $output);
        $this->assertStringContainsString('September 23, 2026', $output);
    }

    public function testNothingIsPrintedForAnotherTemplate()
    {
        $this->storeOverview();
        $this->setKey(self::KEY);

        ob_start();
        $this->integration->renderBlockedOrderOverview('checkout/form-checkout.php', '', '', array());
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function testNothingIsPrintedWhenARealOrderStandsBehindThePage()
    {
        $this->storeOverview();
        $this->setKey(self::KEY);

        ob_start();
        $this->integration->renderBlockedOrderOverview(
            'checkout/thankyou.php',
            '',
            '',
            array('order' => new \stdClass())
        );
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    /**
     * Both entry points have to show the very same details.
     */
    public function testBothEntryPointsProduceTheSameMarkup()
    {
        $this->storeOverview();
        $this->setKey(self::KEY);

        ob_start();
        $this->integration->renderBlockedOrderOverview('checkout/thankyou.php', '', '', array('order' => false));
        $printed = ob_get_clean();

        $appended = $this->integration->appendBlockedOrderOverviewToBlock(
            '',
            array('blockName' => self::STATUS_BLOCK)
        );

        $this->assertSame($printed, $appended);
    }
}
