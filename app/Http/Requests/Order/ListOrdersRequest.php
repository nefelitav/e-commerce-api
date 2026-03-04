<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListOrdersRequest extends FormRequest
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(['id', 'status', 'total_price', 'created_at', 'updated_at'])],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'filter' => ['sometimes', 'array'],
            'filter.status' => ['sometimes', 'string', 'regex:/^(pending|paid|shipped|delivered|cancelled|refunded)(,(pending|paid|shipped|delivered|cancelled|refunded))*$/'],
            'filter.min_total' => ['sometimes', 'numeric', 'min:0'],
            'filter.max_total' => ['sometimes', 'numeric', 'min:0', 'gte:filter.min_total'],
            'include' => ['sometimes', 'string'],
        ];
    }

    /**
     * @param string|array<int|string, mixed>|null $key
     * @param mixed $default
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated();

        // Set defaults
        $validated['page'] = $validated['page'] ?? 1;
        $validated['per_page'] = $validated['per_page'] ?? 15;
        $validated['sort'] = $validated['sort'] ?? 'id';
        $validated['order'] = $validated['order'] ?? 'asc';
        $validated['filter'] = $validated['filter'] ?? [];
        $validated['include'] = isset($validated['include']) ? explode(',', $validated['include']) : [];

        return data_get($validated, $key, $default);
    }
}

