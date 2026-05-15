<?php

namespace App\Services;

use App\Models\Site;

class PassportService
{
    public function build(Site $site): array
    {
        $site->loadMissing(['finance', 'risks', 'checklistValues.checklistItem']);

        $payback = $site->finance?->results_json['payback_months'] ?? null;
        $recommended = $site->site_score >= 70 && $site->risk_score <= 50 && $payback !== null && $payback <= 18;

        return [
            'site' => $site->only(['id', 'title', 'city', 'district', 'address', 'site_type', 'area_m2', 'status']),
            'site_score' => $site->site_score,
            'risk_score' => $site->risk_score,
            'finance' => $site->finance?->results_json,
            'risks' => $site->risks->map->only(['type', 'severity', 'probability', 'description', 'mitigation']),
            'decision' => $recommended ? 'recommended' : 'not_recommended',
        ];
    }
}
