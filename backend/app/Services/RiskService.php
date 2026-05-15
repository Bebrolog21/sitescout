<?php

namespace App\Services;

use App\Models\Site;

class RiskService
{
    public function recalculateSiteRiskScore(Site $site): int
    {
        $severityMap = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $probabilityMap = ['low' => 1, 'medium' => 2, 'high' => 3];

        $total = 0;
        $max = max($site->risks->count(), 1) * 12;

        foreach ($site->risks as $risk) {
            $total += ($severityMap[$risk->severity] ?? 1) * ($probabilityMap[$risk->probability] ?? 1);
        }

        $score = (int) round(($total / $max) * 100);
        $site->update(['risk_score' => $score]);

        return $score;
    }
}
