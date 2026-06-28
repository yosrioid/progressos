<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Journal;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        $journals = Journal::ownedBy($request->user())
            ->orderByDesc('date')
            ->paginate(20);

        return ApiResponse::ok($journals->through(fn ($j) => $this->format($j)));
    }

    public function show(Request $request, Journal $journal)
    {
        abort_unless($journal->user_id === $request->user()->id, 403);

        return ApiResponse::ok(['journal' => $this->format($journal)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $journal = Journal::updateOrCreate(
            ['user_id' => $request->user()->id, 'date' => $data['date']],
            ['body' => $data['body']]
        );

        return ApiResponse::ok(['journal' => $this->format($journal)], 'Journal disimpan.');
    }

    public function update(Request $request, Journal $journal)
    {
        abort_unless($journal->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'body' => ['sometimes', 'string', 'max:10000'],
            'mood' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tema' => ['sometimes', 'nullable', 'string', 'max:200'],
            'ai_content' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $journal->update($data);

        return ApiResponse::ok(['journal' => $this->format($journal)], 'Journal diperbarui.');
    }

    public function destroy(Request $request, Journal $journal)
    {
        abort_unless($journal->user_id === $request->user()->id, 403);
        $journal->delete();

        return ApiResponse::ok([], 'Journal dihapus.');
    }

    public function analyze(Request $request, Journal $journal)
    {
        abort_unless($journal->user_id === $request->user()->id, 403);

        $user = $request->user();
        $config = Configuration::getValue($user, 'quote', 'groq', []);
        $apiKey = is_array($config) ? ($config['api_key'] ?? null) : null;

        if (! $apiKey) {
            return ApiResponse::ok(['error' => 'no_api_key'], 'Groq API key belum dikonfigurasi.', 422);
        }

        $result = $this->callGroq($apiKey, $journal->body);

        if (! $result) {
            return ApiResponse::ok(['error' => 'ai_failed'], 'Gagal mendapatkan analisa dari AI. Coba lagi nanti.', 503);
        }

        $journal->update([
            'mood' => $result['mood'],
            'tema' => $result['tema'],
            'ai_content' => $result['content'],
            'ai_insight' => $result['insight'],
            'ai_saran' => $result['saran'],
            'analyzed_at' => now(),
        ]);

        return ApiResponse::ok(['journal' => $this->format($journal)], 'Analisa selesai.');
    }

    private function callGroq(string $apiKey, string $body): ?array
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.1-8b-instant',
                    'max_tokens' => 400,
                    'temperature' => 0.7,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Kamu adalah teman reflektif yang membantu menganalisa jurnal harian. Selalu balas dengan JSON valid saja, tanpa penjelasan tambahan. Gunakan bahasa Indonesia.',
                        ],
                        [
                            'role' => 'user',
                            'content' => "Analisa jurnal harian berikut dan balas HANYA dengan JSON ini:\n{\"mood\": \"(1 frasa suasana hati, maks 8 kata)\", \"tema\": \"(tema utama dipisah koma, maks 4 tema)\", \"content\": \"(ringkasan naratif 2-3 kalimat dari isi jurnal, tulis seolah kamu menceritakan kembali hari mereka)\", \"insight\": \"(1-2 insight menarik tentang pola atau kebiasaan yang terlihat)\", \"saran\": \"(1-2 saran konkret yang bisa dilakukan besok)\"}\n\nJurnal:\n{$body}",
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return null;
            }

            $content = $response->json('choices.0.message.content', '');
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (isset($parsed['mood'], $parsed['tema'], $parsed['content'], $parsed['insight'], $parsed['saran'])) {
                    return $parsed;
                }
            }
        } catch (\Throwable) {
            // non-critical
        }

        return null;
    }

    private function format(Journal $journal): array
    {
        return [
            'id' => $journal->id,
            'date' => $journal->date->toDateString(),
            'body' => $journal->body,
            'mood' => $journal->mood,
            'tema' => $journal->tema,
            'ai_content' => $journal->ai_content,
            'ai_insight' => $journal->ai_insight,
            'ai_saran' => $journal->ai_saran,
            'analyzed_at' => $journal->analyzed_at?->toIso8601String(),
            'created_at' => $journal->created_at->toIso8601String(),
        ];
    }
}
