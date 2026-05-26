<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function index(Site $site): JsonResponse
    {
        return response()->json($site->visits);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $visit = $site->visits()->create($request->validate([
            'visit_date' => ['required', 'date'],
            'visited_by_user_id' => ['required', 'integer', 'exists:users,id'],
            'summary' => ['required', 'string'],
        ]));

        return response()->json($visit, 201);
    }

    public function show(SiteVisit $visit): JsonResponse
    {
        return response()->json($visit);
    }

    public function update(Request $request, SiteVisit $visit): JsonResponse
    {
        $visit->update($request->validate([
            'visit_date' => ['required', 'date'],
            'visited_by_user_id' => ['required', 'integer', 'exists:users,id'],
            'summary' => ['required', 'string'],
        ]));

        return response()->json($visit->fresh());
    }

    public function destroy(SiteVisit $visit): JsonResponse
    {
        $visit->delete();

        return response()->json(['message' => 'Visit deleted.']);
    }
}
