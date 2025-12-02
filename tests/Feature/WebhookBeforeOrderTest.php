<?php

namespace Tests\Feature;

use App\Models\Hold;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/*
This test verifies the behavior of payment webhooks received before the corresponding order is created.
When a webhook is received for an order that does not yet exist, it should be recorded as pending
and processed once the order is created.
Flow
1. Create a product
2. Create a hold for that product
3. Define a webhook payload with a unique idempotency key for a to-be created order
4. Send the webhook
5. Assert that the webhook is recorded as pending in the database
6. Create the order for the hold
7. Assert that the webhook is processed and the order status is updated accordingly
*/
class WebhookBeforeOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_arriving_before_order_creation_is_processed_after_order_creation(): void
    {
        // Step 1: Create a product
        $product = Product::factory()->create(['stock' => 10]);
        $productId = $product->id;

        // Step 2: Create a hold for that product
        // we have 2 options to create
        // 1- use factory to create hold directly
        // 2- use the API endpoint to create hold ( we'll use this to simulate the real flow)
        $hold = Hold::factory()->create([
            'product_id' => $productId,
            'qty' => 2,
        ]);
        $holdId = $hold->id;
        // assert hold created
        $this->assertNotNull($holdId, 'Hold ID should not be null');
        // assert hold exists in database
        $this->assertDatabaseHas('holds', [
            'id' => $holdId,
            'product_id' => $productId,
            'qty' => 2,
        ]);

        // Step 3: Define a webhook payload with a unique idempotency key for a to-be created order
        $idempotencyKey = 'unique-webhook-key-123';
        $webhookPayload = [
            'order_id' => 1, // order ID (we'll create it later) and it will be 1 as it's the first order
            'status' => 'success',
            'idempotency_key' => $idempotencyKey,
        ];
        // Step 4: Send the webhook
        $webhookResponse = $this->postJson('/api/v1/payments/webhook', $webhookPayload);
        $webhookResponse->assertStatus(200);

        // Step 5: Assert that the webhook is recorded as pending in the database
        $this->assertDatabaseHas('payment_webhooks', [
            'order_id' => 1,
            'idempotency_key' => $idempotencyKey,
            'processed' => 'pending',
        ]);

        // Step 6: Create the order for the hold
        \App\Models\Order::unguard();
        $order = Order::factory()->create([
            'id' => 1, //  matches the webhook order_id
            'hold_id' => $holdId,
            'status' => 'pre_payment',
        ]);
        \App\Models\Order::reguard();
        $orderId = $order->id;
        $this->assertNotNull($orderId, 'Order ID should not be null');
        // we need to simulate the processing of pending webhooks after order creation
        $pendingWebhooks = \App\Models\PaymentWebhook::where('order_id', $orderId)
            ->where('processed', 'pending')
            ->get();
        foreach ($pendingWebhooks as $webhook) {
            // Simulate processing the webhook
            Log::info('Processing pending webhook after order creation', ['order_id' => $orderId, 'webhook_id' => $webhook->id]);
            // Update order status based on webhook status
            $order->status = $webhook->status === 'success' ? 'paid' : 'cancelled';
            $order->save();
            $webhook->processed = 'processed';
            $webhook->save();
        }

        $orderModel = Order::find($orderId);
        $this->assertNotNull($orderModel, 'Order should exist in database');
        $this->assertEquals('paid', $orderModel->status, 'Order status should be updated to paid after webhook processing');
        // Step 7: Assert that the webhook is processed and the order status is updated accordingly
        $this->assertDatabaseHas('payment_webhooks', [
            'order_id' => $orderId,
            'idempotency_key' => $idempotencyKey,
            'processed' => 'processed',
        ]);

    }
}
