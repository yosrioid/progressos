<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
  <div class="space-y-6">
    <div>
      <p class="text-sm font-extrabold text-teal-700">Games</p>
      <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Mini Games</h1>
      <p class="mt-1 text-sm font-medium text-slate-500">Latih fokus dan logika di sela kerja.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <RouterLink to="/games/sudoku" class="card group flex flex-col gap-4 p-6 transition hover:border-teal-300 hover:shadow-md">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-50 text-teal-600 group-hover:bg-teal-100">
          <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24">
            <rect x="3" y="3" width="5" height="5" rx="1" />
            <rect x="9.5" y="3" width="5" height="5" rx="1" />
            <rect x="16" y="3" width="5" height="5" rx="1" />
            <rect x="3" y="9.5" width="5" height="5" rx="1" />
            <rect x="9.5" y="9.5" width="5" height="5" rx="1" />
            <rect x="16" y="9.5" width="5" height="5" rx="1" />
            <rect x="3" y="16" width="5" height="5" rx="1" />
            <rect x="9.5" y="16" width="5" height="5" rx="1" />
            <rect x="16" y="16" width="5" height="5" rx="1" />
          </svg>
        </div>
        <div>
          <h2 class="text-lg font-extrabold">Sudoku</h2>
          <p class="mt-1 text-sm font-medium text-slate-500">3 level kesulitan · Timer · Personal record</p>
        </div>
        <span class="mt-auto inline-flex items-center gap-1 text-sm font-bold text-teal-600 group-hover:text-teal-700">
          Main sekarang
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6" /></svg>
        </span>
      </RouterLink>

      <div class="card flex flex-col gap-4 p-6 opacity-50">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
          <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <path d="M12 8v4l3 3" />
          </svg>
        </div>
        <div>
          <h2 class="text-lg font-extrabold text-slate-400">Coming soon</h2>
          <p class="mt-1 text-sm font-medium text-slate-400">Game berikutnya masih dalam pengembangan.</p>
        </div>
      </div>
    </div>
  </div>
</template>
