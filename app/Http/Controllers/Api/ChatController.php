<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Configuration;
use App\Models\Journal;
use App\Models\Project;
use App\Models\User;
use App\Services\AiProviderManager;
use App\Services\QuotaNotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    private const HISTORY_LIMIT = 12;

    public function __construct(
        private AiProviderManager $aiManager,
        private QuotaNotificationService $quotaNotifier,
    ) {}

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
        $provider = $this->aiManager->resolveProvider('chat');
        $aiConfig = Configuration::getValue(null, 'ai', 'provider_config', []);
        $aiConfig = is_array($aiConfig) ? $aiConfig : [];

        [$apiKey, $model] = $this->resolveProviderCredentials($provider, $aiConfig);

        // No silent fallback: if the configured provider has no API key, surface a
        // clear configuration error instead of transparently switching provider.
        // That would (a) surprise the user, (b) bypass quota expectations, and
        // (c) make usage telemetry point to the wrong bucket.
        if (! $apiKey) {
            return ApiResponse::ok([
                'error' => 'no_api_key',
                'provider' => $provider,
            ], "AI provider '{$provider}' belum dikonfigurasi. Hubungi admin.", 422);
        }

        $userMsg = ChatMessage::create([
            'session_id' => $chatSession->id,
            'role' => 'user',
            'content' => $data['content'],
            'tokens' => 0,
        ]);

        if ($chatSession->messages()->count() === 1) {
            $chatSession->update(['title' => mb_substr($data['content'], 0, 60)]);
        }

        $history = $chatSession->messages()
            ->orderByDesc('created_at')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->toArray();

        $systemPrompt = $this->buildSystemPrompt($chatSession->context_type, $user);

        $executedProvider = $provider;
        $result = $this->aiManager->call($provider, [
            'apiKey' => $apiKey,
            'model' => $model,
            'messages' => $history,
            'maxTokens' => 600,
            'temperature' => 0.8,
            'systemPrompt' => $systemPrompt,
        ]);

        if ($result['success'] === false && $this->aiManager->isQuotaExceeded($result)) {
            $this->quotaNotifier->notifyQuotaExceeded($user, $provider);
            $userMsg->delete();

            return ApiResponse::ok(
                ['error' => 'quota_exceeded', 'provider' => $provider],
                "Kuota {$provider} habis. Silakan upgrade atau beralih provider.",
                403
            );
        }

        // Transparent fallback to Groq is gated behind an explicit configured
        // credential: only attempt it if Groq also has its own api key set.
        if (! $result['success'] && $provider === 'adacode') {
            $groqConfig = Configuration::getValue(null, 'quote', 'groq', []);
            $groqConfig = is_array($groqConfig) ? $groqConfig : [];
            $groqApiKey = $groqConfig['api_key'] ?? null;
            $groqModel = config('ai.providers.groq.chat_model', 'llama-3.1-8b-instant');

            if ($groqApiKey) {
                $executedProvider = 'groq';
                $result = $this->aiManager->call('groq', [
                    'apiKey' => $groqApiKey,
                    'model' => $groqModel,
                    'messages' => $history,
                    'maxTokens' => 600,
                    'temperature' => 0.8,
                    'systemPrompt' => $systemPrompt,
                ]);
            }
        }

        if (! $result['success']) {
            $userMsg->delete();

            return ApiResponse::ok([
                'error' => 'ai_failed',
                'provider' => $executedProvider,
            ], 'Gagal mendapat respons dari AI. Coba lagi.', 503);
        }

        $assistantMsg = ChatMessage::create([
            'session_id' => $chatSession->id,
            'role' => 'assistant',
            'content' => $result['content'],
            'tokens' => $result['tokens'],
        ]);

        $chatSession->touch();

        // Track usage against the provider that actually served the request, not
        // the configured default. Pass $feature=null and explicit $provider so
        // we never accidentally resolve a different feature's provider.
        $this->aiManager->trackUsage($user, $result['tokens'], 1, null, $executedProvider);

        return ApiResponse::ok([
            'message' => $this->formatMessage($assistantMsg),
            'session' => $this->formatSession($chatSession->fresh()),
        ]);
    }

    /**
     * @return array{0: ?string, 1: string} [api_key, model]
     */
    private function resolveProviderCredentials(string $provider, array $aiConfig): array
    {
        if ($provider === 'adacode') {
            return [
                $aiConfig['api_key'] ?? null,
                $aiConfig['model'] ?? config('ai.providers.adacode.chat_model', 'claude-sonnet-4-6'),
            ];
        }

        // Default 'groq': pull api_key from the dedicated quote/groq bucket, but
        // fall back to the admin-configured ai/provider_config.groq_api_key if
        // available. Model defaults to Groq's env-backed chat model.
        $groqBucket = Configuration::getValue(null, 'quote', 'groq', []);
        $groqBucket = is_array($groqBucket) ? $groqBucket : [];

        $apiKey = $groqBucket['api_key'] ?? $aiConfig['groq_api_key'] ?? null;
        $model = config('ai.providers.groq.chat_model', 'llama-3.1-8b-instant');

        return [$apiKey, $model];
    }

    private function buildSystemPrompt(string $contextType, User $user): ?string
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

            $lines = $projects->map(function (Project $p) {
                // @phpstan-ignore property.notFound, property.notFound
                return "- {$p->name} (status: {$p->status}, open tasks: {$p->open_tasks_count})";
            })->join("\n");

            return $base."\n\nKonteks: Pengguna ingin membahas proyek-proyeknya. Daftar proyek:\n{$lines}";
        }

        return $base;
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
