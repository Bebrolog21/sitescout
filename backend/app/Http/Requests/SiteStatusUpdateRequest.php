<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SiteStatusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:new,screening,inspection,scoring,negotiation,approved,rejected,launched,archived'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('status') !== 'approved') {
                return;
            }

            /** @var Site|null $site */
            $site = $this->route('site');

            if (! $site) {
                return;
            }

            $site->loadMissing(['finance', 'risks']);

            if ((int) $site->site_score <= 0) {
                $validator->errors()->add('status', 'Перед согласованием нужно рассчитать балл площадки.');
            }

            if (! $site->finance || empty($site->finance->results_json)) {
                $validator->errors()->add('status', 'Перед согласованием нужно рассчитать финансовую модель.');
            }

            $hasCriticalRiskWithoutMitigation = $site->risks->contains(function ($risk): bool {
                return $risk->severity === 'critical' && blank($risk->mitigation);
            });

            if ($hasCriticalRiskWithoutMitigation) {
                $validator->errors()->add('status', 'У площадки есть критические риски без плана митигации — площадка не может быть согласована.');
            }
        });
    }
}
