<?php

use App\Http\Controllers\HoldController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// get the product by id
Route::get('/products/{id}', [ProductController::class, 'show']);

// create a new hold
Route::post('/holds', [HoldController::class, 'store']);

// payment gateway webhook endpoint
Route::post('/payments/webhook', [PaymentWebhookController::class, 'handle']);
