<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkChecklistUpdateRequest;
use App\Models\Site;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;

class SiteChecklistController extends Controller
{
    public function __construct(private readonly ScoringService $scoringService)
    {
    }

    public function show(Site $site): JsonResponse
    {
        return response()->json($site->load('checklistValues.checklistItem'));
    }

    public function update(BulkChecklistUpdateRequest $request, Site $site): JsonResponse
    {
        foreach ($request->validated('items') as $item) {
            $site->checklistValues()->updateOrCreate(
                ['checklist_item_id' => $item['checklist_item_id']],
                ['value' => $item['value'], 'comment' => $item['comment'] ?? null],
            );
        }

        $score = $this->scoringService->recalculateSiteScore($site->fresh('checklistValues.checklistItem'));

        return response()->json(['site_score' => $score]);
    }

    public function recalculate(Site $site): JsonResponse
    {
        $score = $this->scoringService->recalculateSiteScore($site->load('checklistValues.checklistItem'));

        return response()->json(['site_score' => $score]);
    }
}
