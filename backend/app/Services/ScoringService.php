<?php

namespace App\Services;

use App\Models\Site;

class ScoringService
{
    public function recalculateSiteScore(Site $site): int
    {
        $totalWeighted = 0;
        $maxWeighted = 0;

        foreach ($site->checklistValues as $value) {
            $weight = (int) $value->checklistItem->weight;
            $totalWeighted += (int) $value->value * $weight;
            $maxWeighted += 5 * $weight;
        }

        $score = $maxWeighted > 0 ? (int) round(($totalWeighted / $maxWeighted) * 100) : 0;
        $site->update(['site_score' => $score]);

        return $score;
    }
}
