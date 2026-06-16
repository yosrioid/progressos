# ProgressOS — Code Standards

Reference ini dipakai untuk menjaga konsistensi di seluruh codebase. Semua development baru harus mengikuti pola yang sudah ada di sini. Jangan tambahkan abstraksi baru kecuali memang dibutuhkan.

---

## Bahasa

- **UI, toast, error message, label, placeholder, empty state**: semua dalam **Bahasa Inggris**.
- **Komentar kode**: boleh Inggris atau Indonesia, pilih satu dan konsisten per file.
- **Komunikasi dengan developer (CLAUDE.md, docs)**: Indonesia.

---

## Backend

### Response Format

Semua endpoint API mengembalikan response melalui `App\Support\ApiResponse`. **Jangan pakai `response()->json()` langsung** kecuali untuk `noContent()`.

```php
// ✅ Benar
return ApiResponse::ok(['habits' => $data, 'today' => $today]);
return ApiResponse::item('task', new TaskResource($task), 201, 'Task created.');
return ApiResponse::item('key_result', $this->formatKr($kr), 201);
return ApiResponse::paginated('tasks', ApiQuery::paginateSorted(...), resourceClass: TaskResource::class);
return response()->noContent(); // DELETE — ini boleh

// ❌ Salah
return response()->json(['habits' => $data]);
```

**Method yang tersedia:**

| Method | Kapan dipakai |
|--------|---------------|
| `ApiResponse::ok($payload)` | Read endpoints dan action endpoints (log, reorder, mark, dst) |
| `ApiResponse::item($key, $data, $status, $message)` | Store dan update — sertakan message konfirmasi |
| `ApiResponse::collection($key, $data)` | Koleksi non-paginated |
| `ApiResponse::paginated($key, $paginator, resourceClass:)` | Index endpoint dengan pagination |

**Payload key standar per modul:**

| Modul | Key |
|-------|-----|
| Daily Progress | `entry` |
| Work Logs | `log` |
| Tasks | `task` |
| Learning | `entry` |
| Milestones | `milestone` |
| Docs | `doc` |
| Projects | `project` |

### Validasi — Form Requests

Controller CRUD wajib menggunakan Form Request class di `app/Http/Requests/`. Jangan taruh validasi inline di controller kecuali untuk kasus khusus seperti action endpoint sederhana atau controller invokable.

```php
// ✅ Benar — gunakan Form Request
public function store(TaskRequest $request): JsonResponse
{
    $data = $request->validated();
    ...
}

// ❌ Salah — validasi inline untuk CRUD
public function store(Request $request): JsonResponse
{
    $data = $request->validate([...]);
    ...
}
```

Form Request yang sudah ada: `TaskRequest`, `WorkLogRequest`, `LearningEntryRequest`, `DailyProgressRequest`, `MilestoneRequest`, `DocRequest`, `ProjectRequest`, `ReferenceRequest`, `QuickCaptureRequest`, `SavedViewRequest`, `ApiTokenRequest`.

### API Resources

Setiap modul CRUD harus punya Resource di `app/Http/Resources/`. Resource dipakai di semua endpoint yang mengembalikan record (index, show, store, update).

```php
// ✅ Benar
return ApiResponse::item('task', new TaskResource($task->fresh()->load(['project', 'references'])));

// ❌ Salah — format manual tanpa Resource
return ApiResponse::ok(['task' => ['id' => $task->id, 'title' => $task->title, ...]]);
```

Resource yang sudah ada: `TaskResource`, `WorkLogResource`, `LearningEntryResource`, `DailyProgressResource`, `MilestoneResource`, `DocResource`, `DocFileResource`, `ProjectResource`.

> Habit, Goal, Notification — saat ini masih pakai raw array. Jika modul ini diperluas, tambahkan Resource-nya.

### Ownership & Authorization

Setiap controller yang mengakses data user harus:

1. Filter query dengan `ownedBy($request->user())` di `index`.
2. Panggil `$this->authorize('view'|'update'|'delete', $model)` di show/update/destroy.
3. Untuk controller sederhana tanpa policy, pakai `abort_if($model->user_id !== $request->user()->id, 403)`.

```php
// ✅ Pola standar
public function index(Request $request): JsonResponse
{
    $query = Task::ownedBy($request->user());
    return ApiResponse::paginated('tasks', ApiQuery::paginateSorted($query, $request, 'created_at', 20, [...]), resourceClass: TaskResource::class);
}

public function show(Request $request, Task $task): JsonResponse
{
    $this->authorize('view', $task);
    ...
}
```

### Pagination & Query

Semua index endpoint yang mengembalikan daftar record pakai `ApiQuery::paginateSorted()`. Jangan pakai `->paginate()` langsung.

```php
// ✅ Benar
ApiQuery::paginateSorted($query, $request, 'created_at', 20, ['created_at', 'updated_at', 'title'])

// ❌ Salah
$query->paginate(20)
```

### Services

Logic yang tidak murni CRUD (agregasi, sinkronisasi, generate puzzle, export, dsb.) taruh di `app/Services/`. Controller hanya boleh mengkoordinasi, bukan mengolah data.

Inject service ke method controller, bukan constructor, kecuali benar-benar dipakai di semua method.

### Struktur Route

- Semua route produktif ada di `routes/api/v1.php` di bawah middleware `auth:sanctum`.
- Gunakan `ability` middleware untuk pemisahan read/write/capture/reports.
- Throttle: `throttle:api-read` untuk GET, `throttle:api-write` untuk write operations.
- Nama resource pakai kebab-case: `work-logs`, `daily-progress`, `saved-views`.
- Custom action menggunakan nama deskriptif: `kanban`, `overdue-count`, `mark-all-read`.

---

## Frontend

### Mengambil Data dari API

Selalu gunakan `unwrap` helper dari `api.ts` saat mengekstrak body dari response axios. **Jangan pakai `.then(r => r.data)` atau `.then((r) => r.data)` secara manual.**

```typescript
import { api, unwrap } from '../api';

// ✅ Benar
const res = await api.get('/api/v1/habits').then(unwrap);
habits.value = res.habits;

// ❌ Salah
const res = await api.get('/api/v1/habits').then(r => r.data);
habits.value = res.habits;

// ❌ Juga salah
const res = await api.get('/api/v1/habits');
habits.value = res.data.habits;
```

### Toast & Error Handling

Gunakan `toast()` dari `feedback.ts` untuk semua feedback ke user. **Jangan pakai `alert()` atau `console.error()` untuk feedback user-facing.**

```typescript
import { toast } from '../feedback';

// ✅ Sukses
toast({ tone: 'success', title: 'Saved', message: 'Record has been updated.' });

// ✅ Error dari API
catch (e: any) {
  toast({ tone: 'error', title: e?.response?.data?.message ?? 'Failed to save' });
}

// ✅ Error form — pakai error ref yang ditampilkan di template
const error = ref('');
catch (e: any) {
  error.value = e.response?.data?.message || 'Could not save this record.';
  errors.value = e.response?.data?.errors || {};
}

// ❌ Salah
alert('Gagal menyimpan');
console.error(e);
```

**Kapan pakai toast vs error ref:**
- **Toast**: action yang bisa di-dismiss (delete, status update, reorder, mark done).
- **Error ref di template**: form submit — user perlu lihat error di konteks form yang bersangkutan.

### Silent Catch

Silent catch (`} catch { /* ignore */ }`) hanya boleh untuk operasi non-kritis yang tidak perlu feedback ke user (misalnya: save skor game yang gagal). Tambahkan komentar singkat kenapa silent.

```typescript
// ✅ Boleh dengan komentar alasan
try {
  await api.post('/api/v1/games', payload);
} catch {
  // score save is non-critical, game already ended
}
```

### Loading State

Dua pola loading yang dipakai di codebase — **pilih berdasarkan konteks**:

- **Skeleton loader** (`class="skeleton h-XX rounded-2xl"`): untuk halaman utama yang menampilkan konten utama (Records, RecordForm, RecordDetail, Activity, Analytics, TaskBoard).
- **Teks "Loading..."**: untuk modal, section kecil, atau komponen game yang tidak punya layout pra-loading.

```vue
<!-- ✅ Skeleton — untuk halaman utama -->
<div v-if="loading" class="grid gap-3">
  <div v-for="i in 4" :key="i" class="skeleton h-28 rounded-2xl" />
</div>

<!-- ✅ Teks — untuk section kecil / modal -->
<div v-if="loading" class="text-sm text-slate-400">Loading...</div>
```

### TypeScript

- Definisikan interface lokal untuk data yang dikembalikan API jika dipakai di lebih dari satu tempat.
- Gunakan union type untuk enum-like values: `type Level = 'easy' | 'medium' | 'hard'`.
- `any` boleh untuk data API yang strukturnya kompleks dan tidak worth di-type (terutama response aggregasi dan game state). Jangan pakai `any` untuk props komponen atau argument fungsi yang strukturnya jelas.
- Type cast `as any` hanya untuk edge case Vue computed yang TypeScript tidak bisa infer — beri komentar.

### Struktur Komponen Vue

```
<script setup lang="ts">
// 1. Import Vue (ref, computed, onMounted, dll)
// 2. Import Router
// 3. Import api, unwrap
// 4. Import feedback (toast, confirmAction)
// 5. Import komponen lokal
// 6. Import utils (format, records, dll)

// Interfaces / types lokal

// Reactive state (ref, computed)

// Functions

// Lifecycle (onMounted, onUnmounted)
</script>

<template>
  <!-- loading state -->
  <!-- empty state -->
  <!-- content -->
</template>
```

### Router

- Route list: `/resource` → `Records.vue` dengan props `{ type: 'module-name' }`.
- Route create: `/resource/create` → `RecordForm.vue`.
- Route detail: `/resource/:id` → `RecordDetail.vue`.
- Route edit: `/resource/:id/edit` → `RecordForm.vue`.
- Route khusus (board, settings, dll): nama yang deskriptif.
- Gunakan `meta: { guest: true }` untuk halaman yang tidak butuh auth.

---

## Hal yang Tidak Perlu Distandarisasi

Beberapa inkonsistensi yang ada di codebase **sengaja dibiarkan** karena tidak worth di-refactor:

- **Form Request untuk HabitController, GoalController**: validasinya sederhana dan inline masih mudah dibaca. Buat Form Request jika modul diperluas signifikan.
- **API Resource untuk Habit, Goal, Notification**: model ini dikembalikan langsung karena formatnya sederhana. Tambahkan Resource jika struktur response perlu berubah.
- **`any` di game components**: game state (grid, board, dsb.) memang kompleks dan typing penuh tidak menambah nilai.
- **Skeleton vs teks loading**: keduanya valid — ikuti pola yang sudah ada di file yang diedit.
