<?php

namespace App\Jobs;

use App\Models\Hold;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReleaseExpiredHold implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $holdId;

    public function __construct($holdId)
    {
        $this->holdId = $holdId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $holdId = $this->holdId;
        try {
            DB::transaction(function () use ($holdId) {
                $hold = Hold::where('id', $holdId)->lockForUpdate()->first();

                if (! $hold) {
                    Log::info('Hold not found', ['hold_id' => $holdId]);

                    return;
                }
                if ($hold->used) {
                    Log::info('Hold already used', ['hold_id' => $holdId]);

                    return;
                }
                if ($hold->expires_at > Carbon::now()) {
                    Log::info('Hold not expired yet', [
                        'hold_id' => $holdId,
                        'expires_at' => $hold->expires_at,
                        'now' => Carbon::now(),
                    ]);

                    return;
                }

                Log::info('Releasing expired hold', [
                    'hold_id' => $hold->id,
                    'product_id' => $hold->product_id,
                    'qty' => $hold->qty,
                ]);

                $hold->product()->increment('stock', $hold->qty);
                Cache::put("product_stock_{$hold->product_id}", $hold->product->stock, 10);

                $hold->delete();
            });

        } catch (Throwable $e) {
            Log::error('ReleaseExpiredHold failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
