<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'item_code' => ['required', 'string', 'max:64'],
            'warehouse' => ['required', 'string', 'max:100'],
            // null clears the rule, so the item stops raising low-stock alerts in that warehouse.
            'reorder_level' => ['present', 'nullable', 'numeric', 'min:0', 'max:1000000000'],
            'reorder_qty' => ['present', 'nullable', 'numeric', 'min:0', 'max:1000000000'],
        ];
    }
}
