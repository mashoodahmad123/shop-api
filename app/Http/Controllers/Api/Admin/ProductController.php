<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\AdminListProductsRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
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

    public function index(AdminListProductsRequest $request)
    {
        $products = $this->productService->listForAdmin($request->validated());

        return $this->success(
            ProductResource::collection($products)->response()->getData(true)
        );
    }

    public function store(StoreProductRequest $request)
    {
        $product = $this->productService->create($request->validated());

        return $this->success(new ProductResource($product), 'Product created successfully', 201);
    }

    public function show(Product $product)
    {
        return $this->success(new ProductResource($product));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product = $this->productService->update($product, $request->validated());

        return $this->success(new ProductResource($product), 'Product updated successfully');
    }

    public function destroy(Product $product)
    {
        $this->productService->delete($product);

        return $this->success(null, 'Product deleted successfully');
    }

    public function restore(Product $product)
    {
        $product = $this->productService->restore($product);

        return $this->success(new ProductResource($product), 'Product restored successfully');
    }
}
