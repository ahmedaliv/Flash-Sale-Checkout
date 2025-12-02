<?php

namespace Tests\Feature;

use App\Models\Product;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/*
this test simulates multiple parallel HTTP requests to hold product stock, ensuring that overselling does not occur.
It does so by (we need a new process to run a temporary Laravel server because  php/laravel is single-threaded and cannot handle parallel requests natively in tests):
flow
1- setup DB.
2- make a product with limited stock.
3- start a temporary Laravel server in a separate process.
4- prepare multiple concurrent POST requests to hold stock.
5- execute all requests in parallel using Guzzle's Pool.
6- stop the temporary server.
7- analyze results to ensure no overselling occurred.
*/

class HoldProductionSimulationTest extends TestCase
{
    public function test_parallel_http_requests_do_not_oversell()
    {

        // setup the database
        // we can't use useRefreshDatabase because it wraps each test in a transaction and the separate process won't see the data.
        
        Artisan::call('migrate:fresh');

        // Create a product with stock = 5
        $product = Product::factory()->create(['stock' => 5]);
        $productId = $product->id;

        // running a temporary Laravel server in a separate process to send the requests to it.
        $host = '127.0.0.1';
        $port = 8001;

        $process = new Process(['php', 'artisan', 'serve', "--host={$host}", "--port={$port}"], base_path());
        $process->start();

        // setting up Guzzle client to send requests to the temporary server
        $client = new Client(['base_uri' => "http://{$host}:{$port}"]);

        // making sure that the server is ready
        $ready = false;
        $timeout = 15;
        $start = time();
        while (time() - $start < $timeout) {
            try {
                $client->get("/api/products/{$productId}");
                // print the response status
                // echo "Server response status: " . $res->getStatusCode() . "\n";
                $ready = true;
                break;
            } catch (\Throwable $e) {
                usleep(200_000); 
            }
        }

        if (! $ready) {
            $process->stop();
            $this->fail('Could not start Laravel serve.');
        }

        // preparing 10 requests to hold stock concurrently
        $requests = [];
        for ($i = 0; $i < 10; $i++) {
            $requests[] = new Request(
                'POST',
                '/api/holds',
                ['Content-Type' => 'application/json'],
                json_encode(['product_id' => $productId, 'qty' => 1])
            );
        }

        // sending the requests in parallel using Pool
        $results = [];
        $pool = new Pool($client, $requests, [
            'concurrency' => 10,
            'fulfilled' => function ($response, $index) use (&$results) {
                $results[$index] = $response->getStatusCode();
            },
            'rejected' => function ($reason, $index) use (&$results) {
                if (is_object($reason) && method_exists($reason, 'getResponse') && $reason->getResponse()) {
                    $results[$index] = $reason->getResponse()->getStatusCode();
                } else {
                    $results[$index] = 0;
                }
            },
        ]);

        // wait for all requests to complete like Promise.all in JS

        $pool->promise()->wait();

        // stop the server
        $process->stop(1);

        $successCount = 0;
        $conflictCount = 0;

        foreach ($results as $statusCode) {
            if ($statusCode === 201) {
                $successCount++;
            } elseif ($statusCode === 409) {
                $conflictCount++;
            }
        }

        // Check that the successful holds are exactly 5 like the stock
        $this->assertEquals(5, $successCount, "Expected 5 successful holds (HTTP 201). Got: {$successCount}");

        $this->assertEquals(5, $conflictCount, "Expected 5 conflicts (HTTP 409). Got: {$conflictCount}");

        // the product stock must be 0 now
        $fresh = Product::find($productId);
        $this->assertEquals(0, $fresh->stock, 'Stock should be 0 after holds.');
    }
}
