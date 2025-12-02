<?php

namespace App\Http\Controllers;

use App\Services\ProductService;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function show($id)
    {
        $productData = $this->productService->getProductWithStock((int) $id);
        return response()->json($productData);
    }
}
