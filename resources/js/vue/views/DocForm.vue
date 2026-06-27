<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import { toast } from '../feedback';
import WysiwygEditor from '../components/WysiwygEditor.vue';

const route = useRoute();
const router = useRouter();
const isEdit = Boolean(route.params.id);

const form = ref({ title: '', category: '', description: '', reference_urls: [] as { title: string; url: string }[] });
const categories = ref<string[]>([]);
const files = ref<any[]>([]);
const pendingFiles = ref<File[]>([]);
const uploading = ref(false);
const saving = ref(false);
const loadingDoc = ref(isEdit);
const docId = ref<number | null>(null);

async function loadDoc() {
  const data = await api.get(`/api/v1/docs/${route.params.id}`).then(unwrap);
  const doc = data.doc;
  docId.value = doc.id;
  form.value = {
    title: doc.title,
    category: doc.category || '',
    description: doc.description || '',
    reference_urls: doc.reference_urls?.length ? doc.reference_urls : [] as { title: string; url: string }[],
  };
  files.value = doc.files || [];
  loadingDoc.value = false;
}

async function loadCategories() {
  const data = await api.get('/api/v1/docs/categories').then(unwrap);
  categories.value = data.categories;
}

function addUrl() { form.value.reference_urls.push({ title: '', url: '' }); }
function removeUrl(i: number) { form.value.reference_urls.splice(i, 1); }

function pickFiles(e: Event) {
  const input = e.target as HTMLInputElement;
  if (input.files) pendingFiles.value.push(...Array.from(input.files));
  input.value = '';
}
function removePending(i: number) { pendingFiles.value.splice(i, 1); }

async function uploadPending(id: number) {
  for (const f of pendingFiles.value) {
    const fd = new FormData();
    fd.append('file', f);
    const data = await api.post(`/api/v1/docs/${id}/files`, fd).then(unwrap);
    files.value.push(data.file);
  }
  pendingFiles.value = [];
}

async function deleteFile(file: any) {
  await api.delete(`/api/v1/docs/${docId.value}/files/${file.id}`);
  files.value = files.value.filter((f) => f.id !== file.id);
  toast({ tone: 'success', title: 'File removed', message: file.original_name });
}

function formatSize(bytes: number) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}

async function submit() {
  saving.value = true;
  try {
    const payload = { ...form.value, reference_urls: form.value.reference_urls.filter((r) => r.url) };
    let id: number;
    if (isEdit) {
      await api.put(`/api/v1/docs/${route.params.id}`, payload);
      id = Number(route.params.id);
    } else {
      const data = await api.post('/api/v1/docs', payload).then(unwrap);
      id = data.doc.id;
      docId.value = id;
    }
    if (pendingFiles.value.length) {
      uploading.value = true;
      await uploadPending(id);
      uploading.value = false;
    }
    toast({ tone: 'success', title: isEdit ? 'Doc updated' : 'Doc created', message: form.value.title });
    router.push(`/docs/${id}`);
  } catch (e: any) {
    toast({ tone: 'error', title: 'Error', message: e.response?.data?.message || 'Something went wrong.' });
  } finally {
    saving.value = false;
    uploading.value = false;
  }
}

onMounted(() => { loadCategories(); if (isEdit) loadDoc(); });
</script>

<template>
  <div v-if="loadingDoc" class="py-12 text-center text-sm text-slate-400">Loading…</div>
  <form v-else class="mx-auto max-w-3xl space-y-5" @submit.prevent="submit">
    <div class="flex items-center justify-between gap-3">
      <div>
        <p class="text-sm font-extrabold uppercase text-teal-700 dark:text-teal-500">{{ isEdit ? 'Edit' : 'New' }}</p>
        <h1 class="text-3xl font-extrabold tracking-tight">{{ isEdit ? 'Edit Doc' : 'New Doc' }}</h1>
      </div>
      <button type="button" class="btn btn-muted" @click="router.back()">Cancel</button>
    </div>

    <!-- Title + Category -->
    <div class="card p-5">
      <div class="flex flex-col gap-6">
        <label class="block">
          <span class="label mb-1">Title <span class="text-red-500">*</span></span>
          <input v-model="form.title" class="field" required placeholder="Doc title" />
        </label>
        <label class="block">
          <span class="label mb-1">Category <span class="text-red-500">*</span></span>
          <input v-model="form.category" class="field" list="cat-list" placeholder="Select or type a category" required />
          <datalist id="cat-list">
            <option v-for="c in categories" :key="c" :value="c" />
          </datalist>
        </label>
      </div>
    </div>

    <!-- Description (WYSIWYG) -->
    <div class="card p-5">
      <span class="label mb-3 block">Description</span>
      <WysiwygEditor v-model="form.description" />
    </div>

    <!-- Reference URLs -->
    <div class="card p-5">
      <div class="mb-3 flex items-center justify-between">
        <span class="label">Reference URLs</span>
        <button type="button" class="btn btn-muted px-2 py-1 text-xs" @click="addUrl">+ Add URL</button>
      </div>
      <div v-if="!form.reference_urls.length" class="text-sm text-slate-400 dark:text-zinc-600">No URLs added yet.</div>
      <div v-else class="space-y-3">
        <div v-for="(ref, i) in form.reference_urls" :key="i" class="flex gap-2">
          <div class="flex flex-1 flex-col gap-2 sm:flex-row">
            <input v-model="ref.title" class="field sm:w-44 sm:shrink-0" placeholder="Title (optional)" />
            <input v-model="ref.url" class="field flex-1" type="url" placeholder="https://…" />
          </div>
          <button type="button" class="btn btn-muted px-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20" @click="removeUrl(i)">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12" /></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- File upload -->
    <div class="card p-5">
      <span class="label mb-3 block">Files</span>

      <!-- Existing files -->
      <div v-if="files.length" class="mb-3 space-y-2">
        <div v-for="f in files" :key="f.id" class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-800 dark:text-zinc-200">{{ f.original_name }}</p>
            <p class="text-xs text-slate-400 dark:text-zinc-600">{{ formatSize(f.size) }}</p>
          </div>
          <button type="button" class="shrink-0 rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400" @click="deleteFile(f)">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" /></svg>
          </button>
        </div>
      </div>

      <!-- Pending uploads -->
      <div v-if="pendingFiles.length" class="mb-3 space-y-2">
        <div v-for="(f, i) in pendingFiles" :key="i" class="flex items-center justify-between gap-3 rounded-lg border border-teal-100 bg-teal-50 px-3 py-2 dark:border-teal-700/40 dark:bg-teal-900/20">
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-teal-800 dark:text-teal-300">{{ f.name }}</p>
            <p class="text-xs text-teal-600 dark:text-teal-500">{{ formatSize(f.size) }} · pending upload</p>
          </div>
          <button type="button" class="shrink-0 rounded p-1 text-teal-400 hover:text-red-600" @click="removePending(i)">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12" /></svg>
          </button>
        </div>
      </div>

      <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 hover:border-teal-400 hover:bg-teal-50 dark:border-zinc-600 dark:bg-zinc-800 dark:hover:border-teal-600 dark:hover:bg-teal-900/20">
        <svg class="h-5 w-5 text-slate-400 dark:text-zinc-500" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12" /></svg>
        <span class="text-sm font-semibold text-slate-500 dark:text-zinc-400">Click to attach files</span>
        <input type="file" multiple class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.rtf,.txt,.csv,.md,.json,.jpg,.jpeg,.png,.gif,.webp,.zip,.rar" @change="pickFiles" />
      </label>
      <p class="text-xs text-slate-400 dark:text-zinc-600">PDF, Word, Excel, PowerPoint, ODT/ODS, CSV, RTF, MD, JSON, images (JPG/PNG/GIF/WebP), ZIP, RAR — max 10 MB each.</p>
    </div>

    <div class="flex justify-end gap-2">
      <button type="button" class="btn btn-muted" @click="router.back()">Cancel</button>
      <button type="submit" class="btn btn-primary" :disabled="saving">
        {{ uploading ? 'Uploading…' : saving ? 'Saving…' : isEdit ? 'Save changes' : 'Create doc' }}
      </button>
    </div>
  </form>
</template>
