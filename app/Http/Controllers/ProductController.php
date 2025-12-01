<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function show($id)
    {
        $product = Cache::remember("product_info_{$id}", 60, function () use ($id) {
            return Product::select('id', 'name', 'price')->findOrFail($id);
        });

        $availableStock = Cache::remember("product_stock_{$id}", 10, function () use ($id) {
            $productStock = Product::findOrFail($id)->stock;

            return $productStock;
        });

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'stock' => $availableStock,
        ]);
    }
}
