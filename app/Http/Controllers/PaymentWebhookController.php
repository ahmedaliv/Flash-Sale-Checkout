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

    /**
     * @OA\Post(
     *     path="/api/v1/payments/webhook",
     *     summary="Handle payment gateway webhook",
     *     tags={"Payments"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"order_id","status","idempotency_key"},
     *
     *             @OA\Property(property="order_id", type="integer", example=1),
     *             @OA\Property(property="status", type="string", enum={"success","failure"}, example="success"),
     *             @OA\Property(property="idempotency_key", type="string", example="abc123xyz")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Webhook successfully processed",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Webhook successfully processed.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Client error (invalid request)",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Validation failed")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Webhook processing failed.")
     *         )
     *     )
     * )
     */
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
                'key' => $key,
            ]);

            return response()->json(['message' => 'Webhook processing failed.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
