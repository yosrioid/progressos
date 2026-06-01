<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReferenceRequest;
use App\Models\Reference;
use App\Support\ReferenceTypes;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    public function store(ReferenceRequest $request)
    {
        $data = $request->validated();
        $target = ReferenceTypes::MAP[$data['referenceable_type']]::query()->findOrFail($data['referenceable_id']);
        $this->authorize('view', $target);

        $reference = $target->references()->create([
            'user_id' => $request->user()->id,
            'label' => $data['label'],
            'url' => $data['url'],
            'type' => $data['type'],
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['reference' => $reference], 201);
    }

    public function destroy(Request $request, Reference $reference)
    {
        $this->authorize('delete', $reference);
        $reference->delete();

        return response()->noContent();
    }
}
