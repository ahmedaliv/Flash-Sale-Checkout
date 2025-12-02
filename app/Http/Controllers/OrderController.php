<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * @OA\Post(
     *     path="/api/orders",
     *     summary="Create an order from a hold",
     *     tags={"Orders"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"hold_id"},
     *
     *             @OA\Property(property="hold_id", type="integer", example=1)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Order created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="order_id", type="integer", example=1),
     *             @OA\Property(property="status", type="string", example="pre_payment")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Client-side error: invalid or expired hold",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Hold Expired")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server-side error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Internal Server Error: DB connection failed")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'hold_id' => 'required|exists:holds,id',
        ]);

        $holdId = $request->hold_id;

        try {
            $order = $this->orderService->createOrderFromHold($holdId);

            return response()->json([
                'order_id' => $order->id,
                'status' => $order->status,
            ], 201);

        } catch (\Exception $e) {
            $message = $e->getMessage();
            // client errors
            if (in_array($message, ['Hold Not Found', 'Hold Already Used', 'Hold Expired'])) {
                return response()->json([
                    'message' => $message,
                ], 400);
            }

            // server errors
            return response()->json([
                'message' => 'Internal Server Error: '.$message,
            ], 500);
        }
    }
}
