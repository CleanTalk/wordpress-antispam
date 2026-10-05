<?php

use Cleantalk\ApbctWP\WcSpamOrdersListTable;
use PHPUnit\Framework\TestCase;

class TestWcSpamOrdersListTable extends TestCase
{
    /**
     * @var WcSpamOrdersListTable
     */
    private $instance;

    /**
     * @var \ReflectionMethod
     */
    private $renderOrderDetailsColumn;

    /**
     * @var \ReflectionMethod
     */
    private $renderCustomerDetailsColumn;

    protected function setUp(): void
    {
        // Create instance without calling constructor to avoid dependencies
        $reflection = new ReflectionClass(WcSpamOrdersListTable::class);
        $this->instance = $reflection->newInstanceWithoutConstructor();

        // Make private methods accessible
        $this->renderOrderDetailsColumn = $reflection->getMethod('renderOrderDetailsColumn');
        $this->renderOrderDetailsColumn->setAccessible(true);

        $this->renderCustomerDetailsColumn = $reflection->getMethod('renderCustomerDetailsColumn');
        $this->renderCustomerDetailsColumn->setAccessible(true);
    }

    /**
     * Test renderOrderDetailsColumn returns error on invalid JSON
     */
    public function testRenderOrderDetailsColumnInvalidJson()
    {
        $result = $this->renderOrderDetailsColumn->invoke($this->instance, 'invalid json');

        $this->assertEquals('<b>Product details decoding error.</b><br>', $result);
    }

    /**
     * Test renderOrderDetailsColumn returns error on non-array JSON
     */
    public function testRenderOrderDetailsColumnNonArrayJson()
    {
        $result = $this->renderOrderDetailsColumn->invoke($this->instance, '"just a string"');

        $this->assertEquals('<b>Product details decoding error.</b><br>', $result);
    }

    /**
     * Test renderOrderDetailsColumn with valid order details (no WooCommerce)
     */
    public function testRenderOrderDetailsColumnValidDataWithoutWooCommerce()
    {
        $orderDetails = json_encode([
            ['product_id' => 1, 'quantity' => 2],
            ['product_id' => 2, 'quantity' => 5],
        ]);

        $result = $this->renderOrderDetailsColumn->invoke($this->instance, $orderDetails);

        // Without WooCommerce, product title should be 'Unavailable product'
        $this->assertStringContainsString('<b>Unavailable product</b>', $result);
        $this->assertStringContainsString(' - 2<br>', $result);
        $this->assertStringContainsString(' - 5<br>', $result);
    }

    /**
     * Test renderOrderDetailsColumn escapes HTML to prevent XSS
     */
    public function testRenderOrderDetailsColumnEscapesHtmlInQuantity()
    {
        $orderDetails = json_encode([
            ['product_id' => 1, 'quantity' => '<script>alert("test")</script>'],
        ]);

        $result = $this->renderOrderDetailsColumn->invoke($this->instance, $orderDetails);

        // Verify that script tags are escaped
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    /**
     * Test renderOrderDetailsColumn with empty array
     */
    public function testRenderOrderDetailsColumnEmptyArray()
    {
        $orderDetails = json_encode([]);

        $result = $this->renderOrderDetailsColumn->invoke($this->instance, $orderDetails);

        $this->assertEquals('', $result);
    }

    /**
     * Test renderCustomerDetailsColumn returns error on invalid JSON
     */
    public function testRenderCustomerDetailsColumnInvalidJson()
    {
        $result = $this->renderCustomerDetailsColumn->invoke($this->instance, 'invalid json');

        $this->assertEquals('<b>Customer details decoding error.</b><br>', $result);
    }

    /**
     * Test renderCustomerDetailsColumn with valid customer details
     */
    public function testRenderCustomerDetailsColumnValidData()
    {
        $customerDetails = json_encode([
            'billing_first_name' => 'John',
            'billing_last_name' => 'Doe',
            'billing_email' => 'john@example.com',
        ]);

        $result = $this->renderCustomerDetailsColumn->invoke($this->instance, $customerDetails);

        $this->assertStringContainsString('<b>John</b>', $result);
        $this->assertStringContainsString('<b>Doe</b>', $result);
        $this->assertStringContainsString('<b>john@example.com</b>', $result);
    }

    /**
     * Test renderCustomerDetailsColumn escapes HTML to prevent XSS
     */
    public function testRenderCustomerDetailsColumnEscapesHtml()
    {
        $customerDetails = json_encode([
            'billing_first_name' => '<script>alert("test")</script>',
            'billing_last_name' => '<img onerror="alert(1)" src="">',
            'billing_email' => '"><script>alert("email")</script>',
        ]);

        $result = $this->renderCustomerDetailsColumn->invoke($this->instance, $customerDetails);

        // Verify that malicious HTML is escaped
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringContainsString('&lt;img', $result);
    }

    /**
     * Test renderCustomerDetailsColumn with missing fields
     */
    public function testRenderCustomerDetailsColumnMissingFields()
    {
        $customerDetails = json_encode([
            'billing_first_name' => 'John',
            // billing_last_name and billing_email are missing
        ]);

        $result = $this->renderCustomerDetailsColumn->invoke($this->instance, $customerDetails);

        $this->assertStringContainsString('<b>John</b>', $result);
        // Empty values for missing fields
        $this->assertStringContainsString('<b></b>', $result);
    }

    /**
     * Test renderCustomerDetailsColumn with empty values
     */
    public function testRenderCustomerDetailsColumnEmptyValues()
    {
        $customerDetails = json_encode([
            'billing_first_name' => '',
            'billing_last_name' => '',
            'billing_email' => '',
        ]);

        $result = $this->renderCustomerDetailsColumn->invoke($this->instance, $customerDetails);

        // Should contain empty bold tags
        $expectedCount = substr_count($result, '<b></b>');
        $this->assertEquals(3, $expectedCount);
    }

    /**
     * Resets the memory of the request variables handler so that $_POST is read again.
     *
     * @return void
     */
    private function resetPostMemory()
    {
        \Cleantalk\ApbctWP\Variables\Post::getInstance()->variables = array();
    }

    /**
     * Test the action parameters are named so that the HPOS page controller ignores them.
     */
    public function testActionParamsAreNamespaced()
    {
        $this->assertEquals('apbct_bulk_action', WcSpamOrdersListTable::BULK_ACTION_PARAM);
        $this->assertEquals('apbct_row_action', WcSpamOrdersListTable::ROW_ACTION_PARAM);
        $this->assertEquals('apbct_row_nonce', WcSpamOrdersListTable::ROW_NONCE_PARAM);

        foreach ( array('action', '_wpnonce', '_wp_http_referer') as $reserved ) {
            $this->assertNotEquals($reserved, WcSpamOrdersListTable::BULK_ACTION_PARAM);
            $this->assertNotEquals($reserved, WcSpamOrdersListTable::ROW_ACTION_PARAM);
            $this->assertNotEquals($reserved, WcSpamOrdersListTable::ROW_NONCE_PARAM);
        }
    }

    /**
     * Test current_action reads the select of the top tablenav.
     */
    public function testCurrentActionReadsTopSelect()
    {
        $_POST = array(WcSpamOrdersListTable::BULK_ACTION_PARAM => 'delete');
        $this->resetPostMemory();

        $this->assertEquals('delete', $this->instance->current_action());
    }

    /**
     * Test current_action falls back to the select of the bottom tablenav.
     */
    public function testCurrentActionReadsBottomSelect()
    {
        $_POST = array(
            WcSpamOrdersListTable::BULK_ACTION_PARAM        => '-1',
            WcSpamOrdersListTable::BULK_ACTION_PARAM . '2'  => 'delete',
        );
        $this->resetPostMemory();

        $this->assertEquals('delete', $this->instance->current_action());
    }

    /**
     * Test current_action ignores the placeholder option of the selects.
     */
    public function testCurrentActionIgnoresPlaceholder()
    {
        $_POST = array(
            WcSpamOrdersListTable::BULK_ACTION_PARAM       => '-1',
            WcSpamOrdersListTable::BULK_ACTION_PARAM . '2' => '-1',
        );
        $this->resetPostMemory();

        $this->assertFalse($this->instance->current_action());
    }

    /**
     * Test current_action reports no action when the selects were not submitted.
     */
    public function testCurrentActionWithoutSubmission()
    {
        $_POST = array();
        $this->resetPostMemory();

        $this->assertFalse($this->instance->current_action());
    }

    /**
     * Test current_action stays blind to the 'action' parameter of the WooCommerce orders screen.
     */
    public function testCurrentActionIgnoresWooCommerceActionParam()
    {
        $_POST = array('action' => 'delete', 'action2' => 'delete');
        $this->resetPostMemory();

        $this->assertFalse($this->instance->current_action());
    }
}
