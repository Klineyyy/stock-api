<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100', 'unique:warehouses,name']];
    }
}
