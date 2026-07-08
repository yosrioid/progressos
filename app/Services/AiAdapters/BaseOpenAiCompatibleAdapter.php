<?php

namespace App\Services\AiAdapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shared implementation for AI providers that expose an OpenAI-compatible
 * /chat/completions endpoint (AdaCode, Groq, OpenAI, OpenRouter, etc.).
 *
 * Subclasses only need to declare where to point at (baseUrl) and which
 * provider name to tag the response with. Everything else — request
 * shape, auth header, timeout, retry, streaming, error handling, response
 * parsing — is shared here so the OpenAI-compatible contract can be
 * tested once.
 *
 * Non-streaming return shape:
 *   success:
 *     ['success' => true,  'content' => string, 'tokens' => int, 'provider' => string]
 *   failure:
 *     ['success' => false, 'status' => int, 'error_code' => ?string,
 *      'error_message' => string, 'provider' => string]
 *
 * Streaming yield shape:
 *   - ['type' => 'chunk', 'content' => string]            — incremental delta
 *   - ['type' => 'done',  'content' => string,            — final assembled reply
 *                          'tokens' => int]
 *   - ['type' => 'error', 'status' => int,
 *                          'error_code' => ?string,
 *                          'error_message' => string]     — unrecoverable
 */
abstract class BaseOpenAiCompatibleAdapter
{
    /** Endpoint root, no trailing slash (e.g. https://api.groq.com/openai/v1). */
    abstract protected static function baseUrl(): string;

    /** Provider identifier used in results and storage (e.g. 'groq', 'adacode'). */
    abstract protected static function providerName(): string;

    /** Default max_tokens for this provider if the caller doesn't specify one. */
    protected static function defaultMaxTokens(): int
    {
        return 1024;
    }

    /** Per-request timeout in seconds. */
    protected static function requestTimeout(): int
    {
        return 30;
    }

    /**
     * Send a chat completion request and normalise the response.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    public static function chat(
        string $apiKey,
        string $model,
        array $messages,
        ?int $maxTokens = null,
        float $temperature = 0.8,
        ?string $systemPrompt = null,
    ): array {
        $maxTokens ??= static::defaultMaxTokens();

        $result = static::withRetries(function () use ($apiKey, $model, $messages, $maxTokens, $temperature, $systemPrompt) {
            try {
                $payload = static::buildPayload($model, $messages, $maxTokens, $temperature, $systemPrompt);

                $response = Http::timeout(static::requestTimeout())
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type' => 'application/json',
                    ])
                    ->post(static::baseUrl().'/chat/completions', $payload);

                if (! $response->successful()) {
                    return [
                        'success' => false,
                        'status' => $response->status(),
                        'error_code' => $response->json('error.code'),
                        'error_message' => $response->json('error.message', 'Request failed'),
                        'provider' => static::providerName(),
                        '_retryable' => static::isRetryableStatus($response->status()),
                    ];
                }

                return [
                    'success' => true,
                    'content' => $response->json('choices.0.message.content', ''),
                    'tokens' => $response->json('usage.total_tokens', 0),
                    'provider' => static::providerName(),
                ];
            } catch (\Throwable $e) {
                Log::warning('AI request exception', [
                    'provider' => static::providerName(),
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'status' => 500,
                    'error_code' => 'internal_error',
                    'error_message' => $e->getMessage(),
                    'provider' => static::providerName(),
                    '_retryable' => true,
                ];
            }
        });

        unset($result['_retryable']);

        return $result;
    }

    /**
     * Stream a chat completion. Yields 'chunk' events as content arrives,
     * a final 'done' event with the assembled content + token count, or
     * an 'error' event on unrecoverable failure.
     *
     * Retry policy mirrors {@see chat()} for the initial handshake; once
     * the upstream has sent the first SSE chunk we commit to that response
     * and don't reconnect mid-stream (it would corrupt context for the
     * client). A handshake-level failure is emitted as a single 'error'
     * event.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return \Generator<int, array<string, mixed>>
     */
    public static function streamChat(
        string $apiKey,
        string $model,
        array $messages,
        ?int $maxTokens = null,
        float $temperature = 0.8,
        ?string $systemPrompt = null,
    ): \Generator {
        $maxTokens ??= static::defaultMaxTokens();
        $payload = static::buildPayload($model, $messages, $maxTokens, $temperature, $systemPrompt);
        $payload['stream'] = true;

        $maxAttempts = (int) config('ai.retry.max_attempts', 2);
        $attempt = 0;
        $response = null;
        $lastError = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                $response = Http::timeout(static::requestTimeout())
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type' => 'application/json',
                        'Accept' => 'text/event-stream',
                    ])
                    ->withOptions(['stream' => true])
                    ->post(static::baseUrl().'/chat/completions', $payload);
            } catch (\Throwable $e) {
                // If the client closed the connection (Stop button, tab close, etc.),
                // short-circuit retry attempts — no point hitting the upstream when
                // nobody is listening.
                if (function_exists('connection_aborted') && connection_aborted() !== 0) {
                    yield [
                        'type' => 'error',
                        'status' => 499,
                        'error_code' => 'client_closed',
                        'error_message' => 'Client closed the connection',
                    ];

                    return;
                }

                $lastError = $e;
                Log::warning('AI stream handshake exception', [
                    'provider' => static::providerName(),
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
                if ($attempt < $maxAttempts) {
                    static::backoff($attempt);

                    continue;
                }
                yield [
                    'type' => 'error',
                    'status' => 500,
                    'error_code' => 'internal_error',
                    'error_message' => $e->getMessage(),
                ];

                return;
            }

            if ($response->successful()) {
                break;
            }

            $status = $response->status();
            $lastError = [
                'status' => $status,
                'code' => $response->json('error.code'),
                'message' => $response->json('error.message', 'Request failed'),
            ];

            if (! static::isRetryableStatus($status) || $attempt >= $maxAttempts) {
                yield [
                    'type' => 'error',
                    'status' => $status,
                    'error_code' => $lastError['code'],
                    'error_message' => $lastError['message'],
                ];

                return;
            }

            Log::info('AI stream retrying', [
                'provider' => static::providerName(),
                'attempt' => $attempt,
                'status' => $status,
            ]);
            static::backoff($attempt);
        }

        if ($response === null || ! $response->successful()) {
            yield [
                'type' => 'error',
                'status' => 502,
                'error_code' => 'stream_failed',
                'error_message' => 'Could not open stream to AI provider.',
            ];

            return;
        }

        // Consume the upstream SSE stream line by line. Each line is one of:
        //   data: {"choices":[{"delta":{"content":"..."}}]}
        //   data: [DONE]
        //   <blank>
        $assembled = '';
        $totalTokens = 0;
        $body = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $chunk = $body->read(1024);
            if ($chunk === '') {
                // Avoid busy-loop if read() returns empty without eof
                usleep(2000);

                continue;
            }
            $buffer .= $chunk;

            // SSE events are terminated by a blank line (\n\n). Process
            // complete events and leave any partial one in the buffer.
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);
                $event = trim($event);
                if ($event === '') {
                    continue;
                }

                // Extract all 'data:' lines from this event.
                $dataLines = [];
                foreach (preg_split("/\r?\n/", $event) as $line) {
                    if (str_starts_with($line, 'data:')) {
                        $dataLines[] = ltrim(substr($line, 5));
                    }
                }
                $data = implode("\n", $dataLines);

                if ($data === '[DONE]') {
                    yield [
                        'type' => 'done',
                        'content' => $assembled,
                        'tokens' => $totalTokens,
                    ];

                    return;
                }

                if ($data === '') {
                    continue;
                }

                $decoded = json_decode($data, true);
                if (! is_array($decoded)) {
                    continue;
                }

                // OpenAI-compatible delta format
                $delta = $decoded['choices'][0]['delta']['content'] ?? null;
                if (is_string($delta) && $delta !== '') {
                    $assembled .= $delta;
                    yield ['type' => 'chunk', 'content' => $delta];
                }

                // Some providers (Groq) include usage in the last chunk.
                if (isset($decoded['usage']['total_tokens'])) {
                    $totalTokens = (int) $decoded['usage']['total_tokens'];
                }
            }
        }

        // Stream closed without an explicit [DONE]. Surface what we got.
        yield [
            'type' => 'done',
            'content' => $assembled,
            'tokens' => $totalTokens,
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    protected static function buildPayload(string $model, array $messages, int $maxTokens, float $temperature, ?string $systemPrompt): array
    {
        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => $messages,
        ];

        if ($systemPrompt !== null && $systemPrompt !== '') {
            array_unshift($payload['messages'], ['role' => 'system', 'content' => $systemPrompt]);
        }

        return $payload;
    }

    /**
     * Run a callable with retry/backoff. The callable may return an array
     * with a `_retryable` boolean indicating whether the failure should
     * be retried; the retry loop respects this hint in addition to the
     * status-code heuristic.
     *
     * @param  callable(): array<string, mixed>  $fn
     * @return array<string, mixed>
     */
    protected static function withRetries(callable $fn): array
    {
        $maxAttempts = max(1, (int) config('ai.retry.max_attempts', 2));
        $attempt = 0;
        $result = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $result = $fn();

            if (($result['success'] ?? false) === true) {
                return $result;
            }

            $retryable = ($result['_retryable'] ?? false) === true
                || static::isRetryableStatus($result['status'] ?? 0);

            if (! $retryable || $attempt >= $maxAttempts) {
                return $result;
            }

            Log::info('AI request retrying', [
                'provider' => static::providerName(),
                'attempt' => $attempt,
                'status' => $result['status'] ?? 0,
                'error_code' => $result['error_code'] ?? null,
            ]);
            static::backoff($attempt);
        }

        return $result !== null ? $result : [
            'success' => false,
            'status' => 500,
            'error_code' => 'internal_error',
            'error_message' => 'Retry loop exited without a result.',
            'provider' => static::providerName(),
        ];
    }

    /**
     * Status codes that are safe to retry. 429 (rate limit) and 5xx
     * (server errors / timeouts) get a second chance; 4xx (other than
     * 429) is a client error and we surface it immediately so the user
     * gets a meaningful message.
     */
    protected static function isRetryableStatus(int $status): bool
    {
        if ($status === 429) {
            return true;
        }
        if ($status >= 500 && $status < 600) {
            return true;
        }
        if ($status === 408 || $status === 0) {
            // 408 = request timeout, 0 = network failure surfaced as no status
            return true;
        }

        return false;
    }

    /**
     * Apply exponential backoff with jitter. Sleeps for the lesser of
     * the computed backoff and {@see config('ai.retry.max_backoff_ms')}.
     */
    protected static function backoff(int $attempt): void
    {
        $cfg = config('ai.retry', []);
        $initial = (int) ($cfg['initial_backoff_ms'] ?? 400);
        $multiplier = (float) ($cfg['backoff_multiplier'] ?? 2.0);
        $max = (int) ($cfg['max_backoff_ms'] ?? 4000);
        $jitter = (int) ($cfg['jitter_ms'] ?? 200);

        $base = $initial * ($multiplier ** ($attempt - 1));
        $base = min($base, $max);
        $jittered = $base + random_int(0, max(0, $jitter));

        usleep($jittered * 1000);
    }
}
