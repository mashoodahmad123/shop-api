<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('sku')) {
            $this->merge(['sku' => strtoupper(trim((string) $this->sku))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', 'unique:products,sku'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'status' => ['sometimes', new Enum(ProductStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.regex' => 'SKU may only contain letters, numbers, dashes and underscores.',
            'sku.unique' => 'This SKU is already used by another product.',
        ];
    }
}
