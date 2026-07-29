<script setup lang="ts">
const props = defineProps<{
    label: string;
    help?: string;
}>();

const model = defineModel<string | number | boolean>();
</script>

<template>
    <div class="grid gap-3 px-5 py-4 md:grid-cols-[16rem_1fr] md:items-start">
        <label class="flex flex-col gap-1 font-extrabold text-slate-800">
            {{ props.label }}
            <span v-if="props.help" class="text-xs font-normal text-slate-500">{{ props.help }}</span>
        </label>
        <slot :model-value="model" :update="(val: any) => model = val" />
    </div>
</template>
