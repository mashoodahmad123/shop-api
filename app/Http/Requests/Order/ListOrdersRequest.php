<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Order::class);
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(OrderStatus::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
