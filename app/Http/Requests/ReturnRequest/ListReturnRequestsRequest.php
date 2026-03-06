<?php

namespace App\Http\Requests\ReturnRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListReturnRequestsRequest extends FormRequest
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
            'sort' => ['sometimes', 'string', Rule::in(['id', 'status', 'created_at'])],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'filter' => ['sometimes', 'array'],
            'filter.status' => ['sometimes', 'string'],
            'filter.order_id' => ['sometimes', 'integer'],
            'filter.user_id' => ['sometimes', 'integer'],
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

        return data_get($validated, $key, $default);
    }
}
