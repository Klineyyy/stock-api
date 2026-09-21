<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'item_name' => ['sometimes', 'required', 'string', 'max:255'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('products', 'barcode')->ignore($product)],
            'stock_uom' => ['sometimes', 'required', 'string', 'max:16'],
        ];
    }
}
