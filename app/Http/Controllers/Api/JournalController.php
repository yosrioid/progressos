<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Journal;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            'body'       => ['sometimes', 'string', 'max:10000'],
            'mood'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'tema'       => ['sometimes', 'nullable', 'string', 'max:200'],
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

    public function profile(Request $request)
    {
        $user = $request->user();
        $profileData = Configuration::getValue($user, 'journal', 'ai_profile', []);

        return ApiResponse::ok([
            'profile' => is_array($profileData) ? $profileData : [],
            'total_entries' => Journal::ownedBy($user)->count(),
        ]);
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

        // 14 hari terakhir dengan detail lebih banyak
        $history = Journal::ownedBy($user)
            ->where('id', '!=', $journal->id)
            ->where('date', '>=', now()->subDays(14)->toDateString())
            ->orderByDesc('date')
            ->limit(14)
            ->get(['date', 'mood', 'tema', 'body'])
            ->toArray();

        // Profil persisten yang dibangun AI dari seluruh riwayat
        $profileData = Configuration::getValue($user, 'journal', 'ai_profile', []);
        $profileText = is_array($profileData) ? ($profileData['text'] ?? '') : '';

        $this->currentUser = $user;
        $result = $this->callGroq($apiKey, $journal->body, $history, $profileText);

        if (! $result) {
            return ApiResponse::ok(['error' => 'ai_failed'], 'Gagal mendapatkan analisa dari AI. Coba lagi nanti.', 503);
        }

        $journal->update([
            'mood'        => $result['mood'],
            'tema'        => $result['tema'],
            'ai_content'  => $result['content'],
            'ai_insight'  => $result['insight'],
            'ai_saran'    => $result['saran'],
            'analyzed_at' => now(),
        ]);

        // Simpan profil yang diperbarui AI
        if (! empty($result['profile'])) {
            Configuration::setValue($user, 'journal', 'ai_profile', [
                'text'         => $result['profile'],
                'updated_at'   => now()->toDateString(),
                'entry_count'  => ($profileData['entry_count'] ?? 0) + 1,
            ]);
        }

        return ApiResponse::ok(['journal' => $this->format($journal)], 'Analisa selesai.');
    }

    private function callGroq(string $apiKey, string $body, array $history = [], string $profileText = ''): ?array
    {
        // Bangun konteks riwayat 14 hari
        $historyContext = '';
        if (! empty($history)) {
            $lines = array_map(function ($h) {
                $snippet = mb_substr($h['body'], 0, 200);
                $mood = $h['mood'] ?: '-';
                $tema = $h['tema'] ?: '-';

                return "• {$h['date']} [mood: {$mood}] [tema: {$tema}]\n  \"{$snippet}\"";
            }, $history);
            $historyContext = "\n\n=== JURNAL 14 HARI TERAKHIR ===\n" . implode("\n\n", $lines);
        }

        // Profil persisten (memori jangka panjang)
        $profileContext = '';
        if (! empty($profileText)) {
            $profileContext = "\n\n=== PROFIL PENGGUNA — MEMORI JANGKA PANJANGMU ===\n{$profileText}";
        } else {
            $profileContext = "\n\n=== PROFIL PENGGUNA ===\n(Belum ada — ini adalah analisa pertama. Buat profil awal dari jurnal ini.)";
        }

        $systemPrompt = <<<'PROMPT'
Kamu adalah psikolog personal dan teman reflektif yang sudah mengenal penulis jurnal ini dengan sangat dalam. Kamu memiliki memori lengkap tentang siapa mereka — pola pikir, kebiasaan, perjuangan, pertumbuhan, dan hal-hal kecil yang mereka ceritakan dari waktu ke waktu.

PRINSIP ANALISA:
1. SANGAT PERSONAL — gunakan profil dan riwayat. Tidak boleh ada kalimat yang bisa berlaku untuk siapa saja.
2. REFERENSI EKSPLISIT — kalau ada pola atau perubahan, sebutkan dengan spesifik: tanggal, berapa kali, tren naik/turun.
3. EMOSI NUANSED — tangkap kompleksitas. "Lelah tapi lega", "semangat dengan rasa takut di baliknya", dll.
4. SARAN KONKRET — bukan "istirahat yang cukup" tapi "besok coba blok 2 jam tanpa notif di pagi hari sebelum buka laptop".
5. HANGAT TAPI JUJUR — teman yang peduli, bukan motivator klise atau terapis kaku.
6. UPDATE PROFIL — setelah analisa, perbarui profil pengguna dengan temuan baru. Profil adalah memorimu jangka panjang — semakin akurat semakin bagus.

Balas HANYA dengan JSON valid. Bahasa Indonesia natural, bukan formal.
PROMPT;

        $userPrompt = <<<PROMPT
Analisa jurnal hari ini secara mendalam. Balas HANYA dengan JSON berikut — tidak ada teks apapun di luar JSON:

{
  "mood": "(1 frasa yang nuansed dan presisi, maks 10 kata — bukan sekadar positif/negatif, tangkap lapisan emosinya)",
  "tema": "(tema-tema paling substantif dari jurnal ini, dipisah koma, maks 5 tema — pilih yang benar-benar inti)",
  "content": "(3-4 kalimat narasi hangat — ceritakan kembali hari mereka seolah kamu menjelaskan ke teman, tangkap nuansa emosi dan konteksnya, bukan listing fakta)",
  "insight": "(2-3 insight yang WAJIB spesifik dan personal — referensi pola dari profil atau riwayat secara eksplisit, contoh: 'Ini sudah ketiga kalinya minggu ini kamu menyebut...', 'Polamu biasanya X, tapi hari ini berbeda karena...', 'Dibanding entry tanggal [X]...' — TIDAK BOLEH generik)",
  "saran": "(2-3 saran yang BENAR-BENAR KONKRET dan spesifik ke situasi mereka hari ini — bukan 'jaga kesehatan', tapi langkah nyata yang bisa dilakukan besok atau minggu ini)",
  "profile": "(profil pengguna versi TERBARU — gabungkan profil yang sudah ada dengan insight baru dari jurnal hari ini. Format bebas tapi informatif: siapa mereka, pola emosi dan produktivitas, tema yang sering muncul, perjuangan berulang, pencapaian, gaya hidup, preferensi, dan hal penting lain. 300-500 kata, bahasa Indonesia natural)"
}

=== JURNAL HARI INI ===
{$body}{$profileContext}{$historyContext}
PROMPT;

        try {
            $response = Http::timeout(45)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'       => 'llama-3.3-70b-versatile',
                    'max_tokens'  => 1500,
                    'temperature' => 0.75,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userPrompt],
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('Groq journal analyze failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return null;
            }

            QuoteController::trackUsageFor($this->currentUser, $response->json('usage.total_tokens', 0));

            $content = $response->json('choices.0.message.content', '');

            // Ekstrak JSON dari response (kadang ada teks sebelum/sesudah)
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (isset($parsed['mood'], $parsed['tema'], $parsed['content'], $parsed['insight'], $parsed['saran'])) {
                    return $parsed;
                }
            }

            Log::warning('Groq journal: failed to parse JSON', ['content' => $content]);
        } catch (\Throwable $e) {
            Log::error('Groq journal analyze exception', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function format(Journal $journal): array
    {
        return [
            'id'          => $journal->id,
            'date'        => $journal->date->toDateString(),
            'body'        => $journal->body,
            'mood'        => $journal->mood,
            'tema'        => $journal->tema,
            'ai_content'  => $journal->ai_content,
            'ai_insight'  => $journal->ai_insight,
            'ai_saran'    => $journal->ai_saran,
            'analyzed_at' => $journal->analyzed_at?->toIso8601String(),
            'created_at'  => $journal->created_at->toIso8601String(),
        ];
    }
}
