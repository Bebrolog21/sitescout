<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkChecklistUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.checklist_item_id' => ['required', 'integer'],
            'items.*.value' => ['required', 'integer', 'between:0,5'],
            'items.*.comment' => ['nullable', 'string'],
        ];
    }
}
