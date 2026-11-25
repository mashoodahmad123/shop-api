<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateProductRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('products', 'sku')->ignore($this->route('product')),
            ],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'stock_quantity' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'status' => ['sometimes', 'required', new Enum(ProductStatus::class)],
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
