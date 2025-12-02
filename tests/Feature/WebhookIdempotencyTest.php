<?php

namespace Tests\Feature;

use App\Models\Hold;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
/*
This test verifies the idempotency of payment webhooks.
When the same webhook is sent multiple times with the same idempotency key,
it should only be processed once, preventing duplicate order updates or records.
Flow
1. create a product
2. create a hold for that product
3. create an order for that hold
4. define a webhook payload with a unique idempotency key
5. send the webhook first time
6. assert database changes (order status updated, webhook recorded)
7. send the same webhook second time
8. assert database did not duplicate changes
*/
class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_idempotency_prevents_double_processing(): void
    {
        // Step 1: Create a product
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'price' => 1000,
            'stock' => 10,
        ]);
        $productId = $product->id;

        // Step 2: Create a hold for that product
        // we have 2 options to create
        // 1- use factory to create hold directly
        // 2- use the API endpoint to create hold ( we'll use this to simulate the real flow)
        $holdResponse = $this->postJson('/api/v1/holds', [
            'product_id' => $productId,
            'qty' => 2,
        ]);
        $holdResponse->assertStatus(201);
        $holdId = $holdResponse->json('hold_id');
        $this->assertNotNull($holdId, 'Hold ID should not be null');
        $hold = Hold::find($holdId);
        $this->assertNotNull($hold, 'Hold should exist in database');

        // Step 3: Create an order for that hold
        $order = $this->postJson('/api/v1/orders', [
            'hold_id' => $holdId,
        ]);
        $order->assertStatus(201);
        $orderId = $order->json('order_id');
        $this->assertNotNull($orderId, 'Order ID should not be null');
        $orderModel = Order::find($orderId);
        $this->assertNotNull($orderModel, 'Order should exist in database');
        $this->assertEquals('pre_payment', $orderModel->status, 'Order status should be pre_payment');

        // Step 4: Define a webhook payload with a unique idempotency key
        $idempotencyKey = 'unique-webhook-key-12345';
        $webhookPayload = [
            'order_id' => $orderId,
            'status' => 'success',
            'idempotency_key' => $idempotencyKey,
        ];
        // Step 5: Send the webhook first time
        $firstResponse = $this->postJson('/api/v1/payments/webhook', $webhookPayload);
        $firstResponse->assertStatus(200);
        // Step 6: Assert database changes (order status updated, webhook recorded)
        $orderModel->refresh();
        $this->assertEquals('paid', $orderModel->status, 'Order status should be updated to paid after first webhook');

        $this->assertDatabaseHas('payment_webhooks', [
            'order_id' => $orderId,
            'idempotency_key' => $idempotencyKey,
            'processed' => 'processed',
        ]);

        // Step 7: Send the same webhook second time
        $secondResponse = $this->postJson('/api/v1/payments/webhook', $webhookPayload);
        $secondResponse->assertStatus(200);

        // Step 8: Assert database did not duplicate changes
        $orderModel->refresh();
        $this->assertEquals('paid', $orderModel->status, 'Order status should remain paid after second webhook');
        $webhookCount = \App\Models\PaymentWebhook::where('order_id', $orderId)
            ->where('idempotency_key', $idempotencyKey)
            ->count();
        $this->assertEquals(1, $webhookCount, 'There should only be one record of the webhook in the database');

    }
}
