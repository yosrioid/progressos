<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocResource;
use App\Models\Doc;
use App\Models\DocFile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocShareController extends Controller
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/rtf',
        'text/rtf',
        'text/plain',
        'text/csv',
        'text/markdown',
        'application/json',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/zip',
        'application/x-zip-compressed',
        'application/x-rar-compressed',
        'application/vnd.rar',
    ];

    public function show(string $token): JsonResponse
    {
        $doc = Doc::where('share_token', $token)->firstOrFail();

        return ApiResponse::item('doc', new DocResource($doc->load('files')));
    }

    public function file(string $token, DocFile $docFile): StreamedResponse|Response
    {
        $doc = Doc::where('share_token', $token)->firstOrFail();

        if ($docFile->doc_id !== $doc->id) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($docFile->path)) {
            abort(404);
        }

        $safeMime = in_array($docFile->mime_type, self::ALLOWED_MIME_TYPES)
            ? $docFile->mime_type
            : 'application/octet-stream';

        return Storage::disk('local')->download(
            $docFile->path,
            $docFile->original_name,
            ['Content-Type' => $safeMime, 'Content-Disposition' => 'attachment']
        );
    }
}
