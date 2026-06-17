<?php

namespace App\Services;

use App\Enums\SiteStatus;
use App\Models\Site;

/**
 * Единый источник истины по статусам площадки.
 *
 * Логика:
 *  - пока данные не позволяют согласовать площадку, действует пошаговый режим
 *    (вперёд по воронке с условиями, шаг назад, отклонение, архив) — чтобы данные добрать;
 *  - как только данные позволяют согласовать (рассчитан балл, есть финмодель, нет
 *    критических рисков без митигации) — можно перейти на ЛЮБОЙ статус, кроме «Запущена»;
 *  - «Запущена» доступна только из «Согласована» и только если площадка рекомендована;
 *  - «Согласована» — хаб: отсюда на любой статус (включая «Запущена»);
 *  - «Запущена» можно только вернуть в «Согласована»;
 *  - «В архиве» и «Отклонена» — свободный возврат на любой статус (кроме «Запущена»).
 */
class SiteWorkflowService
{
    private const ALL_STATUSES = [
        'new', 'screening', 'inspection', 'scoring',
        'negotiation', 'approved', 'rejected', 'launched', 'archived',
    ];

    /**
     * Пошаговые рёбра воронки (режим «данные ещё не заполнены»): from => [[to, kind], ...].
     */
    private const STEP_EDGES = [
        'new' => [['screening', 'forward'], ['approved', 'approve'], ['rejected', 'reject'], ['archived', 'archive']],
        'screening' => [['inspection', 'forward'], ['new', 'back'], ['approved', 'approve'], ['rejected', 'reject'], ['archived', 'archive']],
        'inspection' => [['scoring', 'forward'], ['screening', 'back'], ['approved', 'approve'], ['rejected', 'reject'], ['archived', 'archive']],
        'scoring' => [['negotiation', 'forward'], ['inspection', 'back'], ['approved', 'approve'], ['rejected', 'reject'], ['archived', 'archive']],
        'negotiation' => [['approved', 'forward'], ['scoring', 'back'], ['rejected', 'reject'], ['archived', 'archive']],
        // Пустую (незаполненную) площадку из терминальных статусов можно только
        // реактивировать в начало воронки (и при желании архивировать).
        'rejected' => [['new', 'move'], ['archived', 'archive']],
        'archived' => [['new', 'move']],
    ];

    private function kindFor(string $to): string
    {
        return match ($to) {
            'rejected' => 'reject',
            'archived' => 'archive',
            'approved' => 'approve',
            'launched' => 'launch',
            default => 'move',
        };
    }

    /**
     * Невыполненные условия согласования (approved-гейт).
     *
     * @return array<int, string>
     */
    private function approvedRequirements(Site $site): array
    {
        $site->loadMissing(['finance', 'risks']);

        $errors = [];

        if ((int) $site->site_score <= 0) {
            $errors[] = 'Перед согласованием нужно рассчитать балл площадки.';
        }

        if (! $site->finance || empty($site->finance->results_json)) {
            $errors[] = 'Перед согласованием нужно рассчитать финансовую модель.';
        }

        $hasCriticalRiskWithoutMitigation = $site->risks->contains(
            fn ($risk): bool => $risk->severity === 'critical' && blank($risk->mitigation),
        );

        if ($hasCriticalRiskWithoutMitigation) {
            $errors[] = 'У площадки есть критические риски без плана митигации — площадка не может быть согласована.';
        }

        return $errors;
    }

    /**
     * Можно ли согласовать площадку прямо сейчас (все данные заполнены).
     */
    public function isApprovable(Site $site): bool
    {
        return $this->approvedRequirements($site) === [];
    }

    /**
     * Рёбра из текущего статуса с учётом заполненности данных.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function edges(Site $site): array
    {
        $from = (string) $site->status;

        // «Запущена» можно только вернуть в «Согласована».
        if ($from === 'launched') {
            return [['approved', 'approve']];
        }

        // «Согласована» — хаб: на любой статус (включая «Запущена»).
        if ($from === 'approved') {
            return $this->freeEdges($from, includeLaunched: true);
        }

        // Данные позволяют согласовать → любой статус, кроме «Запущена»
        // (в т.ч. свободный возврат из «Отклонена»/«В архиве»).
        if ($this->isApprovable($site)) {
            return $this->freeEdges($from, includeLaunched: false);
        }

        // Иначе — пошаговый режим (для терминальных статусов это реактивация в «Новая»).
        return self::STEP_EDGES[$from] ?? [];
    }

    /**
     * Свободные рёбра: все статусы, кроме текущего (и при необходимости «Запущена»).
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function freeEdges(string $from, bool $includeLaunched): array
    {
        $edges = [];
        foreach (self::ALL_STATUSES as $to) {
            if ($to === $from) {
                continue;
            }
            if ($to === 'launched' && ! $includeLaunched) {
                continue;
            }
            $edges[] = [$to, $this->kindFor($to)];
        }

        return $edges;
    }

    /**
     * Статусы, в которые можно перейти (без учёта гейтов).
     *
     * @return array<int, string>
     */
    public function allowedTargets(Site $site): array
    {
        return array_map(fn (array $edge): string => $edge[0], $this->edges($site));
    }

    /**
     * Невыполненные условия для перехода в $to. Пустой массив = переход возможен.
     *
     * @return array<int, string>
     */
    public function requirements(Site $site, string $from, string $to): array
    {
        // Пошаговые гейты — нужны, пока данные не заполнены (режим STEP_EDGES).
        if ($from === 'inspection' && $to === 'scoring' && (int) $site->site_score <= 0) {
            return ['Перед переходом к скорингу нужно заполнить чек-лист площадки.'];
        }

        if ($from === 'scoring' && $to === 'negotiation' && (int) $site->site_score <= 0) {
            return ['Перед переходом к согласованию нужно рассчитать балл площадки.'];
        }

        // approved-гейт действует при любом переходе в «Согласована».
        if ($to === 'approved') {
            return $this->approvedRequirements($site);
        }

        // Запуск возможен только для рекомендованной площадки (go/no-go-правило).
        if ($to === 'launched') {
            $site->loadMissing(['finance']);
            $payback = $site->finance?->results_json['payback_months'] ?? null;
            $recommended = (int) $site->site_score >= 70
                && (int) $site->risk_score <= 50
                && $payback !== null && $payback <= 18;

            if (! $recommended) {
                return ['Площадку нельзя запустить, пока она не рекомендована (нужно балл ≥ 70, риск-скор ≤ 50 и окупаемость ≤ 18 мес).'];
            }
        }

        return [];
    }

    /**
     * Структурно ли разрешён переход (без учёта гейтов).
     */
    public function isAllowed(Site $site, string $to): bool
    {
        return in_array($to, $this->allowedTargets($site), true);
    }

    /**
     * Можно ли совершить переход прямо сейчас (структура + гейты).
     */
    public function canTransition(Site $site, string $to): bool
    {
        return $this->isAllowed($site, $to)
            && $this->requirements($site, (string) $site->status, $to) === [];
    }

    /**
     * Метаданные статусов для фронта.
     *
     * @return array<string, mixed>
     */
    public function metadata(Site $site): array
    {
        $from = (string) $site->status;

        $transitions = [];
        foreach ($this->edges($site) as [$to, $kind]) {
            $transitions[] = [
                'to' => $to,
                'label' => SiteStatus::labelFor($to),
                'kind' => $kind,
                'requirements' => $this->requirements($site, $from, $to),
            ];
        }

        return [
            'current' => $from,
            'current_label' => SiteStatus::labelFor($from),
            'transitions' => $transitions,
        ];
    }
}
