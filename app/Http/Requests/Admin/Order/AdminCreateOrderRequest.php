<?php

namespace App\Http\Requests\Admin\Order;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdminCreateOrderRequest extends FormRequest
{
    public function authorize(): true
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge($this->request->all(), $this->route()->parameters());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id'             => ['required', 'integer', 'exists:users,id'],
            'status'              => ['required', 'string', Rule::enum(OrderStatus::class)],
            'total_price'         => ['required', 'numeric', 'min:0'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.product_id'  => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'    => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
        ];
    }
}

