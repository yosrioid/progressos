<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
  <div class="space-y-6">
    <div>
      <p class="text-sm font-extrabold text-teal-700 dark:text-teal-400">Games</p>
      <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Mini Games</h1>
      <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-400">Train focus, logic, and memory between work sessions.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
      <!-- Sudoku -->
      <RouterLink to="/games/sudoku" class="card group flex flex-col gap-4 p-6 transition hover:border-teal-300 dark:hover:border-teal-700 hover:shadow-md">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-50 text-teal-600 group-hover:bg-teal-100 dark:bg-teal-900/20 dark:text-teal-400">
          <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24">
            <rect x="3" y="3" width="5" height="5" rx="1"/><rect x="9.5" y="3" width="5" height="5" rx="1"/><rect x="16" y="3" width="5" height="5" rx="1"/>
            <rect x="3" y="9.5" width="5" height="5" rx="1"/><rect x="9.5" y="9.5" width="5" height="5" rx="1"/><rect x="16" y="9.5" width="5" height="5" rx="1"/>
            <rect x="3" y="16" width="5" height="5" rx="1"/><rect x="9.5" y="16" width="5" height="5" rx="1"/><rect x="16" y="16" width="5" height="5" rx="1"/>
          </svg>
        </div>
        <div>
          <h2 class="text-lg font-extrabold">Sudoku</h2>
          <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-400">5 level · Timer · Notes · Personal record</p>
        </div>
        <span class="mt-auto inline-flex items-center gap-1 text-sm font-bold text-teal-600 group-hover:text-teal-700 dark:text-teal-400">
          Play now
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
        </span>
      </RouterLink>

      <!-- Minesweeper -->
      <RouterLink to="/games/minesweeper" class="card group flex flex-col gap-4 p-6 transition hover:border-orange-300 dark:hover:border-orange-700 hover:shadow-md">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-orange-50 text-orange-600 group-hover:bg-orange-100 dark:bg-orange-900/20 dark:text-orange-400">
          <span class="text-3xl leading-none">💣</span>
        </div>
        <div>
          <h2 class="text-lg font-extrabold">Minesweeper</h2>
          <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-400">Beginner to Expert · Custom · Daily</p>
        </div>
        <span class="mt-auto inline-flex items-center gap-1 text-sm font-bold text-orange-600 group-hover:text-orange-700 dark:text-orange-400">
          Play now
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
        </span>
      </RouterLink>

      <!-- 2048 -->
      <RouterLink to="/games/2048" class="card group flex flex-col gap-4 p-6 transition hover:border-amber-300 dark:hover:border-amber-700 hover:shadow-md">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl text-white font-extrabold text-xl" style="background:#edc22e">
          2048
        </div>
        <div>
          <h2 class="text-lg font-extrabold">2048</h2>
          <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-400">4 levels · Undo · Swipe · Daily</p>
        </div>
        <span class="mt-auto inline-flex items-center gap-1 text-sm font-bold text-amber-600 group-hover:text-amber-700 dark:text-amber-400">
          Play now
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
        </span>
      </RouterLink>

      <!-- Memory Match -->
      <RouterLink to="/games/memory" class="card group flex flex-col gap-4 p-6 transition hover:border-indigo-300 dark:hover:border-indigo-700 hover:shadow-md">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 group-hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-400">
          <span class="text-3xl leading-none">🃏</span>
        </div>
        <div>
          <h2 class="text-lg font-extrabold">Memory Match</h2>
          <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-400">3 levels · Preview · Flip animation · Daily</p>
        </div>
        <span class="mt-auto inline-flex items-center gap-1 text-sm font-bold text-indigo-600 group-hover:text-indigo-700 dark:text-indigo-400">
          Play now
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
        </span>
      </RouterLink>

      <!-- Melody Memory -->
      <RouterLink to="/games/melody-memory" class="card group flex flex-col gap-4 p-6 transition hover:border-rose-300 dark:hover:border-rose-700 hover:shadow-md">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 group-hover:bg-rose-100 dark:bg-rose-900/20 dark:text-rose-400">
          <span class="text-3xl leading-none">🎵</span>
        </div>
        <div>
          <h2 class="text-lg font-extrabold">Melody Memory</h2>
          <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-400">Repeat the melody · Tone-deafen friendly</p>
        </div>
        <span class="mt-auto inline-flex items-center gap-1 text-sm font-bold text-rose-600 group-hover:text-rose-700 dark:text-rose-400">
          Play now
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
        </span>
      </RouterLink>

      <!-- Pitch Trainer -->
      <RouterLink to="/games/pitch-trainer" class="card group flex flex-col gap-4 p-6 transition hover:border-violet-300 dark:hover:border-violet-700 hover:shadow-md">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 text-violet-600 group-hover:bg-violet-100 dark:bg-violet-900/20 dark:text-violet-400">
          <span class="text-3xl leading-none">🎤</span>
        </div>
        <div>
          <h2 class="text-lg font-extrabold">Pitch Trainer</h2>
          <p class="mt-1 text-sm font-medium text-slate-500 dark:text-zinc-400">Match the note · Cents accuracy · Streak counter</p>
        </div>
        <span class="mt-auto inline-flex items-center gap-1 text-sm font-bold text-violet-600 group-hover:text-violet-700 dark:text-violet-400">
          Play now
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
        </span>
      </RouterLink>

    </div>
  </div>
</template>
