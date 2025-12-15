<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

final class GetCategoryRequest extends FormRequest
{
    public function authorize(): true
    {
        return true;
    }

    public function validationData(): ?array
    {
        return array_merge($this->request->all(), $this->route()->parameters());
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'exists:categories,id'],
        ];
    }
}
