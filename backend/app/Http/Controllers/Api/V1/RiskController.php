<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskStoreRequest;
use App\Models\Risk;
use App\Models\Site;
use App\Services\RiskService;
use Illuminate\Http\JsonResponse;

class RiskController extends Controller
{
    public function __construct(private readonly RiskService $riskService)
    {
    }

    public function index(Site $site): JsonResponse
    {
        return response()->json($site->risks);
    }

    public function store(RiskStoreRequest $request, Site $site): JsonResponse
    {
        $risk = $site->risks()->create($request->validated());
        $score = $this->riskService->recalculateSiteRiskScore($site->fresh('risks'));

        return response()->json(['risk' => $risk, 'risk_score' => $score], 201);
    }

    public function update(RiskStoreRequest $request, Risk $risk): JsonResponse
    {
        $risk->update($request->validated());
        $score = $this->riskService->recalculateSiteRiskScore($risk->site->fresh('risks'));

        return response()->json(['risk' => $risk->fresh(), 'risk_score' => $score]);
    }

    public function destroy(Risk $risk): JsonResponse
    {
        $site = $risk->site;
        $risk->delete();
        $score = $this->riskService->recalculateSiteRiskScore($site->fresh('risks'));

        return response()->json(['risk_score' => $score]);
    }
}
