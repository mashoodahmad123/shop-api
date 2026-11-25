<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ListProductsRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use App\Traits\ApiResponse;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(protected ProductService $productService)
    {
    }

    public function index(ListProductsRequest $request)
    {
        $products = $this->productService->listForCustomer($request->validated());

        return $this->success(
            ProductResource::collection($products)->response()->getData(true)
        );
    }

    public function show(Product $product)
    {
        $product = $this->productService->showForCustomer($product);

        return $this->success(new ProductResource($product));
    }
}
