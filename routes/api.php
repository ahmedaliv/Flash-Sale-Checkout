<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\HoldController;

// get the product by id
Route::get('/products/{id}', [ProductController::class, 'show']);

// create a new hold
Route::post('/holds', [HoldController::class, 'store']);