<?php

namespace Tests\Feature;

use App\Jobs\ReleaseExpiredHold;
use App\Models\Hold;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HoldExpirationTest extends TestCase
{
    // to reset DB after each test 
    use RefreshDatabase;

    public function test_hold_expiry_returns_availability_and_marks_hold_as_used(): void
    {
        // setup the env

        // this is to prevent jobs from actually being processed, just to assert that the job was dispatched 
        Queue::fake();
        // Create product with limited qty and create a hold on it
        $initialStock = 10;

        $holdQty = 3;

        // create product with initial stock
        $product = Product::factory()->create([
            'stock' => $initialStock,
        ]);

        // time now
        $now = Carbon::now();
        // to control time during the test (freeze time)
        Carbon::setTestNow($now);

        // do the hold
        // make http request to create hold 
        $response = $this->postJson('/api/v1/holds', [
            'product_id' => $product->id,
            'qty' => $holdQty,
        ]);

        // assert hold created successfully
        $response->assertStatus(201, 'Hold creation should return HTTP 201');
        $response->assertJsonStructure(['hold_id', 'expires_at']);

        // get the hold id
        $holdId = $response->json('hold_id');

        $hold = Hold::find($holdId);
        // assert hold exists
        $this->assertNotNull($hold, 'Hold should exist in database after creation');

        //  asser first that product stock is reduced

        $this->assertEquals(
            $initialStock - $holdQty,
            Product::find($product->id)->stock,
            'Product stock should decrease by hold quantity immediately after hold'
        );

        // assert that the expiration job was dispatched to the queue correctly

        Queue::assertPushed(ReleaseExpiredHold::class, function ($job) use ($holdId) {
            return $job->holdId === $holdId;
        }, 'ReleaseExpiredHold job should be dispatched when hold is created');

        // simulate time passing to after hold expiration
        $expirationTime = Carbon::parse($hold->expires_at);
        Carbon::setTestNow($expirationTime->addSeconds(60));

        // now process the job to release the hold
        // simulate running the job manually because we are faking the queue so it won't run automatically
        $job = new ReleaseExpiredHold($holdId);
        $job->handle();

        $productAfter = Product::find($product->id);
        $holdAfter = Hold::find($holdId);
        // assert that hold is deleted
        $this->assertNull($holdAfter, 'Hold must be deleted after expiration');

        // assert that product stock is restored
        $this->assertEquals(
            $initialStock,
            $productAfter->stock,
            'Product stock must be restored to initial value after hold expiration'
        );
        
        // reset time
        Carbon::setTestNow();

    }
}
