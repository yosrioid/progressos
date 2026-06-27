<script setup lang="ts">
import DOMPurify from 'dompurify';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';

const route = useRoute();
const router = useRouter();
const doc = ref<any>(null);
const loading = ref(true);
const sharing = ref(false);

async function toggleShare() {
  if (sharing.value) return;
  sharing.value = true;
  try {
    if (doc.value.share_token) {
      const data = await api.delete(`/api/v1/docs/${doc.value.id}/share`).then(unwrap);
      doc.value = data.doc;
      toast({ tone: 'success', title: 'Link revoked', message: 'Doc is now private.' });
    } else {
      const data = await api.post(`/api/v1/docs/${doc.value.id}/share`).then(unwrap);
      doc.value = data.doc;
      await navigator.clipboard.writeText(doc.value.share_url);
      toast({ tone: 'success', title: 'Link copied', message: 'Share link copied to clipboard.' });
    }
  } finally {
    sharing.value = false;
  }
}

async function copyShareLink() {
  await navigator.clipboard.writeText(doc.value.share_url);
  toast({ tone: 'success', title: 'Copied', message: 'Share link copied.' });
}
const safeDescription = computed(() =>
  doc.value?.description ? DOMPurify.sanitize(doc.value.description) : ''
);

function safeUrl(url: string): string | null {
  try {
    const parsed = new URL(url);
    return ['http:', 'https:'].includes(parsed.protocol) ? url : null;
  } catch {
    return null;
  }
}

async function load() {
  const data = await api.get(`/api/v1/docs/${route.params.id}`).then(unwrap);
  doc.value = data.doc;
  loading.value = false;
}

async function remove() {
  const ok = await confirmAction({ title: 'Delete doc', message: `Delete "${doc.value.title}"? This also removes all attached files.`, confirmLabel: 'Delete' });
  if (!ok) return;
  await api.delete(`/api/v1/docs/${doc.value.id}`);
  toast({ tone: 'success', title: 'Deleted', message: doc.value.title });
  router.push('/docs');
}

function formatSize(bytes: number) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}
function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}
function isImage(mime: string) { return mime.startsWith('image/'); }

onMounted(load);
</script>

<template>
  <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Loading…</div>
  <div v-else-if="doc" class="mx-auto max-w-3xl space-y-5">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3">
      <div>
        <p v-if="doc.category" class="text-sm font-extrabold uppercase text-teal-700 dark:text-teal-500">{{ doc.category }}</p>
        <h1 class="mt-1 text-3xl font-extrabold tracking-tight">{{ doc.title }}</h1>
        <p class="mt-1 text-sm text-slate-400 dark:text-zinc-600">{{ formatDate(doc.created_at) }}</p>
      </div>
      <div class="flex shrink-0 flex-wrap gap-2">
        <template v-if="doc.share_token">
          <button class="btn btn-muted text-xs" @click="copyShareLink">Copy Link</button>
          <button class="btn btn-muted text-xs text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20" :disabled="sharing" @click="toggleShare">Unshare</button>
        </template>
        <button v-else class="btn btn-muted text-xs" :disabled="sharing" @click="toggleShare">Share</button>
        <button class="btn btn-muted" @click="router.push(`/docs/${doc.id}/edit`)">Edit</button>
        <button class="btn btn-muted text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" @click="remove">Delete</button>
      </div>
    </div>

    <!-- Description -->
    <div class="card p-5">
      <!-- eslint-disable-next-line vue/no-v-html -->
      <div v-if="doc.description" class="prose prose-sm max-w-none dark:prose-invert" v-html="safeDescription" />
      <p v-else class="text-sm text-slate-400 dark:text-zinc-600">No description provided.</p>
    </div>

    <!-- Reference URLs -->
    <div v-if="doc.reference_urls?.length" class="card p-5 space-y-2">
      <p class="label mb-2">Reference URLs</p>
      <div v-for="ref in doc.reference_urls" :key="ref.url ?? ref" class="flex items-center gap-2 text-sm">
        <svg class="h-4 w-4 shrink-0 text-slate-400 dark:text-zinc-500" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" /></svg>
        <div class="min-w-0 flex-1">
          <a v-if="safeUrl(ref.url ?? ref)" :href="safeUrl(ref.url ?? ref)!" target="_blank" rel="noopener noreferrer" class="font-semibold text-teal-700 hover:underline dark:text-teal-400">
            {{ ref.title || ref.url || ref }}
          </a>
          <span v-else class="font-semibold text-slate-400">{{ ref.title || ref.url || ref }}</span>
          <p v-if="ref.title" class="truncate text-xs text-slate-400 dark:text-zinc-600">{{ ref.url }}</p>
        </div>
        <button class="shrink-0 rounded p-0.5 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200" title="Copy URL" @click="navigator.clipboard.writeText(ref.url ?? ref)">
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></svg>
        </button>
      </div>
    </div>

    <!-- Files -->
    <div v-if="doc.files?.length" class="card p-5 space-y-2">
      <p class="label mb-2">Attachments</p>
      <div v-for="f in doc.files" :key="f.id" class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
        <img v-if="isImage(f.mime_type)" :src="f.url" class="h-10 w-10 shrink-0 rounded object-cover" :alt="f.original_name" />
        <svg v-else class="h-8 w-8 shrink-0 text-slate-300 dark:text-zinc-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" /></svg>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-semibold text-slate-800 dark:text-zinc-200">{{ f.original_name }}</p>
          <p class="text-xs text-slate-400 dark:text-zinc-600">{{ formatSize(f.size) }}</p>
        </div>
        <a :href="f.url" :download="f.original_name" class="btn btn-muted shrink-0 px-2 py-1 text-xs">Download</a>
      </div>
    </div>
  </div>
</template>
