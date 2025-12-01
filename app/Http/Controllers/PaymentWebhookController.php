<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer',
            'status' => 'required|in:success,failure',
            'idempotency_key' => 'required|string',
        ]);

        $orderId = $request->order_id;
        $status = $request->status;
        $key = $request->idempotency_key;

        try {
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

                    return response()->json(['message' => 'Webhook already processed'], 200);
                }

                // Try to find order
                $order = Order::find($orderId);

                if ($order) {
                    // normal behavior (order exists)
                    $this->processWebhook($order, $webhook);
                } else {
                    // order was not found, so we leave the webhook as pending (to be processed after the order is created)
                    Log::info('Webhook pending, order not found yet', compact('orderId', 'key'));
                }
            });

            // Success
            return response()->json(['message' => 'Webhook successfully processed.'], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed.', ['error' => $e->getMessage(), 'order_id' => $orderId, 'key' => $key]);

            return response()->json(['message' => 'Webhook processing failed.'], Response::HTTP_BAD_REQUEST);
        }
    }

    public function processWebhook(Order $order, PaymentWebhook $webhook)
    {
        // we're using transaction to ensure atomicity and avoid race conditions
        DB::transaction(function () use ($order, $webhook) {
            $webhook = PaymentWebhook::lockForUpdate()->find($webhook->id);

            if ($webhook->processed === 'processed') {
                Log::info('Webhook already processed', ['webhook_id' => $webhook->id, 'order_id' => $order->id]);

                return;
            }

            if ($order->status === 'pre_payment') {
                if ($webhook->status === 'success') {
                    $order->status = 'paid';
                } else { // failure
                    $order->status = 'cancelled';
                    // release the hold and put the stock back
                    if ($order->hold) {
                        DB::table('products')
                            ->where('id', $order->hold->product_id)
                            ->increment('stock_total', $order->hold->qty);
                        // update cache
                        $currentStock = DB::table('products')->where('id', $order->hold->product_id)->value('stock_total');
                        Cache::put("product_stock_{$order->hold->product_id}", $currentStock, 10);

                    }
                }
                $order->save();
            } else {
                Log::info('Order already processed... skipping', ['order_id' => $order->id]);
            }

            // mark webhook as processed
            $webhook->processed = 'processed';
            $webhook->processed_at = now();
            $webhook->save();

            Log::info('Webhook processed successfully', ['webhook_id' => $webhook->id, 'order_id' => $order->id]);
        });
    }
}
