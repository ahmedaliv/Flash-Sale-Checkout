<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;

// get the product by id
Route::get('/products/{id}', [ProductController::class, 'show']);
