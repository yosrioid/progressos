<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{ modelValue?: string; label?: string; placeholder?: string; required?: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const today = new Date();
const cursor = ref(toDate(props.modelValue) || today);

const monthLabel = computed(() => cursor.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }));
const displayValue = computed(() => props.modelValue ? toDate(props.modelValue)?.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '');
const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const days = computed(() => {
  const year = cursor.value.getFullYear();
  const month = cursor.value.getMonth();
  const first = new Date(year, month, 1);
  const start = new Date(year, month, 1 - first.getDay());

  return Array.from({ length: 42 }, (_, index) => {
    const date = new Date(start);
    date.setDate(start.getDate() + index);
    const value = formatDate(date);

    return {
      date,
      value,
      day: date.getDate(),
      muted: date.getMonth() !== month,
      selected: value === props.modelValue,
      today: value === formatDate(today),
    };
  });
});

watch(() => props.modelValue, (value) => {
  if (value) cursor.value = toDate(value) || cursor.value;
});

function toDate(value?: string) {
  if (!value) return null;
  const [year, month, day] = value.split('-').map(Number);
  if (!year || !month || !day) return null;
  return new Date(year, month - 1, day);
}

function formatDate(date: Date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function select(value: string) {
  emit('update:modelValue', value);
  open.value = false;
}

function shiftMonth(offset: number) {
  cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + offset, 1);
}

function chooseToday() {
  cursor.value = today;
  select(formatDate(today));
}

function handleOutside(event: MouseEvent) {
  if (root.value && !root.value.contains(event.target as Node)) open.value = false;
}

watch(open, (value) => {
  if (value) window.addEventListener('mousedown', handleOutside);
  else window.removeEventListener('mousedown', handleOutside);
});

onBeforeUnmount(() => window.removeEventListener('mousedown', handleOutside));
</script>

<template>
  <div ref="root" class="relative">
    <button type="button" class="field flex items-center justify-between gap-3 text-left" :aria-label="label || placeholder || 'Choose date'" @click="open = !open">
      <span :class="displayValue ? 'text-slate-900' : 'text-slate-400'">{{ displayValue || placeholder || 'Choose date' }}</span>
      <span class="grid h-7 w-7 place-items-center rounded-lg bg-teal-50 text-teal-700">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24"><path d="M7 3v3M17 3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" /></svg>
      </span>
    </button>
    <input class="sr-only" :value="modelValue" :required="required" tabindex="-1" aria-hidden="true" />
    <div v-if="open" class="absolute z-40 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl shadow-slate-950/10">
      <div class="mb-3 flex items-center justify-between gap-2">
        <button type="button" class="btn btn-muted px-3" aria-label="Previous month" @click="shiftMonth(-1)">‹</button>
        <p class="text-sm font-extrabold text-slate-900">{{ monthLabel }}</p>
        <button type="button" class="btn btn-muted px-3" aria-label="Next month" @click="shiftMonth(1)">›</button>
      </div>
      <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-extrabold uppercase text-slate-400">
        <span v-for="day in weekdays" :key="day">{{ day }}</span>
      </div>
      <div class="mt-2 grid grid-cols-7 gap-1">
        <button
          v-for="item in days"
          :key="item.value"
          type="button"
          class="grid h-9 place-items-center rounded-xl text-sm font-bold transition hover:bg-teal-50 hover:text-teal-800"
          :class="[item.selected ? 'bg-teal-700 text-white hover:bg-teal-700 hover:text-white' : item.today ? 'bg-teal-50 text-teal-800' : item.muted ? 'text-slate-300' : 'text-slate-700']"
          @click="select(item.value)"
        >
          {{ item.day }}
        </button>
      </div>
      <div class="mt-3 flex justify-between border-t border-slate-100 pt-3">
        <button type="button" class="btn btn-muted" @click="emit('update:modelValue', '')">Clear</button>
        <button type="button" class="btn btn-primary" @click="chooseToday">Today</button>
      </div>
    </div>
  </div>
</template>
