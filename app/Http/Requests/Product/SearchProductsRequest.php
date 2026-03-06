<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SearchProductsRequest extends FormRequest
{
    public function authorize(): true
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:1', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(['id', 'name', 'price', 'quantity', 'created_at', 'updated_at'])],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'filter' => ['sometimes', 'array'],
            'filter.category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'filter.category_ids' => ['sometimes', 'string', 'regex:/^\d+(,\d+)*$/'],
            'filter.min_price' => ['sometimes', 'numeric', 'min:0'],
            'filter.max_price' => ['sometimes', 'numeric', 'min:0', 'gte:filter.min_price'],
            'filter.min_quantity' => ['sometimes', 'integer', 'min:0'],
            'filter.max_quantity' => ['sometimes', 'integer', 'min:0', 'gte:filter.min_quantity'],
        ];
    }

    /**
     * @param  string|array<int|string, mixed>|null  $key
     * @param  mixed  $default
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated();

        $validated['page'] = $validated['page'] ?? 1;
        $validated['per_page'] = $validated['per_page'] ?? 15;
        $validated['sort'] = $validated['sort'] ?? 'id';
        $validated['order'] = $validated['order'] ?? 'asc';
        $validated['filter'] = $validated['filter'] ?? [];
        $validated['filter']['search'] = $validated['q'];

        return data_get($validated, $key, $default);
    }
}
