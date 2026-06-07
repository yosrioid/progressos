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

        $path = $file->storeAs('doc-files/'.$doc->id, Str::uuid().'.'.$ext, 'local');

        $docFile = DocFile::create([
            'doc_id' => $doc->id,
            'user_id' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
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

        return Storage::disk('local')->download(
            $docFile->path,
            $docFile->original_name,
            ['Content-Type' => $docFile->mime_type]
        );
    }

    public function destroy(Request $request, Doc $doc, DocFile $docFile)
    {
        $this->authorize('update', $doc);

        if ($docFile->doc_id !== $doc->id || $docFile->user_id !== $request->user()->id) {
            abort(403);
        }

        Storage::disk('local')->delete($docFile->path);
        $docFile->delete();

        return response()->noContent();
    }
}
