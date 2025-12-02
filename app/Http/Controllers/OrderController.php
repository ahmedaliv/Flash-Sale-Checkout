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
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
