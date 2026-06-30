<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InboxController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $conversations = InboxConversation::with(['userOne:id,name,avatar_path', 'userTwo:id,name,avatar_path'])
            ->where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn ($c) => $this->conversationPayload($c, $userId));

        return ApiResponse::collection('conversations', $conversations);
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id']);

        if ((int) $data['user_id'] === $request->user()->id) {
            return ApiResponse::ok([], 'Cannot chat with yourself.', 422);
        }

        $conv = InboxConversation::between($request->user()->id, (int) $data['user_id']);
        $conv->load(['userOne:id,name,avatar_path', 'userTwo:id,name,avatar_path']);

        return ApiResponse::item('conversation', $this->conversationPayload($conv, $request->user()->id));
    }

    public function messages(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->assertParticipant($conversation, $request->user()->id);

        $conversation->markRead($request->user()->id);

        $messages = $conversation->messages()
            ->with('sender:id,name,avatar_path')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => $this->messagePayload($m));

        return ApiResponse::collection('messages', $messages);
    }

    public function send(Request $request, InboxConversation $conversation): JsonResponse
    {
        $this->assertParticipant($conversation, $request->user()->id);

        $request->validate([
            'body' => 'nullable|string|max:5000',
            'file' => ['nullable', 'file', 'max:20480', function ($attr, $value, $fail) {
                $allowed = [
                    'image/', 'video/', 'audio/',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument',
                    'application/vnd.ms-excel',
                    'application/zip',
                    'text/plain',
                    'text/csv',
                ];
                $mime = $value->getMimeType() ?? '';
                $ok = collect($allowed)->contains(fn ($prefix) => str_starts_with($mime, $prefix));
                if (! $ok) {
                    $fail('Tipe file tidak diizinkan.');
                }
            }],
        ]);

        if (! $request->filled('body') && ! $request->hasFile('file')) {
            return ApiResponse::ok([], 'Pesan tidak boleh kosong.', 422);
        }

        $type = 'text';
        $filePath = $fileName = $fileMime = null;
        $fileSize = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $fileMime = $file->getMimeType() ?? 'application/octet-stream';
            $type = str_starts_with($fileMime, 'image/') ? 'image' : 'file';
            $filePath = $file->store("inbox/{$conversation->id}", 'local');
        }

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $request->input('body'),
            'type' => $type,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'file_mime' => $fileMime,
        ]);

        $conversation->update(['last_message_at' => now()]);

        return ApiResponse::item('message', $this->messagePayload($message->load('sender:id,name,avatar_path')), 201);
    }

    public function deleteMessage(Request $request, InboxMessage $message): JsonResponse
    {
        $conversation = $message->conversation;
        $this->assertParticipant($conversation, $request->user()->id);

        if ($message->sender_id !== $request->user()->id) {
            abort(403, 'Hanya bisa hapus pesan sendiri.');
        }

        if ($message->isDeletedForEveryone()) {
            return ApiResponse::ok([], 'Sudah dihapus.', 422);
        }

        $message->update(['deleted_for_everyone_at' => now()]);

        return ApiResponse::item('message', $this->messagePayload($message->fresh()));
    }

    public function serveFile(Request $request, InboxMessage $message): mixed
    {
        $conversation = $message->conversation;
        $this->assertParticipant($conversation, $request->user()->id);

        if (! $message->file_path || $message->isDeletedForEveryone()) {
            abort(404);
        }

        return Storage::disk('local')->download($message->file_path, $message->file_name);
    }

    public function unread(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $total = InboxConversation::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->get()
            ->sum(fn ($c) => $c->unreadCount($userId));

        return response()->json(['unread' => $total]);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::where('id', '!=', $request->user()->id)
            ->whereNull('disabled_at')
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'email', 'avatar_path'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar_path ? '/storage/'.$u->avatar_path : null,
            ]);

        return ApiResponse::collection('users', $users);
    }

    private function assertParticipant(InboxConversation $conv, int $userId): void
    {
        if ($conv->user_one_id !== $userId && $conv->user_two_id !== $userId) {
            abort(403);
        }
    }

    private function conversationPayload(InboxConversation $c, int $myId): array
    {
        $other = $c->otherUser($myId);
        $last = $c->messages()->orderByDesc('created_at')->first();

        return [
            'id' => $c->id,
            'other_user' => [
                'id' => $other->id,
                'name' => $other->name,
                'avatar_url' => $other->avatar_path ? '/storage/'.$other->avatar_path : null,
            ],
            'last_message' => $last ? $this->messagePayload($last) : null,
            'unread_count' => $c->unreadCount($myId),
            'last_message_at' => $c->last_message_at?->toISOString(),
        ];
    }

    private function messagePayload(InboxMessage $m): array
    {
        if ($m->isDeletedForEveryone()) {
            return [
                'id' => $m->id,
                'conversation_id' => $m->conversation_id,
                'sender_id' => $m->sender_id,
                'deleted' => true,
                'created_at' => $m->created_at->toISOString(),
            ];
        }

        return [
            'id' => $m->id,
            'conversation_id' => $m->conversation_id,
            'sender_id' => $m->sender_id,
            'sender_name' => $m->relationLoaded('sender') ? $m->sender->name : null,
            'sender_avatar' => $m->relationLoaded('sender') && $m->sender->avatar_path ? '/storage/'.$m->sender->avatar_path : null,
            'body' => $m->body,
            'type' => $m->type,
            'file_name' => $m->file_name,
            'file_size' => $m->file_size,
            'file_mime' => $m->file_mime,
            'file_url' => $m->file_path ? "/api/v1/inbox/messages/{$m->id}/file" : null,
            'deleted' => false,
            'created_at' => $m->created_at->toISOString(),
        ];
    }
}
