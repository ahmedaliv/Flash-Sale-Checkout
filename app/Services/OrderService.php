<?php

namespace App\Services;

use App\Models\Hold;
use App\Models\Order;
use App\Models\PaymentWebhook;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    /**
     * Create an order from a hold
     *
     * @param int $holdId
     * @return Order
     * @throws Exception
     */
    public function createOrderFromHold(int $holdId): Order
    {
        Log::info('Creating order for hold', ['hold_id' => $holdId]);

        return DB::transaction(function () use ($holdId) {
            $hold = Hold::lockForUpdate()->find($holdId);

            if (! $hold) {
                Log::error('Hold not found', ['hold_id' => $holdId]);
                throw new Exception('Hold Not Found');
            }

            if ($hold->used) {
                Log::error('Hold already used', ['hold_id' => $holdId]);
                throw new Exception('Hold Already Used');
            }

            if ($hold->expires_at < Carbon::now()) {
                Log::error('Hold expired', ['hold_id' => $holdId]);
                throw new Exception('Hold Expired');
            }

            $hold->used = true;
            $hold->save();

            Log::info('Hold used successfully', ['hold_id' => $holdId]);

            $order = Order::create([
                'hold_id' => $hold->id,
                'status' => 'pre_payment',
            ]);

            // process any pending webhooks
            $pendingWebhooks = PaymentWebhook::where('order_id', $order->id)
                ->where('processed', 'pending')
                ->get();

            $webhookController = new \App\Services\PaymentWebhookService;

            foreach ($pendingWebhooks as $webhook) {
                $webhookController->processWebhook($order, $webhook);
                Log::info('Processed pending webhook', [
                    'order_id' => $order->id,
                    'webhook_id' => $webhook->id,
                ]);
            }

            Log::info('All pending webhooks processed for order', ['order_id' => $order->id]);

            return $order;
        });
    }
}
