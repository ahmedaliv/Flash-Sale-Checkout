<?php

namespace App\Http\Controllers;

use App\Models\Hold;
use App\Models\Order;
use App\Models\PaymentWebhook;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // gets hold_id -success-> creates an order in a pre_payment state.
        // considerations [only valid,take care of expiery,each hold can be used once]
        // validate the request
        $request->validate([
            'hold_id' => 'required|exists:holds,id',
        ]);

        $holdId = $request->hold_id;
        // log
        Log::info('Creating order for hold', ['hold_id' => $holdId]);

        try {
            $order = DB::transaction(function () use ($holdId) {
                // we gonna get the relevant hold, lock it to prevent race conditions
                $hold = Hold::lockForUpdate()->find($holdId);

                // make sure it exists
                if (! $hold) {
                    Log::error('Hold not found', ['hold_id' => $holdId]);
                    throw new Exception('Hold Not Found');
                }
                // make sure it was not used
                if ($hold->used) {
                    Log::error('Hold already used', ['hold_id' => $holdId]);
                    throw new Exception('Hold Already Used');
                }

                // make sure it has not expired yet.
                if ($hold->expires_at < Carbon::now()) {
                    Log::error('Hold expired', ['hold_id' => $holdId]);
                    throw new Exception('Hold Expired');
                }
                $hold->used = true;
                $hold->save();
                Log::info('Hold used successfully', ['hold_id' => $holdId]);

                return Order::create([
                    'hold_id' => $hold->id,
                    'status' => 'pre_payment',
                ]);
            });
            // After creating the order, process any early webhooks
            Log::info('Order created successfully', ['order_id' => $order->id]);
            // After order creation
            $pendingWebhooks = PaymentWebhook::where('order_id', $order->id)
                ->where('processed', 'pending')
                ->get();

            $webhookController = new PaymentWebhookController;

            foreach ($pendingWebhooks as $webhook) {
                $webhookController->processWebhook($order, $webhook);
                Log::info('Processed pending webhook', [
                    'order_id' => $order->id,
                    'webhook_id' => $webhook->id,
                ]);
            }
            Log::info('All pending webhooks processed for order', ['order_id' => $order->id]);

            return response()->json([
                'order_id' => $order->id,
                'status' => $order->status,
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }

    }
}
