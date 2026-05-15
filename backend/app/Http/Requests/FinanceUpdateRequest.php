<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinanceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'containers_count' => ['required', 'integer', 'min:1'],
            'units_per_container' => ['required', 'integer', 'min:1'],
            'occupancy_percent' => ['required', 'numeric', 'between:0,100'],
            'capex_total' => ['required', 'numeric', 'min:0'],
            'opex_total' => ['required', 'numeric', 'min:0'],
            'price_per_unit' => ['required', 'array'],
            'price_per_unit.*' => ['numeric', 'min:0'],
        ];
    }
}
