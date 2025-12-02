<?php

namespace App\Http\Controllers;

use App\Models\Hold;
use App\Models\Product;
use App\Services\HoldService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class HoldController extends Controller
{
    protected HoldService $holdService;

    public function __construct(HoldService $holdService)
    {
        $this->holdService = $holdService;
    }

    /**
     * @OA\Post(
     *     path="/api/holds",
     *     summary="Create a temporary hold for a product",
     *     tags={"Holds"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"product_id","qty"},
     *
     *             @OA\Property(property="product_id", type="integer", example=1),
     *             @OA\Property(property="qty", type="integer", example=2)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Hold created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="hold_id", type="integer", example=1),
     *             @OA\Property(property="expires_at", type="string", format="date-time", example="2025-12-02T16:20:00Z")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Not enough stock available",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Not Enough Stock Available."),
     *             @OA\Property(property="available", type="integer", example=5)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="System error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        // it gets product_id and quantity
        // returns hold_id and expires_at

        // let's put the logic first (like what do we need to do to hold a product)
        // this should create a temp. reservation for the product (~ 2 mins)
        // if successful return hold_id and expiers_at
        // this hold immediately reduces availability for others
        // expirey should immediately auto-release the hold (the availability is increased again) not only on read
        // let's go
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
        ]);

        $productId = $request->product_id;
        $qty = $request->qty;

        try {
            $hold = $this->holdService->createHold($productId, $qty);

            return response()->json([
                'hold_id' => $hold->id,
                'expires_at' => $hold->expires_at,
            ], 201);

        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

    }
}
