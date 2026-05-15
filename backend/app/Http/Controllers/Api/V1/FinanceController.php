<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinanceUpdateRequest;
use App\Models\Site;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;

class FinanceController extends Controller
{
    public function __construct(private readonly FinanceService $financeService)
    {
    }

    public function show(Site $site): JsonResponse
    {
        return response()->json($site->finance);
    }

    public function update(FinanceUpdateRequest $request, Site $site): JsonResponse
    {
        $finance = $this->financeService->storeAndRecalculate($site, $request->validated());

        return response()->json($finance);
    }

    public function recalculate(Site $site): JsonResponse
    {
        $finance = $this->financeService->storeAndRecalculate($site, $site->finance?->assumptions_json ?? []);

        return response()->json($finance);
    }

    public function summary(Site $site): JsonResponse
    {
        return response()->json($site->finance?->results_json ?? []);
    }
}
