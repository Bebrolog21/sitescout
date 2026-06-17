<?php

namespace App\Http\Requests;

use App\Enums\SiteStatus;
use App\Models\Site;
use App\Services\SiteWorkflowService;
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
            // Уже отвалилось на правиле `in` — проверять переход бессмысленно.
            if ($validator->errors()->has('status')) {
                return;
            }

            /** @var Site|null $site */
            $site = $this->route('site');

            if (! $site) {
                return;
            }

            $workflow = app(SiteWorkflowService::class);
            $from = (string) $site->status;
            $to = (string) $this->input('status');

            // Переход в тот же статус считаем no-op — пропускаем.
            if ($from === $to) {
                return;
            }

            if (! $workflow->isAllowed($site, $to)) {
                $validator->errors()->add('status', sprintf(
                    'Недопустимый переход из «%s» в «%s».',
                    SiteStatus::labelFor($from),
                    SiteStatus::labelFor($to),
                ));

                return;
            }

            foreach ($workflow->requirements($site, $from, $to) as $message) {
                $validator->errors()->add('status', $message);
            }
        });
    }
}
