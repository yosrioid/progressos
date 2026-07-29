<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocFileResource;
use App\Models\Doc;
use App\Models\DocFile;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocFileController extends Controller
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

    private const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'odp', 'rtf', 'txt', 'csv', 'md', 'json',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'zip', 'rar',
    ];

    public function store(Request $request, Doc $doc)
    {
        $this->authorize('update', $doc);

        if ($doc->files()->count() >= 20) {
            return ApiResponse::ok(['message' => 'Maximum 20 files per document.'], 'Maximum 20 files per document.', 422);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('file');

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file->getRealPath());

        if (! in_array($realMime, self::ALLOWED_MIME_TYPES)) {
            return ApiResponse::ok(['message' => 'File type not allowed.'], 'File type not allowed.', 422);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, self::ALLOWED_EXTENSIONS)) {
            return ApiResponse::ok(['message' => 'File extension not allowed.'], 'File extension not allowed.', 422);
        }

        // Sanitize original filename — strip path separators, control chars, limit length
        $safeName = preg_replace('/[\/\\\0\x01-\x1f]/', '_', $file->getClientOriginalName());
        $safeName = mb_substr((string) $safeName, 0, 200).'.'.$ext;

        $path = $file->storeAs('doc-files/'.$doc->id, Str::uuid().'.'.$ext, 'local');

        $docFile = DocFile::create([
            'doc_id' => $doc->id,
            'user_id' => $request->user()->id,
            'original_name' => $safeName,
            'path' => $path,
            'mime_type' => $realMime,
            'size' => $file->getSize(),
        ]);

        return ApiResponse::item('file', new DocFileResource($docFile), 201, 'File uploaded.');
    }

    public function show(Request $request, DocFile $docFile)
    {
        $this->authorize('view', $docFile->doc);

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

    public function destroy(Request $request, Doc $doc, DocFile $docFile)
    {
        $this->authorize('update', $doc);

        if ($docFile->doc_id !== $doc->id || $docFile->user_id !== $request->user()->id) {
            abort(403, 'File tidak dapat diakses.');
        }

        Storage::disk('local')->delete($docFile->path);
        $docFile->delete();

        return response()->noContent();
    }
}
