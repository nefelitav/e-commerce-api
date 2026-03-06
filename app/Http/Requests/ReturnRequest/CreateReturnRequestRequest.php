<?php

namespace App\Http\Requests\ReturnRequest;

use Illuminate\Foundation\Http\FormRequest;

final class CreateReturnRequestRequest extends FormRequest
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
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
