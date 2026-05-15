<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteStatusUpdateRequest;
use App\Http\Requests\SiteStoreRequest;
use App\Http\Requests\SiteUpdateRequest;
use App\Models\Attachment;
use App\Models\Site;
use App\Services\PassportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $statusFilter = (string) $request->string('status');

        $sites = Site::query()
            ->with('finance')
            ->when($statusFilter !== '', fn ($query) => $query->where('status', $statusFilter))
            // Архив скрываем по умолчанию — он показывается отдельной вкладкой
            // и достаётся только явным фильтром status=archived.
            ->when($statusFilter === '', fn ($query) => $query->where('status', '!=', 'archived'))
            ->when($request->string('district')->isNotEmpty(), fn ($query) => $query->where('district', (string) $request->string('district')))
            ->when($request->filled('min_score'), fn ($query) => $query->where('site_score', '>=', $request->integer('min_score')))
            ->when($request->string('q')->isNotEmpty(), function ($query) use ($request) {
                $term = '%'.(string) $request->string('q').'%';
                $query->where(fn ($subQuery) => $subQuery
                    ->where('title', 'like', $term)
                    ->orWhere('address', 'like', $term)
                    ->orWhere('district', 'like', $term));
            })
            ->latest()
            ->paginate();

        return response()->json($sites);
    }

    public function store(SiteStoreRequest $request): JsonResponse
    {
        $site = Site::create($request->validated() + ['created_by' => $request->user()?->id]);

        return response()->json($site, 201);
    }

    public function show(Site $site): JsonResponse
    {
        return response()->json($site->load(['finance', 'risks', 'visits', 'checklistValues.checklistItem']));
    }

    public function update(SiteUpdateRequest $request, Site $site): JsonResponse
    {
        $site->update($request->validated());

        return response()->json($site->fresh());
    }

    public function updateStatus(SiteStatusUpdateRequest $request, Site $site): JsonResponse
    {
        $site->update($request->validated());

        return response()->json($site->fresh());
    }

    public function passport(Site $site, PassportService $passportService): JsonResponse
    {
        return response()->json($passportService->build($site));
    }

    public function stats(Site $site): JsonResponse
    {
        return response()->json([
            'visits_count' => $site->visits()->count(),
            'risks_count' => $site->risks()->count(),
            'attachments_count' => Attachment::where('entity_type', 'site')
                ->where('entity_id', $site->id)
                ->count(),
        ]);
    }
}

