<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocRequest;
use App\Http\Resources\DocResource;
use App\Models\Doc;
use App\Models\DocFile;
use App\Support\ApiQuery;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocController extends Controller
{
    public function index(Request $request)
    {
        $query = Doc::ownedBy($request->user());
        ApiQuery::applyTextSearch($query, $request, ['title', 'category', 'description']);
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        return ApiResponse::paginated('docs', ApiQuery::paginateSorted($query, $request, 'created_at', 20, ['created_at', 'updated_at', 'title', 'category']), resourceClass: DocResource::class);
    }

    public function categories(Request $request)
    {
        $cats = Doc::ownedBy($request->user())
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return ApiResponse::ok(['categories' => $cats]);
    }

    public function show(Request $request, Doc $doc)
    {
        $this->authorize('view', $doc);

        return ApiResponse::item('doc', new DocResource($doc->load('files')));
    }

    public function store(DocRequest $request)
    {
        $doc = $request->user()->docs()->create($request->validated());

        return ApiResponse::item('doc', new DocResource($doc->load('files')), 201, 'Doc created.');
    }

    public function update(DocRequest $request, Doc $doc)
    {
        $this->authorize('update', $doc);
        $doc->update($request->validated());

        return ApiResponse::item('doc', new DocResource($doc->fresh()->load('files')));
    }

    public function share(Request $request, Doc $doc)
    {
        $this->authorize('update', $doc);
        if (! $doc->share_token) {
            $doc->update(['share_token' => Str::random(48)]);
        }

        return ApiResponse::item('doc', new DocResource($doc->fresh()->load('files')));
    }

    public function unshare(Request $request, Doc $doc)
    {
        $this->authorize('update', $doc);
        $doc->update(['share_token' => null]);

        return ApiResponse::item('doc', new DocResource($doc->fresh()->load('files')));
    }

    public function destroy(Request $request, Doc $doc)
    {
        $this->authorize('delete', $doc);

        foreach ($doc->files as $file) {
            /** @var DocFile $file */
            Storage::disk('local')->delete($file->path);
        }
        $doc->delete();

        return response()->noContent();
    }
}
