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
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $this->authorize('view', $chatSession);

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
        $this->authorize('delete', $chatSession);
        $chatSession->delete();

        return ApiResponse::ok([], 'Sesi dihapus.');
    }

    public function sendMessage(Request $request, ChatSession $chatSession)
    {
        $this->authorize('update', $chatSession);

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
     * Streaming variant of {@see sendMessage()}. Emits Server-Sent Events
     * so the client can render each token as it arrives instead of waiting
     * for the whole reply. Wire format:
     *
     *   event: chunk
     *   data: {"content":"..."}
     *
     *   event: done
     *   data: {"message_id":42,"tokens":123,"session":{...}}
     *
     *   event: error
     *   data: {"code":"quota_exceeded","message":"...","status":403}
     *
     * Usage tracking happens AFTER the full message lands so partial /
     * failed streams never get billed. Quota pre-check happens before the
     * upstream connection is opened so quota-exhausted users fail fast
     * instead of getting a half-reply.
     */
    public function streamMessage(Request $request, ChatSession $chatSession): StreamedResponse
    {
        $this->authorize('update', $chatSession);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
        ]);

        $user = $request->user();
        $provider = $this->aiManager->resolveProvider('chat');
        $aiConfig = Configuration::getValue(null, 'ai', 'provider_config', []);
        $aiConfig = is_array($aiConfig) ? $aiConfig : [];

        [$apiKey, $model] = $this->resolveProviderCredentials($provider, $aiConfig);

        // Persistence: save user message + history BEFORE the upstream
        // request opens so that the assistant message can reference the
        // user's existing message id (foreign key is on ChatMessage).
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

        // SSE needs an open buffer. PHP's default output_buffering buffers
        // everything; disabling it here ensures each echo+flush reaches
        // the client immediately. ini_set only affects the current request.
        $response = new StreamedResponse(function () use ($user, $userMsg, $chatSession, $provider, $apiKey, $model, $history, $systemPrompt) {
            @ini_set('output_buffering', '0');
            @ini_set('implicit_flush', '1');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $send = function (string $event, array $payload): void {
                echo 'event: '.$event."\n";
                echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                if (function_exists('flush')) {
                    @flush();
                }
            };

            // Tracks whether the user already hit Stop. Checked before persisting
            // any assistant message so a partial stream never ends up in history.
            $aborted = false;
            // Tracks whether the stream finished its happy path (user message
            // + matching assistant message both persisted). The shutdown safety
            // net below uses this to know whether to clean up or leave alone.
            $completed = false;
            $checkAbort = function () use (&$aborted): bool {
                // ignore_user_abort is off by default; once the client closes the
                // socket, connection_aborted() returns 1 and we stop touching DB.
                if (function_exists('connection_aborted') && connection_aborted() !== 0) {
                    $aborted = true;
                }

                return $aborted;
            };

            // Safety net for any path that didn't go through $failAndRollback:
            // PHP fatal errors, timeout-driven kills, shutdown from `max_execution_time`,
            // or anywhere we `return` without cleaning up. If the stream hasn't
            // completed when the script is winding down, the user message is
            // almost certainly orphaned (no assistant reply was ever persisted)
            // so we drop it to keep the session history consistent.
            register_shutdown_function(function () use ($userMsg, &$completed): void {
                if ($completed) {
                    return;
                }
                try {
                    if ($userMsg->exists) {
                        $userMsg->delete();
                    }
                } catch (\Throwable) {
                    // Swallow — we're already in shutdown, nothing else we can do.
                }
            });

            // Single source of truth for the "emit error event + roll back the
            // user message + exit the stream" pattern. Centralising this means
            // every new failure branch stays consistent (and we never leak a
            // user message into the session when nothing useful came back).
            $failAndRollback = function (string $code, string $providerName, string $message, int $status) use ($send, $userMsg, &$aborted, &$completed): void {
                // Best-effort: if the client already went away, skip the SSE
                // emit and just clean up the DB row.
                if (! $aborted) {
                    $send('error', [
                        'code' => $code,
                        'provider' => $providerName,
                        'message' => $message,
                        'status' => $status,
                    ]);
                }
                if ($userMsg->exists) {
                    $userMsg->delete();
                }
                // Mark completed so the shutdown safety net doesn't try to
                // double-delete (the row is already gone).
                $completed = true;
            };

            // Surface config error early: no point opening an SSE connection
            // that will just emit a quota error 4 seconds in.
            if (! $apiKey) {
                $failAndRollback('no_api_key', $provider, "AI provider '{$provider}' belum dikonfigurasi. Hubungi admin.", 422);

                return;
            }

            // Quota pre-check: prevents half-replies and avoids wasting
            // the upstream request budget on a user who can't pay for it.
            $quota = $this->aiManager->getUsage($user, $provider);
            if ($quota['requests'] >= $quota['request_limit']) {
                $this->quotaNotifier->notifyQuotaExceeded($user, $provider);
                $failAndRollback('quota_exceeded', $provider, "Kuota {$provider} habis.", 403);

                return;
            }

            $send('start', ['provider' => $provider, 'model' => $model, 'user_message_id' => $userMsg->id]);

            $assembled = '';
            $totalTokens = 0;
            $errored = false;
            $executedProvider = $provider;

            try {
                foreach ($this->aiManager->stream($provider, [
                    'apiKey' => $apiKey,
                    'model' => $model,
                    'messages' => $history,
                    'maxTokens' => 600,
                    'temperature' => 0.8,
                    'systemPrompt' => $systemPrompt,
                ]) as $event) {
                    if ($checkAbort()) {
                        // Stop button (or dropped socket): don't keep yielding,
                        // don't persist anything. The 'finally' / shutdown below
                        // handles DB cleanup.
                        break;
                    }

                    if (($event['type'] ?? null) === 'chunk') {
                        $assembled .= $event['content'];
                        $send('chunk', ['content' => $event['content']]);
                    } elseif (($event['type'] ?? null) === 'done') {
                        $totalTokens = (int) ($event['tokens'] ?? 0);
                        // The adapter may have yielded a 'done' from the
                        // initial response even if it errored mid-stream;
                        // the 'error' branch below supersedes in that case.
                        if (! $errored) {
                            break;
                        }
                    } elseif (($event['type'] ?? null) === 'error') {
                        // Transparent fallback mirrors the non-streaming
                        // path: if AdaCode fails and Groq is configured,
                        // try Groq before surfacing the failure.
                        if ($provider === 'adacode' && ! $errored) {
                            $errored = true;
                            $groqConfig = Configuration::getValue(null, 'quote', 'groq', []);
                            $groqConfig = is_array($groqConfig) ? $groqConfig : [];
                            $groqApiKey = $groqConfig['api_key'] ?? null;
                            $groqModel = config('ai.providers.groq.chat_model', 'llama-3.1-8b-instant');

                            if ($groqApiKey) {
                                $send('fallback', ['from' => 'adacode', 'to' => 'groq']);
                                $executedProvider = 'groq';
                                $assembled = '';
                                $totalTokens = 0;

                                foreach ($this->aiManager->stream('groq', [
                                    'apiKey' => $groqApiKey,
                                    'model' => $groqModel,
                                    'messages' => $history,
                                    'maxTokens' => 600,
                                    'temperature' => 0.8,
                                    'systemPrompt' => $systemPrompt,
                                ]) as $fallbackEvent) {
                                    if ($checkAbort()) {
                                        break 2;
                                    }

                                    if (($fallbackEvent['type'] ?? null) === 'chunk') {
                                        $assembled .= $fallbackEvent['content'];
                                        $send('chunk', ['content' => $fallbackEvent['content']]);
                                    } elseif (($fallbackEvent['type'] ?? null) === 'done') {
                                        $totalTokens = (int) ($fallbackEvent['tokens'] ?? 0);
                                        break 2;
                                    } elseif (($fallbackEvent['type'] ?? null) === 'error') {
                                        $failAndRollback('ai_failed', 'groq', $fallbackEvent['error_message'] ?? 'AI fallback failed', $fallbackEvent['status'] ?? 502);

                                        return;
                                    }
                                }
                                $errored = false;
                                break;
                            }
                        }

                        $failAndRollback('ai_failed', $executedProvider, $event['error_message'] ?? 'Gagal mendapat respons dari AI.', $event['status'] ?? 502);

                        return;
                    }
                }
            } catch (\Throwable $e) {
                $failAndRollback('stream_exception', $executedProvider, $e->getMessage(), 500);

                return;
            }

            // Stop semantics: if the client aborted mid-stream, drop both the
            // user message and the partial assistant payload so the session
            // doesn't end up with "pertanyaan tanpa jawaban" or a truncated
            // reply that pollutes future prompts (history is loaded into the
            // next request's context).
            if ($checkAbort()) {
                if ($userMsg->exists) {
                    $userMsg->delete();
                }
                $completed = true;

                return;
            }

            // Empty-content guard: if the upstream returned nothing (rare,
            // but possible on certain configs), don't persist an empty
            // assistant row — that's worse than no row.
            if (trim($assembled) === '') {
                $failAndRollback('empty_response', $executedProvider, 'AI mengembalikan respons kosong.', 502);

                return;
            }

            $assistantMsg = ChatMessage::create([
                'session_id' => $chatSession->id,
                'role' => 'assistant',
                'content' => $assembled,
                'tokens' => $totalTokens,
            ]);

            $chatSession->touch();
            $this->aiManager->trackUsage($user, $totalTokens, 1, null, $executedProvider);

            $send('done', [
                'message' => $this->formatMessage($assistantMsg),
                'session' => $this->formatSession($chatSession->fresh()),
            ]);

            // Both rows are persisted; tell the shutdown safety net we're done.
            $completed = true;
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
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
