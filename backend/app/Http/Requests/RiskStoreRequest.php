<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RiskStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:legal,security,neighbors,engineering,competition,finance,other'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'probability' => ['required', 'in:low,medium,high'],
            'description' => ['required', 'string'],
            'mitigation' => ['nullable', 'string'],
        ];
    }
}
