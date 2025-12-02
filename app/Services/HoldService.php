<?php

namespace App\Services;

use App\Jobs\ReleaseExpiredHold;
use App\Models\Hold;
use App\Models\Product;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class HoldService
{
    // creates a temporary hold for a product
    public function createHold(int $productId, int $qty): Hold
    {
        try {
            $hold = DB::transaction(function () use ($productId, $qty) {
                $product = Product::lockForUpdate()->find($productId);

                if ($product->stock < $qty) {
                    Log::warning('Not enough stock available for hold', ['product_id' => $productId, 'requested_qty' => $qty, 'available_stock' => $product->stock]);
                    throw new HttpResponseException(
                        response()->json([
                            'message' => 'Not Enough Stock Available.',
                            'available' => $product->stock,
                        ], 409)
                    );
                }

                $product->decrement('stock', $qty);
                Cache::put("product_stock_{$product->id}", $product->stock, 10);
                $product->save();
                Log::info('Product stock decremented for hold', ['product_id' => $productId, 'decremented_qty' => $qty, 'new_stock' => $product->stock]);

                return Hold::create([
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'expires_at' => Carbon::now()->addMinutes(2),
                ]);
            });
            Log::info('Hold created successfully', ['hold_id' => $hold->id, 'product_id' => $productId, 'qty' => $qty]);

            $expiresAt = $hold->expires_at;
            $now = now();
            $delay = abs($expiresAt->diffInSeconds($now));

            Log::info('Hold delay debug', [
                'expires_at' => $expiresAt->toDateTimeString(),
                'now' => $now->toDateTimeString(),
                'delay' => $delay,
            ]);

            ReleaseExpiredHold::dispatch($hold->id)->delay($delay);

            return $hold;

        } catch (HttpResponseException $e) {
            Log::warning('Failed to create hold due to stock conflict', ['product_id' => $productId, 'qty' => $qty]);
            throw $e;
        } catch (Exception $e) {
            Log::error('Failed to create hold due to system error', ['error' => $e->getMessage()]);
            throw new Exception('System error: Could not complete reservation.');
        }
    }
}
