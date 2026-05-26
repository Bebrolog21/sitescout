<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SiteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'residential_complex_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:120'],
            // Район необязателен — у многих российских адресов его просто нет
            // (особенно в малых городах и центрах крупных).
            'district' => ['nullable', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'site_type' => ['required', 'in:yard,parking,tech_zone,other'],
            'area_m2' => ['required', 'numeric', 'gt:0'],
            'status' => ['required', 'in:new,screening,inspection,scoring,negotiation,approved,rejected,launched,archived'],
            'owner_type' => ['required', 'in:management_company,developer,municipality,private'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Поле «:attribute» обязательно к заполнению.',
            'string' => 'Поле «:attribute» должно быть строкой.',
            'numeric' => 'Поле «:attribute» должно быть числом.',
            'integer' => 'Поле «:attribute» должно быть целым числом.',
            'max' => 'Поле «:attribute» не должно быть длиннее :max символов.',
            'gt' => 'Поле «:attribute» должно быть больше :value.',
            'in' => 'Недопустимое значение для поля «:attribute».',
            'contact_phone.regex' => 'Телефон должен соответствовать маске «+7 (XXX) XXX-XX-XX».',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'название',
            'city' => 'город',
            'district' => 'район',
            'address' => 'адрес',
            'lat' => 'широта',
            'lng' => 'долгота',
            'site_type' => 'тип площадки',
            'area_m2' => 'площадь',
            'status' => 'статус',
            'owner_type' => 'тип владельца',
            'contact_name' => 'контактное лицо',
            'contact_phone' => 'телефон',
        ];
    }
}
