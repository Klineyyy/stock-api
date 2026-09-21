<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'item_code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:products,item_code'],
            'item_name' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/', 'unique:products,barcode'],
            'stock_uom' => ['nullable', 'string', 'max:16'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_code.regex' => 'The item code may only contain letters, numbers, dots, dashes and underscores.',
            'barcode.regex' => 'The barcode may only contain letters, numbers and dashes.',
        ];
    }
}
