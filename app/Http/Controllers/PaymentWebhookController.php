<?php

namespace App\Http\Controllers;

use App\Services\PaymentWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaymentWebhookController extends Controller
{
    protected PaymentWebhookService $service;

    public function __construct(PaymentWebhookService $service)
    {
        $this->service = $service;
    }

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
            $this->service->handleWebhook($orderId, $status, $key);

            return response()->json(['message' => 'Webhook successfully processed.'], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed.', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'key' => $key
            ]);

            return response()->json(['message' => 'Webhook processing failed.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
