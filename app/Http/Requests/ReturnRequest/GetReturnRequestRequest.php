<?php

namespace App\Http\Requests\ReturnRequest;

use Illuminate\Foundation\Http\FormRequest;

final class GetReturnRequestRequest extends FormRequest
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
            'id' => ['required', 'integer', 'exists:return_requests,id'],
        ];
    }
}
