<script setup lang="ts">
import { useRouter } from 'vue-router';
const router = useRouter();
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-slate-50 p-8 text-center dark:bg-slate-900">
        <div class="mb-4 text-7xl font-black text-slate-300 dark:text-slate-700">404</div>
        <h1 class="mb-2 text-2xl font-bold text-slate-900 dark:text-slate-100">Halaman tidak ditemukan</h1>
        <p class="mb-6 max-w-md text-slate-600 dark:text-slate-400">
            Halaman yang kamu cari mungkin telah dipindahkan, dihapus, atau tidak pernah ada.
        </p>
        <button
            @click="router.push('/dashboard')"
            class="btn btn-primary"
            aria-label="Kembali ke dashboard"
        >
            Kembali ke Dashboard
        </button>
    </div>
</template>