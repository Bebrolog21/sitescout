<?php

namespace App\Services;

use App\Models\FinancialModel;
use App\Models\Site;

class FinanceService
{
    public function storeAndRecalculate(Site $site, array $assumptions): FinancialModel
    {
        $results = $this->calculate($assumptions);

        return $site->finance()->updateOrCreate(
            ['site_id' => $site->id],
            [
                'assumptions_json' => $assumptions,
                'results_json' => $results,
            ],
        );
    }

    public function calculate(array $assumptions): array
    {
        $containers = (int) ($assumptions['containers_count'] ?? 0);
        $unitsPerContainer = (int) ($assumptions['units_per_container'] ?? 0);
        $occupancy = ((float) ($assumptions['occupancy_percent'] ?? 0)) / 100;
        $prices = $assumptions['price_per_unit'] ?? [];

        $averagePrice = count($prices) > 0 ? array_sum($prices) / count($prices) : 0;
        $monthlyRevenue = (int) round($containers * $unitsPerContainer * $averagePrice * $occupancy);
        $monthlyGrossProfit = (int) round($monthlyRevenue - (float) ($assumptions['opex_total'] ?? 0));
        $capex = (float) ($assumptions['capex_total'] ?? 0);

        return [
            'monthly_revenue' => $monthlyRevenue,
            'monthly_gross_profit' => $monthlyGrossProfit,
            'payback_months' => $monthlyGrossProfit > 0 ? (int) ceil($capex / $monthlyGrossProfit) : null,
            'roi_12m' => $capex > 0 ? (int) round(($monthlyGrossProfit * 12 / $capex) * 100) : 0,
            'breakeven_occupancy' => $monthlyRevenue > 0 ? (int) round(((float) ($assumptions['opex_total'] ?? 0) / $monthlyRevenue) * 100) : 0,
        ];
    }
}
