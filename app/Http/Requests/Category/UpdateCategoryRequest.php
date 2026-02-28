<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

final class UpdateCategoryRequest extends FormRequest
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
            'id' => ['required', 'integer', 'exists:categories,id'],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && (int) $value === (int) $this->input('id', $this->route('id'))) {
                        $fail('A category cannot be its own parent.');
                    }
                },
            ],
        ];
    }
}
