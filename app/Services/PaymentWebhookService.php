<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentWebhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaymentWebhookService
{
    public function handleWebhook(int $orderId, string $status, string $key): void
    {
        DB::transaction(function () use ($orderId, $status, $key) {
            $webhook = PaymentWebhook::firstOrCreate(
                ['idempotency_key' => $key],
                [
                    'order_id' => $orderId,
                    'status' => $status,
                    'processed' => 'pending',
                ]
            );

            if ($webhook->processed === 'processed') {
                Log::info('Duplicate webhook ignored', compact('orderId', 'key'));
                return;
            }

            $order = Order::find($orderId);

            if ($order) {
                $this->processWebhook($order, $webhook);
            } else {
                Log::info('Webhook pending, order not found yet', compact('orderId', 'key'));
            }
        });
    }

    public function processWebhook(Order $order, PaymentWebhook $webhook): void
    {
        DB::transaction(function () use ($order, $webhook) {
            $webhook = PaymentWebhook::lockForUpdate()->find($webhook->id);

            if ($webhook->processed === 'processed') {
                Log::info('Webhook already processed', ['webhook_id' => $webhook->id, 'order_id' => $order->id]);
                return;
            }

            if ($order->status === 'pre_payment') {
                if ($webhook->status === 'success') {
                    $order->status = 'paid';
                } else {
                    $order->status = 'cancelled';
                    if ($order->hold) {
                        DB::table('products')
                            ->where('id', $order->hold->product_id)
                            ->increment('stock_total', $order->hold->qty);

                        $currentStock = DB::table('products')
                            ->where('id', $order->hold->product_id)
                            ->value('stock_total');

                        Cache::put("product_stock_{$order->hold->product_id}", $currentStock, 10);
                    }
                }
                $order->save();
            } else {
                Log::info('Order already processed... skipping', ['order_id' => $order->id]);
            }

            $webhook->processed = 'processed';
            $webhook->processed_at = now();
            $webhook->save();

            Log::info('Webhook processed successfully', ['webhook_id' => $webhook->id, 'order_id' => $order->id]);
        });
    }
}
