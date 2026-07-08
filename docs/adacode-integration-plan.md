# Plan: Integrasi AdaCode.ai sebagai Alternatif Provider AI

> Status: Planning — belum dimulai
> Branch target: `feature/adacode-integration`

---

## Ringkasan

ProgressOS saat ini menggunakan **Groq** untuk semua fitur AI (chat, quote, journaling, dll). AdaCode.ai ingin ditambahkan sebagai **opsi provider alternatif** dengan kemampuan:

1. **Pilih provider** (Groq vs AdaCode) via konfigurasi.
2. **Journaling tetap Groq** — tidak terpengaruh pilihan provider.
3. **Pengecekan quota/token habis** → kirim notifikasi ke user bahwa plan/kuota sudah habis.

---

## 1. Latar Belakang

### Yang Sudah Ada

#### Backend
- `ChatController` — chat AI dengan Groq (model `llama-3.1-8b-instant`)
- `JournalController` — analisa jurnal dengan Groq (model `llama-3.3-70b-versatile`)
- `QuoteController` — daily quote dengan Groq (model `llama-3.1-8b-instant`)
- Semua config Groq disimpan di `Configuration` model (group `quote`/`groq`, key `groq`)
- Usage tracking via `QuoteController::trackUsageFor()`

#### Frontend
- **Vue 3 + Pinia** store architecture (`resources/js/vue/stores/`)
  - `auth.ts` — authentication state management
  - `configuration.ts` — app configuration (groups: general, appearance, notifications)
  - `inbox.ts` — inbox/notification state
  - `privacy.ts` — privacy/PIN settings
- **Vue Router** dengan dynamic routes (`resources/js/vue/router.ts`)
- **Views** (50+ halaman): Dashboard, Chat, Journals, Goals, Habits, Projects, Analytics, Records, Lists, Money, Reports, Configuration, Games, Search, WeeklyReview, dll.
- **Components**: ChatBubble, WorkTimer, DailyQuote, WysiwygEditor, DatePicker, PinGate, BarChart, DailyProgressLists
- **Composables**: `useAsyncData`, `useClickOutside`, `useConversationSession`
- **API layer**: `resources/js/vue/api.ts` — centralized axios wrapper dengan interceptors
- **Feedback utilities**: `resources/js/vue/feedback.ts` — `toast()`, `confirmAction()`
- **Router**: `resources/js/vue/router.ts` — route guards, lazy loading, auth checks
- **Stores**: Pinia stores untuk auth, configuration, inbox, privacy

#### Routes & Pages
- `/dashboard` — main dashboard (Activity view)
- `/chat` — AI chat with session management
- `/journal` — journal CRUD with AI analysis
- `/goals`, `/habits`, `/projects`, `/records`, `/lists` — productivity features
- `/analytics`, `/reports` — data visualization
- `/configuration` — admin settings (backup, sync, quotes, email, SSO)
- `/games` — game hub (2048, Memory Match, Minesweeper, Sudoku, Melody Memory, Pitch Trainer)
- `/search` — global search
- `/inbox` — notifications center

### Kebutuhan Baru

- Tambah provider **AdaCode.ai** yang kompatibel OpenAI SDK
- User bisa pilih provider via config (Groq atau AdaCode)
- Journaling **tetap pakai Groq** (tidak bisa diganti)
- Deteksi **quota habis / plan expired** → notifikasi ke user

---

## 2. Spesifikasi AdaCode.ai

### Kompatibilitas

- **OpenAI-compatible** — format request/response sama persis
- Base URL: `https://api.adacode.ai/v1`
- Auth: `Authorization: Bearer sk-ac-xxx`
- Endpoint utama: `POST /v1/chat/completions`
- Streaming: SSE (Server-Sent Events)
- Endpoint health: `GET /health`
- Endpoint models: `GET /v1/models`

### Model Tersedia (yang relevan untuk ProgressOS)

| Model | Provider | Context | Max Output | Use Case |
|-------|----------|---------|------------|----------|
| `claude-sonnet-4-6` | Anthropic | 200K | 66K | Chat, coding, analisis |
| `gpt-5.3` | OpenAI | 128K | 16K | Reasoning, general |
| `gemini-3-flash` | Google | 1M | 66K | Context panjang, cepat |
| `glm-4.7` | Z.AI | 203K | 16K | Multilingual |
| `claude-haiku-3-5` | Anthropic | 200K | 8K | Chat ringan, cepat |
| `qwen3.6-flash` | Alibaba | 1M | 8K | Ultra-cepat, hemat |

### Error Codes Penting

| Code | Deskripsi |
|------|-----------|
| `401` | API key tidak valid |
| **`403`** | **Kuota habis atau akses ditolak** ← *ini yang kita perlukan* |
| `404` | Model tidak ditemukan |
| `429` | Rate limit exceeded |
| `500` | Internal error |

### Pricing

- Pay-As-You-Go atau Coding Plan
- Token-based billing (input + output)
- Bisa cek sisa quota via dashboard AdaCode.ai

---

## 3. Design Decision

### Q: Journaling tetap Groq?
**A: Ya.** Alasan:
- Prompt journaling sangat kompleks (psikolog personal, memori jangka panjang, profil dinamis)
- Butuh model besar (`llama-3.3-70b-versatile`)
- Groq sudah terbukti stabil untuk use case ini
- AdaCode punya Claude Sonnet yang juga bagus, tapi untuk konsistensi dan performa, biarkan Groq dulu

### Q: Fitur apa yang boleh pakai AdaCode?
**A:** Chat (general + project), Daily Quote, Quick Capture AI parsing
**Tidak boleh:** Journaling (tetap Groq)

### Q: Bagaimana cek quota habis?
**A:** Via HTTP status code `403` dari API. AdaCode mengembalikan:
```json
{
  "error": {
    "message": "Quota exceeded",
    "type": "billing_error",
    "code": "billing_error"
  }
}
```

Kita bisa detect ini dan trigger notifikasi.

### Q: Provider selection — global atau per-feature?
**A: Global + override per-feature.**
- Default provider bisa dipilih di config (Groq atau AdaCode)
- Journaling selalu override ke Groq (hardcoded)
- Ini memberi fleksibilitas tanpa mengubah banyak code

---

## 4. Arsitektur

### High-Level Flow

```
┌─────────────────────────────────────────────┐
│                  Frontend                    │
│  ┌──────────┐  ┌──────────┐  ┌───────────┐ │
│  │  Chat    │  │  Quote   │  │  Settings │ │
│  └────┬─────┘  └────┬─────┘  └─────┬─────┘ │
│       │              │              │       │
│       ▼              ▼              ▼       │
│  ┌─────────────────────────────────────┐   │
│  │   Provider Selector (config store)  │   │
│  │   - ai.provider: "groq" | "adacode" │   │
│  │   - ai.model: "claude-sonnet-4-6"   │   │
│  │   - ai.api_key: "sk-ac-..."         │   │
│  └─────────────────────────────────────┘   │
└─────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────┐
│              Backend (Laravel)               │
│                                             │
│  ┌───────────────────────────────────────┐  │
│  │  AI Provider Manager (Service)        │  │
│  │  - resolveProvider()                  │  │
│  │  - callProvider($provider, $payload)  │  │
│  │  - detectQuotaExceeded($response)     │  │
│  └───────────────────────────────────────┘  │
│       │                     │               │
│       ▼                     ▼               │
│  ┌──────────┐        ┌──────────┐          │
│  │ Groq     │        │ AdaCode  │          │
│  │ Adapter  │        │ Adapter  │          │
│  └────┬─────┘        └────┬─────┘          │
│       │                   │                │
│       ▼                   ▼                │
│  ┌─────────���─────────────────────────────┐ │
│  │  Quota Checker                        │ │
│  │  - track usage                        │ │
│  │  - detect 403 from AdaCode            │ │
│  │  - create notification                │ │
│  └───────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────┐
│           Notifications (DB + UI)            │
│  - "Kuota AdaCode habis. Upgrade plan atau  │
│   beralih ke Groq."                         │
│  - "Daily usage: 1,234 / 10,000 tokens"     │
└─────────────────────────────────────────────┘
```

---

## 5. Implementation Plan

### Phase 1: Backend — Core Infrastructure

#### 5.1. Config Structure

Tambahkan config baru di `Configuration` model:

```php
// Group: 'ai', Key: 'provider_config'
[
    'provider' => 'groq',          // 'groq' | 'adacode'
    'model' => 'claude-sonnet-4-6', // model AdaCode
    'api_key' => 'sk-ac-xxx',      // API key AdaCode
    'groq_api_key' => 'gsk-xxx',   // API key Groq (tetap ada)
    'journal_provider' => 'groq',  // hardcoded, tidak bisa diubah
]
```

**Files to create/modify:**
- `app/Models/Configuration.php` — tambah key baru
- `config/ai.php` — tambah config file baru
- `.env.example` — tambah vars:
  ```env
  AI_PROVIDER=groq
  ADACODE_API_KEY=
  ADACODE_MODEL=claude-sonnet-4-6
  GROQ_API_KEY=
  ```

#### 5.2. AI Provider Manager Service

**File baru:** `app/Services/AiProviderManager.php`

```php
class AiProviderManager
{
    /**
     * Resolve provider berdasarkan config.
     * Untuk journaling, selalu return 'groq'.
     */
    public function resolveProvider(string $feature): string
    {
        if ($feature === 'journal') {
            return 'groq';
        }

        return Configuration::getValue(null, 'ai', 'provider_config', [])['provider'] ?? 'groq';
    }

    /**
     * Call provider dengan adapter yang sesuai.
     * Return: ['content' => ..., 'tokens' => ..., 'provider' => ...]
     */
    public function call(string $provider, array $payload): array
    {
        return match ($provider) {
            'groq' => $this->callGroq($payload),
            'adacode' => $this->callAdaCode($payload),
            default => throw new InvalidArgumentException("Unknown provider: {$provider}")
        };
    }

    /**
     * Detect jika response menunjukkan quota habis.
     */
    public function isQuotaExceeded(Response $response): bool
    {
        return $response->status() === 403
            && $response->json('error.code') === 'billing_error';
    }

    /**
     * Track usage ke Configuration.
     */
    public function trackUsage($user, int $tokens, string $provider): void
    {
        // simpan ke Configuration group 'ai', key 'usage'
    }
}
```

#### 5.3. AdaCode Adapter

**File baru:** `app/Services/AiAdapters/AdaCodeAdapter.php`

```php
class AdaCodeAdapter
{
    public static function chat(string $apiKey, string $model, array $messages, int $maxTokens = 1024): ?array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.adacode.ai/v1/chat/completions', [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'temperature' => 0.8,
                    'messages' => $messages,
                ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'status' => $response->status(),
                    'error_code' => $response->json('error.code'),
                    'error_message' => $response->json('error.message'),
                ];
            }

            return [
                'success' => true,
                'content' => $response->json('choices.0.message.content', ''),
                'tokens' => $response->json('usage.total_tokens', 0),
                'provider' => 'adacode',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
```

#### 5.4. Quota Notification Service

**File baru:** `app/Services/QuotaNotificationService.php`

```php
class QuotaNotificationService
{
    /**
     * Kirim notifikasi ke user jika quota habis.
     */
    public function notifyQuotaExceeded($user, string $provider): void
    {
        Notification::create([
            'user_id' => $user->id,
            'type' => 'quota_exceeded',
            'title' => "Kuota {$provider} Habis",
            'body' => "Kuota API {$provider} Anda sudah habis. "
                    . ($provider === 'adacode'
                        ? "Silakan upgrade plan di adacode.ai atau beralih ke Groq."
                        : "Silakan periksa quota Groq Anda."),
            'data' => [
                'provider' => $provider,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
```

---

### Phase 2: Integration — Update Existing Controllers

#### 5.5. Update `ChatController`

**Yang berubah:**
- Ganti hardcoded Groq call dengan `AiProviderManager::call()`
- Tambah deteksi quota exceeded → kirim notifikasi
- Support fallback ke Groq jika AdaCode quota habis

**Contoh perubahan di `sendMessage()`:**

```php
public function sendMessage(Request $request, ChatSession $chatSession)
{
    // ... (validasi user & save user message tetap sama)

    $provider = $this->aiManager->resolveProvider('chat');
    $config = Configuration::getValue(null, 'ai', 'provider_config', []);
    $apiKey = $config['api_key'] ?? null;
    $model = $config['model'] ?? 'claude-sonnet-4-6';

    // Jika provider ada tapi key kosong, fallback ke Groq
    if (! $apiKey && $provider === 'adacode') {
        $provider = 'groq';
        $apiKey = Configuration::getValue(null, 'quote', 'groq', [])['api_key'] ?? null;
        $model = 'llama-3.1-8b-instant';
    }

    // ... (build messages tetap sama)

    $result = $this->aiManager->call($provider, [
        'apiKey' => $apiKey,
        'model' => $model,
        'messages' => $messages,
        'maxTokens' => 600,
        'temperature' => 0.8,
    ]);

    // Cek quota exceeded
    if ($result['success'] === false && $result['status'] === 403) {
        $this->quotaNotifier->notifyQuotaExceeded($user, $provider);

        return ApiResponse::ok(
            ['error' => 'quota_exceeded'],
            "Kuota {$provider} habis. Silakan upgrade atau beralih provider.",
            403
        );
    }

    // Jika gagal (bukan quota), fallback ke Groq
    if (! $result['success'] && $provider === 'adacode') {
        $result = $this->fallbackToGroq($apiKey, $messages, $user);
    }

    // ... (save assistant message tetap sama)
}
```

#### 5.6. Update `QuoteController`

**Yang berubah:**
- Support provider selection via config
- Default tetap Groq (backward compatible)
- Tambah deteksi quota → notifikasi

**Contoh perubahan di `daily()`:**

```php
public function daily(Request $request)
{
    $user = $request->user();
    $aiConfig = Configuration::getValue(null, 'ai', 'provider_config', []);
    $provider = $aiConfig['provider'] ?? 'groq';

    if ($provider === 'adacode') {
        $apiKey = $aiConfig['api_key'] ?? null;
        $model = $aiConfig['model'] ?? 'claude-sonnet-4-6';
    } else {
        $quoteConfig = $this->quoteConfig($user);
        $apiKey = $quoteConfig['api_key'] ?? null;
        $model = 'llama-3.1-8b-instant';
    }

    // ... (generate quote dengan provider yang dipilih)
}
```

#### 5.7. `JournalController` — TIDAK BERUBAH

Journaling tetap menggunakan Groq secara hardcoded. Tidak ada perubahan di controller ini.

---

### Phase 3: Frontend — UI Provider Selector

#### 5.8. Config Store Update

**File:** `resources/js/vue/stores/configuration.ts` (sudah ada)

File ini sudah ada dan mengelola configuration groups (general, appearance, notifications). Tambahkan group baru untuk AI:

```typescript
// Tambahkan di defaultGroups:
ai: {
  provider: 'groq',          // 'groq' | 'adacode'
  model: 'claude-sonnet-4-6',
  api_key: '',               // AdaCode API key
  groq_api_key: '',          // Groq API key (existing)
  journal_provider: 'groq',  // hardcoded, read-only
  usage_requests: 0,         // daily usage tracking
  usage_tokens: 0,
  request_limit: 10000,
},
```

**Getter yang perlu ditambahkan:**
```typescript
getters: {
  // ... existing getters
  aiProvider: (state) => state.groups.ai?.provider || 'groq',
  aiModel: (state) => state.groups.ai?.model || 'claude-sonnet-4-6',
  aiApiKey: (state) => state.groups.ai?.api_key || '',
  groqApiKey: (state) => state.groups.ai?.groq_api_key || '',
  isAdaCode: (state) => state.groups.ai?.provider === 'adacode',
  usagePercentage: (state) => {
    const usage = state.groups.ai?.usage_requests || 0;
    const limit = state.groups.ai?.request_limit || 10000;
    return Math.round((usage / limit) * 100);
  },
}
```

#### 5.9. Settings Page — Provider Selector

**File:** `resources/js/vue/views/Configuration.vue` (sudah ada, 787 baris)

Tambahkan section baru di Configuration.vue (setelah Daily Quote section, ~line 780):

```vue
<!-- ── AI Provider ── -->
<section class="card overflow-hidden p-0">
  <button type="button" class="flex w-full items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-4 text-left" @click="toggleGroup('ai')">
    <div>
      <p class="font-extrabold text-slate-900">AI Provider</p>
      <span class="mt-1 block text-sm font-medium text-slate-500">Pilih provider untuk chat & quote. Journaling tetap Groq.</span>
    </div>
    <span class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500" :class="openGroups.ai ? 'rotate-180' : ''">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
    </span>
  </button>
  <div v-if="openGroups.ai" class="divide-y divide-slate-100 p-5 space-y-4">
    <!-- Provider dropdown -->
    <div class="grid gap-3 md:grid-cols-[16rem_1fr] md:items-center">
      <span class="font-extrabold text-slate-800">Provider</span>
      <select v-model="groupSettings.ai.provider" class="field">
        <option value="groq">Groq (default, untuk journaling & chat)</option>
        <option value="adacode">AdaCode.ai (Claude Sonnet, GPT-5.3, dll)</option>
      </select>
    </div>

    <!-- AdaCode API Key -->
    <div v-if="groupSettings.ai.provider === 'adacode'" class="grid gap-3 md:grid-cols-[16rem_1fr] md:items-center">
      <span class="font-extrabold text-slate-800">AdaCode API Key</span>
      <input v-model="groupSettings.ai.api_key" class="field" type="password" placeholder="sk-ac-..." />
    </div>

    <!-- Model selector (AdaCode only) -->
    <div v-if="groupSettings.ai.provider === 'adacode'" class="grid gap-3 md:grid-cols-[16rem_1fr] md:items-center">
      <span class="font-extrabold text-slate-800">Model</span>
      <select v-model="groupSettings.ai.model" class="field">
        <option value="claude-sonnet-4-6">Claude Sonnet 4.6 (recommended)</option>
        <option value="gpt-5.3">GPT-5.3</option>
        <option value="gemini-3-flash">Gemini 3 Flash</option>
        <option value="glm-4.7">GLM 4.7</option>
        <option value="claude-haiku-3-5">Claude Haiku 3.5</option>
        <option value="qwen3.6-flash">Qwen 3.6 Flash</option>
      </select>
    </div>

    <!-- Info box -->
    <div class="rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:bg-blue-900/20 dark:text-blue-400">
      ℹ️ Journaling tetap menggunakan Groq dan tidak dapat diubah.
    </div>

    <!-- Save button -->
    <div class="flex justify-end pt-2">
      <button class="btn btn-primary" @click="saveAiConfig">Save AI Settings</button>
    </div>
  </div>
</section>
```

**Script additions:**
```typescript
const openGroups = ref({ 
  // ... existing groups
  ai: false,
});

async function saveAiConfig() {
  try {
    const data: any = await api.put('/api/admin/configuration/settings', groupSettings.value).then(unwrap);
    groupSettings.value = { ...groupSettings.value, ...(data.groups || {}) };
    configuration.applyGroups(data.groups || {});
    toast({ tone: 'success', title: 'AI settings saved' });
  } catch (e: any) {
    toast({ tone: 'error', title: 'Failed to save', message: e?.response?.data?.message ?? 'Terjadi kesalahan.' });
  }
}
```

#### 5.10. Quota Status Component

**File baru:** `resources/js/vue/components/AiQuotaStatus.vue`

Tampil di navbar/sidebar:
```vue
<script setup lang="ts">
import { computed } from 'vue';
import { useConfigurationStore } from '../stores/configuration';

const config = useConfigurationStore();

const usagePercent = computed(() => {
  const usage = config.groups.ai?.usage_requests || 0;
  const limit = config.groups.ai?.request_limit || 10000;
  return Math.round((usage / limit) * 100);
});

const statusColor = computed(() => {
  const pct = usagePercent.value;
  if (pct >= 100) return 'text-red-600';
  if (pct >= 80) return 'text-orange-600';
  if (pct >= 50) return 'text-yellow-600';
  return 'text-green-600';
});

const isExceeded = computed(() => usagePercent.value >= 100);
</script>

<template>
  <div v-if="config.groups.ai?.provider === 'adacode'" class="flex items-center gap-2">
    <span :class="statusColor" class="text-xs font-bold">
      {{ isExceeded ? '⛔ HABIS' : `🤖 ${usagePercent}%` }}
    </span>
    <span v-if="!isExceeded" class="text-xs text-slate-400">
      {{ config.groups.ai.usage_requests }}/{{ config.groups.ai.request_limit }}
    </span>
  </div>
</template>
```

#### 5.11. Quota Exceeded Notification

**File baru:** `resources/js/vue/components/QuotaExceededBanner.vue`

Tampil saat user kena quota exceeded (global banner di atas semua halaman):
```vue
<script setup lang="ts">
import { ref } from 'vue';
import { api, unwrap } from '../api';
import { toast } from '../feedback';
import { useConfigurationStore } from '../stores/configuration';

const config = useConfigurationStore();
const show = ref(false);

async function dismiss() {
  show.value = false;
  localStorage.setItem('quota_banner_dismissed', '1');
}

async function switchToGroq() {
  config.groups.ai.provider = 'groq';
  await api.put('/api/admin/configuration/settings', config.groups);
  toast({ tone: 'success', title: 'Switched to Groq', message: 'Chat dan quote sekarang menggunakan Groq.' });
  dismiss();
}
</script>

<template>
  <Transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
  >
    <div v-if="show" class="fixed top-0 left-0 right-0 z-50 bg-red-600 text-white px-4 py-2">
      <div class="max-w-6xl mx-auto flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <span class="text-lg">⛔</span>
          <div>
            <p class="text-sm font-extrabold">Kuota AdaCode Habis!</p>
            <p class="text-xs text-red-100">Silakan upgrade plan atau beralih ke Groq.</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0 text-xs" @click="switchToGroq">
            Beralih ke Groq
          </button>
          <a href="https://adacode.ai/billing" target="_blank" class="btn bg-white/20 hover:bg-white/30 text-white border-0 text-xs">
            Upgrade Plan
          </a>
          <button class="text-white/70 hover:text-white" @click="dismiss">×</button>
        </div>
      </div>
    </div>
  </Transition>
</template>
```

**Integrasi ke App.vue:**
Tambahkan di `resources/js/vue/App.vue` (sebelum router-view):
```vue
<QuotaExceededBanner />
```

---

### Phase 4: Testing & Validation

#### 5.12. Backend Tests

**File baru:** `tests/Feature/AiProviderManagerTest.php`

```php
it('resolves adacode provider for chat', function () {
    Configuration::setValue(null, 'ai', 'provider_config', ['provider' => 'adacode']);
    
    $manager = app(AiProviderManager::class);
    expect($manager->resolveProvider('chat'))->toBe('adacode');
});

it('always resolves groq for journaling', function () {
    Configuration::setValue(null, 'ai', 'provider_config', ['provider' => 'adacode']);
    
    $manager = app(AiProviderManager::class);
    expect($manager->resolveProvider('journal'))->toBe('groq');
});

it('detects quota exceeded from adacode', function () {
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('status')->andReturn(403);
    $response->shouldReceive('json')->with('error.code')->andReturn('billing_error');
    
    $manager = app(AiProviderManager::class);
    expect($manager->isQuotaExceeded($response))->toBe(true);
});
```

**File baru:** `tests/Feature/AdaCodeAdapterTest.php`

```php
it('calls adacode api correctly', function () {
    Http::fake([
        'api.adacode.ai/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello']]],
            'usage' => ['total_tokens' => 50],
        ], 200),
    ]);

    $result = AdaCodeAdapter::chat('sk-ac-test', 'claude-sonnet-4-6', [
        ['role' => 'user', 'content' => 'Hi']
    ]);

    expect($result['success'])->toBe(true);
    expect($result['content'])->toBe('Hello');
    expect($result['tokens'])->toBe(50);
});

it('returns quota exceeded status', function () {
    Http::fake([
        'api.adacode.ai/*' => Http::response([
            'error' => [
                'message' => 'Quota exceeded',
                'code' => 'billing_error',
            ],
        ], 403),
    ]);

    $result = AdaCodeAdapter::chat('sk-ac-test', 'claude-sonnet-4-6', [
        ['role' => 'user', 'content' => 'Hi']
    ]);

    expect($result['success'])->toBe(false);
    expect($result['status'])->toBe(403);
    expect($result['error_code'])->toBe('billing_error');
});
```

**Update existing tests:**
- `tests/Feature/CoreFlowsTest.php` — tambah test untuk chat dengan AdaCode
- `tests/Feature/QuoteControllerTest.php` — tambah test untuk quote dengan AdaCode

#### 5.13. Frontend Tests

**File baru:** `tests/e2e/adacode-settings.spec.ts`

```typescript
test('user can switch provider to adacode', async ({ page }) => {
  await page.goto('/settings');
  await page.getByLabel('AI Provider').selectOption('adacode');
  await page.getByLabel('AdaCode API Key').fill('sk-ac-test123');
  await page.getByRole('button', { name: 'Save' }).click();
  
  await expect(page.getByText('Settings saved')).toBeVisible();
});

test('quota exceeded notification shows when api returns 403', async ({ page }) => {
  // mock API response 403
  await page.route('**/api/v1/chat/**', route => {
    route.fulfill({
      status: 403,
      body: JSON.stringify({ error: { code: 'billing_error' } })
    });
  });
  
  await page.goto('/chat');
  await page.getByRole('textbox').fill('test message');
  await page.getByRole('button', { name: 'Send' }).click();
  
  await expect(page.getByText('Kuota AdaCode habis')).toBeVisible();
});
```

---

### Phase 5: Migration & Rollout

#### 5.14. Database Migration

**File baru:** `database/migrations/2026_07_07_000001_add_ai_provider_config_to_configurations.php`

```php
public function up(): void
{
    // Tidak perlu migration baru — semua disimpan di Configuration model
    // Cukup tambah key baru di config table via seed/app
}
```

#### 5.15. Seed Default Config

**File baru:** `database/seeders/AiConfigSeeder.php`

```php
public function run(): void
{
    Configuration::setValue(null, 'ai', 'provider_config', [
        'provider' => 'groq',      // default tetap Groq
        'model' => 'claude-sonnet-4-6',
        'api_key' => null,         // user isi manual
        'groq_api_key' => null,    // tetap ada
    ]);
}
```

#### 5.16. Rollout Strategy

1. **Deploy Phase 1-2** (backend) → fitur tersedia tapi UI belum ada
2. **Deploy Phase 3** (frontend) → user bisa switch provider via settings
3. **Monitor** → lihat logs, error rates, quota usage
4. **Deprecate** (opsional) → jika AdaCode terbukti lebih baik, bisa pertimbangkan switch default

---

## 6. Estimasi Effort

| Phase | Task | Estimasi |
|-------|------|----------|
| **1** | Config structure + AiProviderManager + AdaCodeAdapter + QuotaNotificationService | 4-6 jam |
| **2** | Update ChatController + QuoteController | 3-4 jam |
| **3** | Frontend: Settings UI + QuotaStatus + Notification | 4-6 jam |
| **4** | Backend tests + Frontend tests | 4-6 jam |
| **5** | Migration + Seed + Deployment | 1-2 jam |
| **Total** | | **16-24 jam (≈ 2-3 hari kerja)** |

---

## 7. Risk Assessment

| Risk | Severity | Mitigation |
|------|----------|------------|
| AdaCode API down | Medium | Fallback ke Groq otomatis |
| Quota detection tidak akurat | Medium | Log semua response 403, manual review |
| Breaking change untuk user yang sudah pakai Groq | Low | Default tetap Groq, backward compatible |
| Token tracking tidak konsisten antara provider | Low | Normalisasi token count di AiProviderManager |
| User lupa switch provider → tetap pakai Groq | Low | UI reminder di settings |

---

## 8. What's NOT Included (Out of Scope)

- ~~Journaling bisa pakai AdaCode~~ (tetap Groq, hardcoded)
- ~~Streaming response~~ (bisa ditambahkan di Phase berikutnya)
- ~~Multi-provider per feature~~ (hanya global + journal override)
- ~~Cost analytics dashboard~~ (bisa ditambahkan nanti)
- ~~Auto-switch provider saat quota habis~~ (manual switch via UI)

---

## 9. Checklist Implementasi

### Backend
- [ ] Buat `config/ai.php`
- [ ] Buat `app/Services/AiProviderManager.php`
- [ ] Buat `app/Services/AiAdapters/AdaCodeAdapter.php`
- [ ] Buat `app/Services/QuotaNotificationService.php`
- [ ] Update `app/Http/Controllers/Api/ChatController.php`
- [ ] Update `app/Http/Controllers/Api/QuoteController.php`
- [ ] Buat `tests/Feature/AiProviderManagerTest.php`
- [ ] Buat `tests/Feature/AdaCodeAdapterTest.php`
- [ ] Update `.env.example`

### Frontend
- [x] **Scan project selesai** — ditemukan: Vue 3 + Pinia + Vue Router, 50+ views, API layer, composable architecture
- [ ] Update `resources/js/vue/stores/configuration.ts` — tambah group `ai`
- [ ] Update `resources/js/vue/views/Configuration.vue` — tambah AI Provider section (~line 780)
- [ ] Buat `resources/js/vue/components/AiQuotaStatus.vue`
- [ ] Buat `resources/js/vue/components/QuotaExceededBanner.vue`
- [ ] Update `resources/js/vue/App.vue` — include QuotaExceededBanner
- [ ] Buat `tests/e2e/adacode-settings.spec.ts`

### Documentation
- [ ] Update `docs/api.md` dengan endpoint baru
- [ ] Update `docs/PROJECT_CONTEXT.md` dengan status integrasi AdaCode
- [ ] Update `README.md` dengan env vars baru

---

## 10. Next Steps

1. ✅ **Planning selesai** (dokumen ini)
2. ⏳ **Review & approval** dari user
3. ⏳ **Buat branch** `feature/adacode-integration`
4. ⏳ **Implement Phase 1** (backend core)
5. ⏳ **Implement Phase 2** (integration)
6. ⏳ **Implement Phase 3** (frontend)
7. ⏳ **Testing & QA**
8. ⏳ **Deploy & monitor**

---

> **Catatan:** Dokumen ini bersifat living document — akan di-update seiring implementasi berjalan.
