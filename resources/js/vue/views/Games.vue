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
      <!-- Sudoku -->
      <RouterLink to="/games/sudoku" class="card group flex flex-col gap-4 p-6 transition hover:border-teal-300 hover:shadow-md">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-50 text-teal-600 group-hover:bg-teal-100 dark:bg-teal-900/20 dark:text-teal-400">
          <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24">
            <rect x="3" y="3" width="5" height="5" rx="1"/><rect x="9.5" y="3" width="5" height="5" rx="1"/><rect x="16" y="3" width="5" height="5" rx="1"/>
            <rect x="3" y="9.5" width="5" height="5" rx="1"/><rect x="9.5" y="9.5" width="5" height="5" rx="1"/><rect x="16" y="9.5" width="5" height="5" rx="1"/>
            <rect x="3" y="16" width="5" height="5" rx="1"/><rect x="9.5" y="16" width="5" height="5" rx="1"/><rect x="16" y="16" width="5" height="5" rx="1"/>
          </svg>
        </div>
        <div>
          <h2 class="text-lg font-extrabold">Sudoku</h2>
          <p class="mt-1 text-sm font-medium text-slate-500">5 level · Timer · Notes · Personal record</p>
        </div>
        <span class="mt-auto inline-flex items-center gap-1 text-sm font-bold text-teal-600 group-hover:text-teal-700 dark:text-teal-400">
          Main sekarang
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
        </span>
      </RouterLink>

      <!-- Minesweeper -->
      <RouterLink to="/games/minesweeper" class="card group flex flex-col gap-4 p-6 transition hover:border-orange-300 hover:shadow-md">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-orange-50 text-orange-600 group-hover:bg-orange-100 dark:bg-orange-900/20 dark:text-orange-400">
          <span class="text-3xl leading-none">💣</span>
        </div>
        <div>
          <h2 class="text-lg font-extrabold">Minesweeper</h2>
          <p class="mt-1 text-sm font-medium text-slate-500">Pemula hingga Expert · Custom · Harian</p>
        </div>
        <span class="mt-auto inline-flex items-center gap-1 text-sm font-bold text-orange-600 group-hover:text-orange-700 dark:text-orange-400">
          Main sekarang
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
        </span>
      </RouterLink>
    </div>
  </div>
</template>
