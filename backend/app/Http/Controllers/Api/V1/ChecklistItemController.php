<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistItemController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ChecklistItem::query()->orderBy('category')->orderBy('weight', 'desc')->get());
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admin can manage checklist items.');

        $item = ChecklistItem::create($request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:checklist_items,code'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
            'weight' => ['required', 'integer', 'between:1,10'],
        ]));

        return response()->json($item, 201);
    }

    public function update(Request $request, ChecklistItem $checklistItem): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admin can manage checklist items.');

        $checklistItem->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
            'weight' => ['required', 'integer', 'between:1,10'],
        ]));

        return response()->json($checklistItem->fresh());
    }
}
