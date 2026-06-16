# UI Patterns & CSS Guide

Semua class yang dipakai di ProgressOS sudah didefinisikan di `resources/css/app.css` dalam `@layer components`. Jangan buat varian baru kecuali pattern yang ada tidak mencukupi.

---

## CSS Utility Classes

### `.card`

Container konten utama. Pakai untuk section, panel, dan blok konten.

```vue
<section class="card p-5">
  ...
</section>

<!-- Dengan overflow untuk tabel/list tanpa padding -->
<section class="card overflow-hidden p-0">
  <div class="p-4 border-b ...">Header</div>
  <div>...content...</div>
</section>
```

### `.field`

Semua input form: `<input>`, `<select>`, `<textarea>`.

```vue
<input v-model="form.title" class="field" placeholder="Title" required />
<select v-model="form.status" class="field">...</select>
<textarea v-model="form.notes" class="field min-h-32" />
```

### `.label`

Label kecil uppercase untuk heading section atau field label.

```vue
<span class="label mb-1">Title</span>
<p class="label mb-2">Contributing Milestones</p>
```

### `.btn` + modifier

```vue
<!-- Primary action -->
<button class="btn btn-primary">Save</button>

<!-- Secondary / muted -->
<button class="btn btn-muted">Cancel</button>

<!-- Destructive — custom color, pakai btn base -->
<button class="btn border-red-200 bg-red-50 text-red-700 hover:bg-red-100">Delete</button>

<!-- Disabled state -->
<button class="btn btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>

<!-- Sebagai RouterLink -->
<RouterLink class="btn btn-muted" to="/work-logs">Back</RouterLink>
```

### `.pill` + tone

Badge status, kategori, atau label kecil.

```vue
<!-- Pilih tone sesuai makna, bukan estetika -->
<span class="pill pill-green">completed</span>   <!-- done, completed, active -->
<span class="pill pill-blue">in_progress</span>  <!-- in_progress, medium -->
<span class="pill pill-red">urgent</span>        <!-- blocked, urgent, high, cancelled -->
<span class="pill pill-slate">draft</span>       <!-- default / neutral -->

<!-- Dengan border tambahan (untuk highlight khusus) -->
<span class="pill border border-yellow-200 bg-yellow-50 text-yellow-700
             dark:bg-yellow-900/20 dark:text-yellow-400">New record!</span>
```

Fungsi `tone()` yang sudah ada di `Records.vue` dan `RecordDetail.vue`:

```typescript
function tone(value?: string) {
  if (['done', 'completed', 'active'].includes(value || '')) return 'pill-green';
  if (['in_progress', 'medium', 'feature', 'programming'].includes(value || '')) return 'pill-blue';
  if (['blocked', 'urgent', 'high', 'cancelled'].includes(value || '')) return 'pill-red';
  return 'pill-slate';
}
```

### `.skeleton`

Loading placeholder dengan shimmer animation.

```vue
<div v-if="loading" class="grid gap-3">
  <div v-for="i in 4" :key="i" class="skeleton h-28 rounded-2xl" />
</div>

<!-- Untuk form -->
<div v-if="loading" class="grid gap-4">
  <div class="skeleton h-28 rounded-2xl" />
  <div class="skeleton h-72 rounded-2xl" />
</div>
```

Gunakan skeleton untuk **halaman utama**. Untuk modal atau section kecil, pakai teks `Loading...`.

---

## Dark Mode

Tailwind v4 di project ini menggunakan class `dark:` yang berpasangan. **Selalu sertakan pasangan dark mode** saat menambah warna background, teks, dan border.

### Pasangan warna umum

| Light | Dark | Konteks |
|-------|------|---------|
| `bg-white` | `dark:bg-zinc-900` | Surface utama / card |
| `bg-slate-50` | `dark:bg-zinc-800/30` | Surface sekunder |
| `bg-slate-100` | `dark:bg-zinc-800` | Surface tertier |
| `border-slate-200` | `dark:border-zinc-800` | Border card |
| `border-slate-100` | `dark:border-zinc-800` | Border divider dalam card |
| `text-slate-900` | `dark:text-zinc-100` | Teks utama |
| `text-slate-700` | `dark:text-zinc-300` | Teks sekunder |
| `text-slate-500` | `dark:text-zinc-500` | Teks tersier / muted |
| `text-slate-400` | `dark:text-zinc-600` | Teks placeholder / disabled |

### Contoh penggunaan

```vue
<!-- Card metadata -->
<div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
  <p class="label">Category</p>
  <p class="mt-1 font-semibold text-slate-900 dark:text-zinc-100">{{ record.category }}</p>
</div>

<!-- Teks metadata ringan -->
<span class="text-slate-500 dark:text-zinc-400">{{ record.project_name }}</span>
<span class="text-slate-400 dark:text-zinc-600">·</span>

<!-- Divider dalam list -->
<div class="divide-y divide-slate-100 dark:divide-zinc-800">...</div>
```

---

## Empty State

Dua varian:

**Tanpa filter aktif** (belum ada data sama sekali):
```vue
<div class="py-16 text-center text-slate-400 dark:text-zinc-500">
  <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-teal-50 dark:bg-teal-900/20">
    <svg class="h-7 w-7 text-teal-700 dark:text-teal-400" .../>
  </div>
  <h2 class="text-xl font-extrabold text-slate-900 dark:text-zinc-100">No things yet</h2>
  <p class="mx-auto mt-2 max-w-md text-sm font-medium text-slate-500 dark:text-zinc-500">
    Start by creating your first thing.
  </p>
</div>
```

**Dengan filter aktif** (data ada tapi tidak ada hasil):
```vue
<div class="py-16 text-center text-slate-400 dark:text-zinc-500">
  <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 dark:bg-zinc-800">
    <svg class="h-6 w-6 text-slate-400" .../>
  </div>
  <p class="font-extrabold text-slate-700 dark:text-zinc-300">No results</p>
  <p class="mt-1 text-sm text-slate-400 dark:text-zinc-600">No records match the active filters.</p>
</div>
```

---

## Form Modal

Pattern untuk modal form (lihat `Habits.vue` sebagai referensi):

```vue
<div v-if="showForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4">
  <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
    <h2 class="text-lg font-bold text-slate-900 dark:text-white">
      {{ editing ? 'Edit Thing' : 'New Thing' }}
    </h2>

    <div>
      <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Title *</label>
      <input v-model="form.title" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" />
    </div>

    <div v-if="formError" class="text-sm text-red-500">{{ formError }}</div>

    <div class="flex justify-end gap-2 pt-2">
      <button @click="closeForm" class="px-4 py-2 text-sm text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg">Cancel</button>
      <button @click="submitForm" :disabled="saving" class="px-4 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg disabled:opacity-50">
        {{ saving ? 'Saving...' : (editing ? 'Save' : 'Create') }}
      </button>
    </div>
  </div>
</div>
```

---

## Section Header

Pattern heading halaman yang konsisten:

```vue
<!-- Halaman dengan eyebrow label -->
<div class="mb-5">
  <p class="text-xs font-extrabold uppercase text-teal-700 dark:text-teal-500">Section Label</p>
  <h1 class="text-2xl font-extrabold">Page Title</h1>
  <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-500">Subtitle atau deskripsi singkat.</p>
</div>

<!-- Halaman list dengan action button -->
<div class="flex items-center justify-between gap-3 flex-wrap">
  <div>
    <h1 class="text-xl font-bold text-slate-900 dark:text-white">Things</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Manage your things.</p>
  </div>
  <RouterLink to="/things/create" class="btn btn-primary">+ New Thing</RouterLink>
</div>
```

---

## Layout Grid

```vue
<!-- Dua kolom di desktop -->
<div class="grid gap-4 lg:grid-cols-2">...</div>

<!-- Tiga kolom di desktop -->
<div class="grid gap-4 md:grid-cols-3">...</div>

<!-- Main + sidebar (2/3 + 1/3) -->
<div class="grid gap-5 lg:grid-cols-3">
  <div class="lg:col-span-2">...main...</div>
  <div>...sidebar...</div>
</div>
```

---

## Spacing Standar

- Antar section di halaman: `mt-5` atau `space-y-5`
- Padding card: `p-5`
- Gap grid: `gap-4` atau `gap-5`
- Padding list item: `p-4`
- Gap tombol di footer form: `gap-2`

---

## Warna Aksen per Modul

Gunakan warna yang sudah ada di modul-modul sebelumnya untuk konsistensi:

| Modul | Warna |
|-------|-------|
| Dashboard / general | `teal` |
| Tasks | `teal` |
| Work Logs | `teal` |
| Daily Progress | `teal` |
| Learning | `sky` |
| Milestones | `teal` |
| Goals & OKR | `violet` |
| Habits | `indigo` |
| Docs | `teal` |
| Warning / attention | `amber` |
| Error / destructive | `red` |
