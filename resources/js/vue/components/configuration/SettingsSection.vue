<script setup lang="ts">
defineProps<{
    title: string;
    subtitle?: string;
    expanded: boolean;
    badge?: string;
}>();

defineEmits<{ toggle: [] }>();
</script>

<template>
    <section class="card overflow-hidden p-0">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-4 text-left dark:border-zinc-800 dark:bg-zinc-800/40"
            :aria-expanded="expanded"
            @click="$emit('toggle')"
        >
            <span>
                <span class="block text-xs font-extrabold uppercase text-teal-700">
                    {{ title }} <span v-if="badge" class="ml-2 rounded-full bg-teal-100 px-2 py-0.5 text-[10px] text-teal-800">{{ badge }}</span>
                </span>
                <span class="mt-1 block text-lg font-extrabold text-slate-950 dark:text-slate-100">
                    <slot name="heading">{{ title }}</slot>
                </span>
                <span v-if="subtitle" class="mt-1 block text-sm font-medium text-slate-500">
                    {{ subtitle }}
                </span>
            </span>
            <span
                class="grid h-8 w-8 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 transition dark:border-zinc-700 dark:bg-zinc-900"
                :class="expanded ? 'rotate-180' : ''"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></svg>
            </span>
        </button>
        <div v-if="expanded">
            <slot />
        </div>
    </section>
</template>
