<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Configuration;
use App\Models\Journal;
use App\Models\Project;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    private const HISTORY_LIMIT = 12;

    public function index(Request $request)
    {
        $sessions = ChatSession::ownedBy($request->user())
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn ($s) => $this->formatSession($s));

        return ApiResponse::ok(['sessions' => $sessions]);
    }

    public function show(Request $request, ChatSession $chatSession)
    {
        abort_unless($chatSession->user_id === $request->user()->id, 403);

        $messages = $chatSession->messages()->get()->map(fn ($m) => $this->formatMessage($m));

        return ApiResponse::ok([
            'session' => $this->formatSession($chatSession),
            'messages' => $messages,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'context_type' => ['required', 'in:general,journal,project'],
            'title' => ['nullable', 'string', 'max:100'],
        ]);

        $session = ChatSession::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'] ?? 'New Chat',
            'context_type' => $data['context_type'],
        ]);

        return ApiResponse::ok(['session' => $this->formatSession($session)], 'Sesi chat dibuat.');
    }

    public function destroy(Request $request, ChatSession $chatSession)
    {
        abort_unless($chatSession->user_id === $request->user()->id, 403);
        $chatSession->delete();

        return ApiResponse::ok([], 'Sesi dihapus.');
    }

    public function sendMessage(Request $request, ChatSession $chatSession)
    {
        abort_unless($chatSession->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
        ]);

        $user = $request->user();
        $config = Configuration::getValue(null, 'quote', 'groq', []);
        $apiKey = is_array($config) ? ($config['api_key'] ?? null) : null;

        if (! $apiKey) {
            return ApiResponse::ok(['error' => 'no_api_key'], 'Groq API key belum dikonfigurasi.', 422);
        }

        // Save user message
        $userMsg = ChatMessage::create([
            'session_id' => $chatSession->id,
            'role' => 'user',
            'content' => $data['content'],
            'tokens' => 0,
        ]);

        // Auto-title from first message
        if ($chatSession->messages()->count() === 1) {
            $chatSession->update(['title' => mb_substr($data['content'], 0, 60)]);
        }

        // Build messages for Groq: last 12 (including the one just saved)
        $history = $chatSession->messages()
            ->orderByDesc('created_at')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->toArray();

        $systemPrompt = $this->buildSystemPrompt($chatSession->context_type, $user);

        $result = $this->callGroq($apiKey, $systemPrompt, $history);

        if (! $result) {
            $userMsg->delete();

            return ApiResponse::ok(['error' => 'ai_failed'], 'Gagal mendapat respons dari AI. Coba lagi.', 503);
        }

        // Save assistant message
        $assistantMsg = ChatMessage::create([
            'session_id' => $chatSession->id,
            'role' => 'assistant',
            'content' => $result['content'],
            'tokens' => $result['tokens'],
        ]);

        $chatSession->touch();
        QuoteController::trackUsageFor($user, $result['tokens']);

        return ApiResponse::ok([
            'message' => $this->formatMessage($assistantMsg),
            'session' => $this->formatSession($chatSession->fresh()),
        ]);
    }

    private function buildSystemPrompt(string $contextType, $user): string
    {
        $base = 'Kamu adalah asisten pribadi yang cerdas dan suportif untuk pengguna ini. Balas dalam bahasa Indonesia kecuali diminta lain. Jawab dengan ringkas dan natural, tidak perlu terlalu formal.';

        if ($contextType === 'journal') {
            $journals = Journal::ownedBy($user)
                ->where('date', '>=', now()->subDays(30)->toDateString())
                ->whereNotNull('mood')
                ->orderByDesc('date')
                ->limit(30)
                ->get(['date', 'mood', 'tema', 'body']);

            if ($journals->isEmpty()) {
                return $base."\n\nPengguna belum memiliki jurnal yang dianalisa dalam 30 hari terakhir.";
            }

            $lines = $journals->map(function ($j) {
                $first = mb_substr($j->body, 0, 80);

                return "- {$j->date} | mood: {$j->mood} | tema: {$j->tema} | \"{$first}\"";
            })->join("\n");

            return $base."\n\nKonteks: Pengguna ingin membahas jurnal hariannya. Berikut ringkasan 30 hari terakhir:\n{$lines}\n\nGunakan konteks ini untuk menjawab pertanyaan tentang pola, kebiasaan, perasaan, atau hal lain dari jurnal mereka.";
        }

        if ($contextType === 'project') {
            $projects = Project::where('user_id', $user->id)
                ->withCount(['tasks', 'tasks as open_tasks_count' => fn ($q) => $q->whereIn('status', ['todo', 'in_progress'])])
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'status', 'description']);

            if ($projects->isEmpty()) {
                return $base."\n\nPengguna belum memiliki proyek.";
            }

            $lines = $projects->map(function ($p) {
                return "- {$p->name} (status: {$p->status}, open tasks: {$p->open_tasks_count})";
            })->join("\n");

            return $base."\n\nKonteks: Pengguna ingin membahas proyek-proyeknya. Daftar proyek:\n{$lines}";
        }

        return $base;
    }

    private function callGroq(string $apiKey, string $systemPrompt, array $messages): ?array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.1-8b-instant',
                    'max_tokens' => 600,
                    'temperature' => 0.8,
                    'messages' => array_merge(
                        [['role' => 'system', 'content' => $systemPrompt]],
                        $messages
                    ),
                ]);

            if (! $response->successful()) {
                return null;
            }

            return [
                'content' => $response->json('choices.0.message.content', ''),
                'tokens' => $response->json('usage.total_tokens', 0),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatSession(ChatSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'context_type' => $session->context_type,
            'messages_count' => $session->messages_count ?? $session->messages()->count(),
            'updated_at' => $session->updated_at->toIso8601String(),
            'created_at' => $session->created_at->toIso8601String(),
        ];
    }

    private function formatMessage(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'tokens' => $message->tokens,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
