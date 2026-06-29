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
    private $currentUser = null;

    public function index(Request $request)
    {
        $journals = Journal::ownedBy($request->user())
            ->orderByDesc('date')
            ->get()
            ->map(fn ($j) => $this->format($j))
            ->values();

        return ApiResponse::ok(['journals' => $journals]);
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

        $history = Journal::ownedBy($user)
            ->where('id', '!=', $journal->id)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('date')
            ->limit(30)
            ->get(['date', 'mood', 'tema', 'body']);

        $this->currentUser = $user;
        $result = $this->callGroq($apiKey, $journal->body, $history->toArray());

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

    private function callGroq(string $apiKey, string $body, array $history = []): ?array
    {
        $historyContext = '';
        if (! empty($history)) {
            $lines = array_map(function ($h) {
                $snippet = mb_substr($h['body'], 0, 150);
                $mood = $h['mood'] ?: '-';
                $tema = $h['tema'] ?: '-';

                return "• {$h['date']} [mood: {$mood}] [tema: {$tema}]\n  \"{$snippet}\"";
            }, $history);
            $historyContext = "\n\nRiwayat jurnal 30 hari terakhir:\n" . implode("\n\n", $lines);
        }

        $systemPrompt = <<<'PROMPT'
Kamu adalah teman reflektif yang sudah membaca seluruh jurnal harian pengguna ini selama berbulan-bulan.

Gaya analisamu:
- SANGAT PERSONAL — referensi pola, kemajuan, atau perjuangan spesifik dari riwayat kalau relevan
- HANGAT tapi JUJUR — seperti teman dekat yang peduli, bukan asisten generik atau motivator klise
- BERBASIS DATA — kalau ada pola yang terlihat (mis: mood turun di hari tertentu, tema yang berulang, kemajuan nyata), sebutkan eksplisit
- PRAKTIS — insight dan saran harus actionable dan spesifik ke situasi mereka, bukan nasihat umum

Balas HANYA dengan JSON valid. Gunakan bahasa Indonesia yang natural dan hangat.
PROMPT;

        $userPrompt = <<<PROMPT
Analisa jurnal ini secara mendalam. Balas HANYA dengan JSON berikut (tidak ada teks lain di luar JSON):

{
  "mood": "(1 frasa suasana hati yang nuansed dan presisi — bukan sekadar 'senang' atau 'sedih', contoh: 'bersemangat tapi sedikit cemas', 'lelah tapi lega', maksimal 10 kata)",
  "tema": "(tema-tema utama dipisah koma, maksimal 5 tema — pilih yang paling substantif dari isi jurnal)",
  "content": "(ringkasan naratif 3-4 kalimat — ceritakan kembali hari mereka seolah kamu menjelaskan ke orang ketiga, tangkap nuansa emosi dan konteksnya, jangan hanya listing fakta)",
  "insight": "(2-3 insight yang SPESIFIK dan PERSONAL — kalau ada pola dari riwayat yang relevan sebutkan langsung, contoh: 'Ini sudah kali ketiga dalam bulan ini kamu menyebut...', 'Berbeda dari dua minggu lalu ketika...' — jangan generik)",
  "saran": "(2-3 saran konkret dan spesifik untuk esok atau minggu depan — harus relevan langsung ke situasi mereka hari ini, bukan nasihat umum)"
}

Jurnal hari ini:
{$body}{$historyContext}
PROMPT;

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.3-70b-versatile',
                    'max_tokens' => 800,
                    'temperature' => 0.75,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if (! $response->successful()) {
                return null;
            }

            QuoteController::trackUsageFor($this->currentUser, $response->json('usage.total_tokens', 0));

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
