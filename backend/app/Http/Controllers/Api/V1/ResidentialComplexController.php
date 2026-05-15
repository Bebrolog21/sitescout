<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ResidentialComplex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResidentialComplexController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ResidentialComplex::query()->latest()->paginate());
    }

    public function store(Request $request): JsonResponse
    {
        $complex = ResidentialComplex::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'developer_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]));

        return response()->json($complex, 201);
    }

    public function show(ResidentialComplex $residentialComplex): JsonResponse
    {
        return response()->json($residentialComplex->load('sites'));
    }

    public function update(Request $request, ResidentialComplex $residentialComplex): JsonResponse
    {
        $residentialComplex->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'developer_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]));

        return response()->json($residentialComplex->fresh());
    }
}
