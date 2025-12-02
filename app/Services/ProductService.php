<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductService
{
    public function getProductWithStock(int $id): array
    {
        $product = Cache::remember("product_info_{$id}", 60, function () use ($id) {
            return Product::select('id', 'name', 'price')->findOrFail($id);
        });

        $availableStock = Cache::remember("product_stock_{$id}", 10, function () use ($id) {
            return Product::findOrFail($id)->stock;
        });

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'stock' => $availableStock,
        ];
    }
}
