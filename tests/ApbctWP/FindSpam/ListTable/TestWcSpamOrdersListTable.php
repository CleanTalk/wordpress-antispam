<?php

namespace Cleantalk\ApbctWP\FindSpam\ListTable;

use Cleantalk\ApbctWP\WcSpamOrdersListTable;
use PHPUnit\Framework\TestCase;

/**
 * Tests for WcSpamOrdersListTable renderOrderDetailsColumn and renderCustomerDetailsColumn methods.
 */
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
        global $apbct;
        
        $reflection = new \ReflectionClass(WcSpamOrdersListTable::class);
        $this->instance = $reflection->newInstanceWithoutConstructor();

        $this->renderOrderDetailsColumn = $reflection->getMethod('renderOrderDetailsColumn');
        $this->renderOrderDetailsColumn->setAccessible(true);

        $this->renderCustomerDetailsColumn = $reflection->getMethod('renderCustomerDetailsColumn');
        $this->renderCustomerDetailsColumn->setAccessible(true);

        // Set both instance property and global variable
        $apbct = (object)[
            'white_label' => true,
        ];
        
        // Use reflection to set the protected property
        $apbctProperty = $reflection->getProperty('apbct');
        $apbctProperty->setAccessible(true);
        $apbctProperty->setValue($this->instance, $apbct);
    }

    /**
     * renderOrderDetailsColumn renders product title and quantity.
     */
    public function testRenderOrderDetailsColumnRendersProductAndQuantity(): void
    {
        $order_details = json_encode([
            [
                'product_id' => 123,
                'quantity'   => 2,
            ],
            [
                'product_id' => 456,
                'quantity'   => 1,
            ],
        ]);

        $result = $this->renderOrderDetailsColumn->invoke($this->instance, $order_details);

        $this->assertStringContainsString('Unavailable product', $result);
        $this->assertStringContainsString('2', $result);
        $this->assertStringContainsString('1', $result);
    }

    /**
     * renderOrderDetailsColumn shows error when JSON is invalid.
     */
    public function testRenderOrderDetailsColumnShowsErrorWhenJsonInvalid(): void
    {
        $order_details = 'invalid json';

        $result = $this->renderOrderDetailsColumn->invoke($this->instance, $order_details);

        $this->assertStringContainsString('Product details decoding error', $result);
    }

    /**
     * renderOrderDetailsColumn shows error when JSON is not an array.
     */
    public function testRenderOrderDetailsColumnShowsErrorWhenNotArray(): void
    {
        $order_details = json_encode('not an array');

        $result = $this->renderOrderDetailsColumn->invoke($this->instance, $order_details);

        $this->assertStringContainsString('Product details decoding error', $result);
    }

    /**
     * renderCustomerDetailsColumn renders customer billing details.
     */
    public function testRenderCustomerDetailsColumnRendersBillingDetails(): void
    {
        $customer_details = json_encode([
            'billing_first_name' => 'John',
            'billing_last_name'  => 'Doe',
            'billing_email'      => 'john.doe@example.com',
        ]);

        $result = $this->renderCustomerDetailsColumn->invoke($this->instance, $customer_details);

        $this->assertStringContainsString('John', $result);
        $this->assertStringContainsString('Doe', $result);
        $this->assertStringContainsString('john.doe@example.com', $result);
    }

    /**
     * renderCustomerDetailsColumn shows error when JSON is invalid.
     */
    public function testRenderCustomerDetailsColumnShowsErrorWhenJsonInvalid(): void
    {
        $customer_details = 'invalid json';

        $result = $this->renderCustomerDetailsColumn->invoke($this->instance, $customer_details);

        $this->assertStringContainsString('Customer details decoding error', $result);
    }

    /**
     * renderCustomerDetailsColumn shows error when JSON is not an array.
     */
    public function testRenderCustomerDetailsColumnShowsErrorWhenNotArray(): void
    {
        $customer_details = json_encode('not an array');

        $result = $this->renderCustomerDetailsColumn->invoke($this->instance, $customer_details);

        $this->assertStringContainsString('Customer details decoding error', $result);
    }

    /**
     * renderCustomerDetailsColumn handles missing billing fields.
     */
    public function testRenderCustomerDetailsColumnHandlesMissingFields(): void
    {
        $customer_details = json_encode([
            'billing_first_name' => 'John',
            // Missing billing_last_name and billing_email
        ]);

        $result = $this->renderCustomerDetailsColumn->invoke($this->instance, $customer_details);

        $this->assertStringContainsString('John', $result);
        // Empty fields should still render (empty strings)
        $this->assertNotEmpty($result);
    }
}

