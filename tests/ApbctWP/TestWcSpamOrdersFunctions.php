<?php

namespace Cleantalk\ApbctWP\Tests;

use Cleantalk\ApbctWP\WcSpamOrdersFunctions;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WcSpamOrdersFunctions::prepareDetailsOrderResponse()
 *
 * @covers \Cleantalk\ApbctWP\WcSpamOrdersFunctions::prepareDetailsOrderResponse
 */
class WcSpamOrdersFunctionsTest extends TestCase
{
    // ------------------------------------------------------------------
    // Helpers / stubs
    // ------------------------------------------------------------------

    private function makeSearchMethod(callable $impl): array
    {
        OrderDataStub::$impl = $impl;
        return [
            'class'  => OrderDataStub::class,
            'method' => 'getOrderDataById',
        ];
    }

    private function makeOrderRow(array $orderDetails = [], array $customerDetails = []): \stdClass
    {
        $row                   = new \stdClass();
        $row->order_details    = json_encode($orderDetails);
        $row->customer_details = json_encode($customerDetails);
        return $row;
    }

    // ------------------------------------------------------------------
    // Tests: invalid / missing order_id
    // ------------------------------------------------------------------

    /**
     * @test
     * @dataProvider invalidOrderIdProvider
     */
    public function prepareDetailsOrderResponse_returnsError_whenOrderIdIsInvalid($invalidId): void
    {
        $result = WcSpamOrdersFunctions::prepareDetailsOrderResponse(
            $invalidId,
            $this->makeSearchMethod(function ($id) { return null; })
        );

        $this->assertNull($result['order_details']);
        $this->assertNull($result['customer_details']);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('not valid', $result['error']);
    }

    public function invalidOrderIdProvider(): array
    {
        return [
            'null id'      => [null],
            'zero int'     => [0],
            'empty string' => [''],
            'false'        => [false],
        ];
    }

    // ------------------------------------------------------------------
    // Tests: invalid search_method
    // ------------------------------------------------------------------

    /** @test */
    public function prepareDetailsOrderResponse_returnsError_whenSearchMethodIsEmptyArray(): void
    {
        $result = WcSpamOrdersFunctions::prepareDetailsOrderResponse(42, []);

        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('undefined', strtolower($result['error']));
    }

    /** @test */
    public function prepareDetailsOrderResponse_returnsError_whenClassDoesNotExist(): void
    {
        $result = WcSpamOrdersFunctions::prepareDetailsOrderResponse(42, [
            'class'  => 'NonExistentClass_XYZ',
            'method' => 'someMethod',
        ]);

        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('undefined', strtolower($result['error']));
    }

    /** @test */
    public function prepareDetailsOrderResponse_returnsError_whenMethodDoesNotExist(): void
    {
        $result = WcSpamOrdersFunctions::prepareDetailsOrderResponse(42, [
            'class'  => OrderDataStub::class,
            'method' => 'nonExistentMethod',
        ]);

        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('undefined', strtolower($result['error']));
    }

    /** @test */
    public function prepareDetailsOrderResponse_returnsError_whenMethodKeyMissing(): void
    {
        $result = WcSpamOrdersFunctions::prepareDetailsOrderResponse(42, [
            'class' => OrderDataStub::class,
            // 'method' intentionally omitted
        ]);

        $this->assertNotNull($result['error']);
    }

    // ------------------------------------------------------------------
    // Tests: order not found
    // ------------------------------------------------------------------

    /** @test */
    public function prepareDetailsOrderResponse_returnsError_whenOrderNotFound(): void
    {
        $searchMethod = $this->makeSearchMethod(function ($id) { return null; });

        $result = WcSpamOrdersFunctions::prepareDetailsOrderResponse(99, $searchMethod);

        $this->assertNull($result['order_details']);
        $this->assertNull($result['customer_details']);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('not found', strtolower($result['error']));
    }

    // ------------------------------------------------------------------
    // Tests: happy path
    // ------------------------------------------------------------------

    /** @test */
    public function prepareDetailsOrderResponse_returnsDecodedData_whenOrderExists(): void
    {
        $orderDetails    = [['product_id' => 5, 'quantity' => 2]];
        $customerDetails = ['billing_email' => 'test@example.com'];

        $row          = $this->makeOrderRow($orderDetails, $customerDetails);
        $searchMethod = $this->makeSearchMethod(function ($id) use ($row) { return $row; });

        $result = WcSpamOrdersFunctions::prepareDetailsOrderResponse(1, $searchMethod);

        $this->assertNull($result['error']);
        $this->assertNotNull($result['order_details']);
        $this->assertNotNull($result['customer_details']);
        $this->assertEquals('test@example.com', $result['customer_details']['billing_email']);
        $this->assertEquals(5, $result['order_details'][0]['product_id']);
    }

    /** @test */
    public function prepareDetailsOrderResponse_passesOrderIdToSearchMethod(): void
    {
        $capturedId   = null;
        $self         = $this;
        $searchMethod = $this->makeSearchMethod(function ($id) use (&$capturedId, $self) {
            $capturedId = $id;
            return $self->makeOrderRowPublic();
        });

        WcSpamOrdersFunctions::prepareDetailsOrderResponse(777, $searchMethod);

        $this->assertSame(777, $capturedId);
    }

    // makeOrderRow must be public to use it inside the closure above
    public function makeOrderRowPublic(array $orderDetails = [], array $customerDetails = []): \stdClass
    {
        return $this->makeOrderRow($orderDetails, $customerDetails);
    }

    // ------------------------------------------------------------------
    // Tests: response structure
    // ------------------------------------------------------------------

    /** @test */
    public function prepareDetailsOrderResponse_alwaysReturnsAllThreeKeys(): void
    {
        $searchMethod = $this->makeSearchMethod(function ($id) { return null; });

        $result = WcSpamOrdersFunctions::prepareDetailsOrderResponse(null, $searchMethod);

        $this->assertArrayHasKey('order_details', $result);
        $this->assertArrayHasKey('customer_details', $result);
        $this->assertArrayHasKey('error', $result);
    }

    /** @test */
    public function prepareDetailsOrderResponse_nullifiesDetails_onError(): void
    {
        $searchMethod = $this->makeSearchMethod(function ($id) { return null; });

        $result = WcSpamOrdersFunctions::prepareDetailsOrderResponse(10, $searchMethod);

        $this->assertNull($result['order_details']);
        $this->assertNull($result['customer_details']);
    }

    // ------------------------------------------------------------------
    // Tests: edge cases with JSON data
    // ------------------------------------------------------------------

    /** @test */
    public function prepareDetailsOrderResponse_handlesEmptyJsonArrays(): void
    {
        $row          = $this->makeOrderRow([], []);
        $searchMethod = $this->makeSearchMethod(function ($id) use ($row) { return $row; });

        $result = WcSpamOrdersFunctions::prepareDetailsOrderResponse(1, $searchMethod);

        $this->assertNull($result['error']);
        $this->assertIsArray($result['order_details']);
        $this->assertEmpty($result['order_details']);
    }

    /** @test */
    public function prepareDetailsOrderResponse_handlesMultipleProducts(): void
    {
        $orderDetails = [
            ['product_id' => 1, 'quantity' => 3],
            ['product_id' => 2, 'quantity' => 1],
        ];
        $row          = $this->makeOrderRow($orderDetails, []);
        $searchMethod = $this->makeSearchMethod(function ($id) use ($row) { return $row; });

        $result = WcSpamOrdersFunctions::prepareDetailsOrderResponse(1, $searchMethod);

        $this->assertNull($result['error']);
        $this->assertCount(2, $result['order_details']);
    }

    /** @test */
    public function prepareDetailsOrderResponse_errorIsString_onAnyError(): void
    {
        $searchMethod = $this->makeSearchMethod(function ($id) { return null; });

        $result = WcSpamOrdersFunctions::prepareDetailsOrderResponse(5, $searchMethod);

        $this->assertIsString($result['error']);
        $this->assertGreaterThan(0, strlen($result['error']));
    }
}

// ---------------------------------------------------------------------------
// Named stub — declared at file scope so class_exists() / method_exists()
// can see it during checks inside prepareDetailsOrderResponse().
// ---------------------------------------------------------------------------

class OrderDataStub
{
    /** @var callable|null */
    public static $impl = null;

    public static function getOrderDataById(int $id)
    {
        return (self::$impl)($id);
    }
}
