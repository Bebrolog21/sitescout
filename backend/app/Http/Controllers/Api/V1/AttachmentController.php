<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $attachments = Attachment::query()
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->string('entity_type')))
            ->when($request->filled('entity_id'), fn ($query) => $query->where('entity_id', $request->integer('entity_id')))
            ->latest()
            ->get();

        return response()->json($attachments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'in:site,visit,risk'],
            'entity_id'   => ['required', 'integer'],
            'kind'        => ['required', 'in:photo,scheme,document,other'],
            'file'        => ['required_without:url', 'file', 'max:20480'],
            'url'         => ['required_without:file', 'nullable', 'url'],
        ]);

        $payload = [
            'entity_type' => $validated['entity_type'],
            'entity_id'   => $validated['entity_id'],
            'kind'        => $validated['kind'],
        ];

        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $directory = "attachments/{$validated['entity_type']}/{$validated['entity_id']}";
            $storedPath = $uploaded->store($directory, 'public');

            $payload['path']          = $storedPath;
            $payload['original_name'] = $uploaded->getClientOriginalName();
            $payload['mime_type']     = $uploaded->getClientMimeType();
            $payload['size']          = $uploaded->getSize();
        } else {
            $payload['path'] = $validated['url'];
        }

        $attachment = Attachment::create($payload);

        return response()->json($attachment, 201);
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        if (! $attachment->isExternalLink()) {
            Storage::disk('public')->delete($attachment->path);
        }

        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted.']);
    }
}
