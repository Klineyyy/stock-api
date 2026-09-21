<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'item_code' => ['required', 'string', 'max:64'],
            'warehouse' => ['required', 'string', 'max:100'],
            // Positive adds stock, negative removes it. Up to three decimals (a kilo, a litre...).
            'qty' => [
                'required', 'numeric', 'decimal:0,3', 'between:-1000000000,1000000000',
                fn (string $attribute, mixed $value, \Closure $fail) => (float) $value === 0.0 ? $fail('The qty must not be zero.') : null,
            ],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
