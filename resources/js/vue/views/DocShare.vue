<script setup lang="ts">
import DOMPurify from 'dompurify';
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const doc = ref<any>(null);
const loading = ref(true);
const notFound = ref(false);

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

function formatSize(bytes: number) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

function isImage(mime: string) { return mime.startsWith('image/'); }

function fileDownloadUrl(fileId: number) {
  return `/api/share/docs/${route.params.token}/files/${fileId}`;
}

onMounted(async () => {
  try {
    const res = await axios.get(`/api/share/docs/${route.params.token}`);
    doc.value = res.data.doc;
  } catch {
    notFound.value = true;
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="min-h-screen bg-stone-50 dark:bg-zinc-950 text-slate-950 dark:text-zinc-100">
    <div class="mx-auto max-w-3xl px-4 py-10">
      <div v-if="loading" class="space-y-4 py-10">
        <div class="skeleton h-10 w-2/3 rounded-2xl" />
        <div class="skeleton h-5 w-32 rounded-xl" />
        <div class="skeleton h-64 rounded-2xl" />
      </div>
      <div v-else-if="notFound" class="py-20 text-center">
        <p class="text-2xl font-extrabold">Not Found</p>
        <p class="mt-2 text-sm text-slate-400">This document is not available or the link has expired.</p>
      </div>
      <template v-else-if="doc">
        <div class="mb-8">
          <p v-if="doc.category" class="text-sm font-extrabold uppercase tracking-widest text-teal-700 dark:text-teal-500">{{ doc.category }}</p>
          <h1 class="mt-1 text-3xl font-extrabold tracking-tight">{{ doc.title }}</h1>
          <p class="mt-1 text-sm text-slate-400 dark:text-zinc-500">{{ formatDate(doc.created_at) }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
          <!-- eslint-disable-next-line vue/no-v-html -->
          <div v-if="doc.description" class="prose prose-sm max-w-none dark:prose-invert" v-html="safeDescription" />
          <p v-else class="text-sm text-slate-400 dark:text-zinc-600">No description provided.</p>
        </div>

        <div v-if="doc.reference_urls?.length" class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 space-y-2">
          <p class="mb-2 text-xs font-extrabold uppercase tracking-widest text-slate-400 dark:text-zinc-500">Reference URLs</p>
          <div v-for="ref in doc.reference_urls" :key="ref.url ?? ref" class="flex items-center gap-2 text-sm">
            <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" /></svg>
            <div class="min-w-0 flex-1">
              <a v-if="safeUrl(ref.url ?? ref)" :href="safeUrl(ref.url ?? ref)!" target="_blank" rel="noopener noreferrer" class="font-semibold text-teal-700 hover:underline dark:text-teal-400">
                {{ ref.title || ref.url || ref }}
              </a>
              <span v-else class="text-slate-400">{{ ref.title || ref.url || ref }}</span>
              <p v-if="ref.title" class="truncate text-xs text-slate-400">{{ ref.url }}</p>
            </div>
          </div>
        </div>

        <div v-if="doc.files?.length" class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 space-y-2">
          <p class="mb-2 text-xs font-extrabold uppercase tracking-widest text-slate-400 dark:text-zinc-500">Attachments</p>
          <div v-for="f in doc.files" :key="f.id" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
            <img v-if="isImage(f.mime_type)" :src="fileDownloadUrl(f.id)" class="h-10 w-10 shrink-0 rounded object-cover" :alt="f.original_name" />
            <svg v-else class="h-8 w-8 shrink-0 text-slate-300 dark:text-zinc-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" /></svg>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-slate-800 dark:text-zinc-200">{{ f.original_name }}</p>
              <p class="text-xs text-slate-400">{{ formatSize(f.size) }}</p>
            </div>
            <a :href="fileDownloadUrl(f.id)" :download="f.original_name" class="shrink-0 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">Download</a>
          </div>
        </div>
      </template>

      <div class="mt-12 text-center text-xs text-slate-300 dark:text-zinc-700">
        Shared via ProgressOS
      </div>
    </div>
  </div>
</template>
