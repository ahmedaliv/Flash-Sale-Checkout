<?php

namespace App\Http\Controllers;

use App\Jobs\ReleaseExpiredHold;
use App\Models\Hold;
use App\Models\Product;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class HoldController extends Controller
{
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

        // hold logic
        // TODO
        try {
            // first get the product with select for update to lock the row of that product to avoid race conditions
            $hold = DB::transaction(function () use ($productId, $qty) {

                // get product
                $product = Product::lockForUpdate()->find($productId);

                // edge case: no enough stock

                if ($product->stock < $qty) {
                    throw new HttpResponseException(
                        response()->json([
                            'message' => 'Not Enough Stock Available.',
                            'available' => $product->stock,
                        ], 409)
                    );
                }

                // if we're here -> enough stock

                // we decrement the stock with qty
                // we use decrement method to avoid race conditions
                $product->decrement('stock', $qty);
                Cache::put("product_stock_{$product->id}", $product->stock, 10);
                $product->save();

                // create the hold
                return Hold::create([
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'expires_at' => Carbon::now()->addMinutes(2),
                ]);

            });

            // TODO: dispatch job to release after expiry
            $expiresAt = $hold->expires_at;
            $now = now();
            $delay = abs($expiresAt->diffInSeconds($now));

            Log::info('Hold delay debug', [
                'expires_at' => $expiresAt->toDateTimeString(),
                'now' => $now->toDateTimeString(),
                'delay' => $delay,
            ]);

            ReleaseExpiredHold::dispatch($hold->id)->delay($delay);

            // return hold_id and expires_at
            return response()->json([
                'hold_id' => $hold->id,
                'expires_at' => $hold->expires_at,
            ], 201);

        } catch (HttpResponseException $e) {
            Log::warning('Failed to create hold due to stock conflict (insuffcient Stock)', ['product_id' => $productId, 'qty' => $qty]);
            return $e->getResponse();
        } catch (Exception $e) {
            Log::error('Failed to create hold due to system error', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'System error: Could not complete reservation.',
            ], 500);
        }

    }
}
