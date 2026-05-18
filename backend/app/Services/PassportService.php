<?php

namespace App\Services;

use App\Models\Site;

class PassportService
{
    private const CATEGORY_LABELS = [
        'access' => 'Доступ',
        'security' => 'Безопасность',
        'visibility' => 'Видимость',
        'legal' => 'Юр. вопросы',
        'engineering' => 'Инженерия',
        'neighbors' => 'Соседи',
        'sales' => 'Спрос',
    ];

    public function build(Site $site): array
    {
        $site->loadMissing(['finance', 'risks', 'checklistValues.checklistItem']);

        $payback = $site->finance?->results_json['payback_months'] ?? null;
        $rules = [
            'score_ok' => $site->site_score >= 70,
            'risk_ok' => $site->risk_score <= 50,
            'payback_ok' => $payback !== null && $payback <= 18,
        ];
        $recommended = $rules['score_ok'] && $rules['risk_ok'] && $rules['payback_ok'];

        return [
            'site' => $site->only(['id', 'title', 'city', 'district', 'address', 'site_type', 'area_m2', 'status']),
            'site_score' => $site->site_score,
            'risk_score' => $site->risk_score,
            'finance' => $site->finance?->results_json,
            'risks' => $site->risks->map->only(['type', 'severity', 'probability', 'description', 'mitigation']),
            'decision' => $recommended ? 'recommended' : 'not_recommended',
            'decision_rules' => $rules,
        ];
    }

    /**
     * Полный набор данных для PDF — расширенная версия build().
     */
    public function buildFull(Site $site): array
    {
        $site->loadMissing(['finance', 'risks', 'checklistValues.checklistItem']);

        $assumptions = $site->finance?->assumptions_json ?? [];
        $results = $site->finance?->results_json ?? [];
        $payback = $results['payback_months'] ?? null;

        $rules = [
            'score_ok' => $site->site_score >= 70,
            'risk_ok' => $site->risk_score <= 50,
            'payback_ok' => $payback !== null && $payback <= 18,
        ];
        $recommended = $rules['score_ok'] && $rules['risk_ok'] && $rules['payback_ok'];

        return [
            'site' => $site->only([
                'id', 'title', 'city', 'district', 'address', 'site_type', 'area_m2',
                'status', 'owner_type', 'contact_name', 'contact_phone', 'lat', 'lng',
            ]),
            'site_score' => (int) $site->site_score,
            'risk_score' => (int) $site->risk_score,
            'category_scores' => $this->categoryBreakdown($site),
            'checklist' => $this->checklistRows($site),
            'risks' => $site->risks->map->only(['type', 'severity', 'probability', 'description', 'mitigation'])->all(),
            'finance' => [
                'assumptions' => $assumptions,
                'results' => $results,
            ],
            'decision' => $recommended ? 'recommended' : 'not_recommended',
            'decision_rules' => $rules,
            'generated_at' => now(),
        ];
    }

    /**
     * Подытоги скоринга по категориям. Та же формула, что в frontend/normalizeSite.
     */
    private function categoryBreakdown(Site $site): array
    {
        $buckets = [];
        foreach ($site->checklistValues as $value) {
            $item = $value->checklistItem;
            if (! $item) {
                continue;
            }
            $cat = $item->category ?? 'other';
            $weight = (int) $item->weight;
            $buckets[$cat] ??= ['earned' => 0, 'max' => 0];
            $buckets[$cat]['earned'] += (int) $value->value * $weight;
            $buckets[$cat]['max'] += 5 * $weight;
        }

        $rows = [];
        foreach ($buckets as $code => $b) {
            $rows[] = [
                'code' => $code,
                'label' => self::CATEGORY_LABELS[$code] ?? $code,
                'score' => $b['max'] > 0 ? (int) round(($b['earned'] / $b['max']) * 100) : 0,
            ];
        }

        return $rows;
    }

    /**
     * Полная таблица чеклиста: категория, пункт, оценка, комментарий.
     */
    private function checklistRows(Site $site): array
    {
        return $site->checklistValues
            ->filter(fn ($v) => $v->checklistItem !== null)
            ->sortBy(fn ($v) => $v->checklistItem->category.'#'.$v->checklistItem->id)
            ->values()
            ->map(fn ($v) => [
                'category' => $v->checklistItem->category,
                'category_label' => self::CATEGORY_LABELS[$v->checklistItem->category] ?? $v->checklistItem->category,
                'title' => $v->checklistItem->title,
                'value' => (int) $v->value,
                'weight' => (int) $v->checklistItem->weight,
                'comment' => $v->comment,
            ])
            ->all();
    }

}
