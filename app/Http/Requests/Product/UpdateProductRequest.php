<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProductRequest extends FormRequest
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
            'id' => ['required', 'integer', 'exists:products,id'],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'price' => 'required|numeric|min:0.01',
            'quantity' => 'required|integer|min:0',
            'category_id' => 'nullable|integer|exists:categories,id',
        ];
    }
}
