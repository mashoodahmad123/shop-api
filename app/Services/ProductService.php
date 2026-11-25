<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    public function listForCustomer(array $filters): LengthAwarePaginator
    {
        return Product::query()
            ->active()
            ->search($filters['search'] ?? null)
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function listForAdmin(array $filters): LengthAwarePaginator
    {
        $query = Product::query();

        if (($filters['trashed'] ?? null) === 'with') {
            $query->withTrashed();
        } elseif (($filters['trashed'] ?? null) === 'only') {
            $query->onlyTrashed();
        }

        return $query
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->search($filters['search'] ?? null)
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function showForCustomer(Product $product): Product
    {
        if (!$product->isActive()) {
            throw new BusinessException('Product not found.', 404);
        }

        return $product;
    }

    public function create(array $data): Product
    {
        return Product::create($data)->refresh();
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->refresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function restore(Product $product): Product
    {
        if (!$product->trashed()) {
            throw new BusinessException('Product is not deleted.', 422);
        }

        $product->restore();

        return $product->refresh();
    }
}
