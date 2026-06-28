<script setup lang="ts">
import { nextTick, ref } from 'vue';

type SectionKey = 'completed_items' | 'in_progress' | 'todo' | 'blockers';

interface Item {
  text: string;
  depth: number;
}

interface ModelValue {
  completed_items: string[];
  in_progress: string[];
  todo: string[];
  blockers: string[];
}

const props = defineProps<{ modelValue: ModelValue }>();
const emit = defineEmits<{ 'update:modelValue': [ModelValue] }>();

const SECTIONS: { key: SectionKey; label: string }[] = [
  { key: 'completed_items', label: 'Completed' },
  { key: 'in_progress', label: 'In Progress' },
  { key: 'todo', label: 'Todo' },
  { key: 'blockers', label: 'Blockers' },
];

const MAX_DEPTH = 4;

const inputRefs = ref<Record<string, HTMLInputElement | null>>({});
const dragging = ref<{ section: SectionKey; index: number } | null>(null);
const dropTarget = ref<{ section: SectionKey; index: number } | null>(null);

function parseItems(raw: string[]): Item[] {
  return raw.map((s) => {
    const depth = s.match(/^\t*/)?.[0].length ?? 0;
    return { text: s.slice(depth), depth };
  });
}

function serializeItems(items: Item[]): string[] {
  return items.map((item) => '\t'.repeat(item.depth) + item.text);
}

function get(section: SectionKey): Item[] {
  return parseItems(props.modelValue[section] ?? []);
}

function set(section: SectionKey, items: Item[]) {
  emit('update:modelValue', { ...props.modelValue, [section]: serializeItems(items) });
}

async function addItem(section: SectionKey) {
  const items = get(section);
  const depth = items.length > 0 ? items[items.length - 1].depth : 0;
  set(section, [...items, { text: '', depth }]);
  await nextTick();
  inputRefs.value[`${section}-${items.length}`]?.focus();
}

function removeItem(section: SectionKey, index: number) {
  const items = [...get(section)];
  items.splice(index, 1);
  set(section, items);
}

function updateItem(section: SectionKey, index: number, value: string) {
  const items = [...get(section)];
  items[index] = { ...items[index], text: value };
  set(section, items);
}

async function onKeyDown(e: KeyboardEvent, section: SectionKey, index: number) {
  const items = get(section);
  if (e.key === 'Enter') {
    e.preventDefault();
    const newItems = [...items];
    newItems.splice(index + 1, 0, { text: '', depth: items[index].depth });
    set(section, newItems);
    await nextTick();
    inputRefs.value[`${section}-${index + 1}`]?.focus();
  } else if (e.key === 'Tab') {
    e.preventDefault();
    const newItems = [...items];
    if (e.shiftKey) {
      newItems[index] = { ...newItems[index], depth: Math.max(0, newItems[index].depth - 1) };
    } else {
      newItems[index] = { ...newItems[index], depth: Math.min(MAX_DEPTH, newItems[index].depth + 1) };
    }
    set(section, newItems);
    await nextTick();
    inputRefs.value[`${section}-${index}`]?.focus();
  } else if (e.key === 'Backspace' && items[index].text === '') {
    e.preventDefault();
    removeItem(section, index);
    await nextTick();
    const prev = Math.max(0, index - 1);
    inputRefs.value[`${section}-${prev}`]?.focus();
  }
}

function onDragStart(e: DragEvent, section: SectionKey, index: number) {
  dragging.value = { section, index };
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', '');
  }
}

function onDragEnd() {
  dragging.value = null;
  dropTarget.value = null;
}

function onDragOverItem(e: DragEvent, section: SectionKey, index: number) {
  e.preventDefault();
  e.stopPropagation();
  dropTarget.value = { section, index };
}

function onDragOverSection(e: DragEvent, section: SectionKey) {
  e.preventDefault();
  if (!dropTarget.value || dropTarget.value.section !== section) {
    dropTarget.value = { section, index: get(section).length };
  }
}

function performDrop(targetSection: SectionKey, targetIndex: number) {
  if (!dragging.value) return;
  const { section: srcSection, index: srcIndex } = dragging.value;
  const newData = { ...props.modelValue };
  const srcItems = parseItems([...newData[srcSection]]);
  const [item] = srcItems.splice(srcIndex, 1);
  if (srcSection === targetSection) {
    const adj = targetIndex > srcIndex ? targetIndex - 1 : targetIndex;
    srcItems.splice(adj, 0, item);
    newData[srcSection] = serializeItems(srcItems);
  } else {
    newData[srcSection] = serializeItems(srcItems);
    const tgtItems = parseItems([...newData[targetSection]]);
    tgtItems.splice(targetIndex, 0, item);
    newData[targetSection] = serializeItems(tgtItems);
  }
  emit('update:modelValue', newData);
  dragging.value = null;
  dropTarget.value = null;
}

function onDropItem(e: DragEvent, section: SectionKey, index: number) {
  e.preventDefault();
  e.stopPropagation();
  performDrop(section, index);
}

function onDropSection(e: DragEvent, section: SectionKey) {
  e.preventDefault();
  performDrop(section, get(section).length);
}

function isDropTarget(section: SectionKey, index: number) {
  return dropTarget.value?.section === section && dropTarget.value?.index === index && dragging.value;
}

function isDraggingFrom(section: SectionKey, index: number) {
  return dragging.value?.section === section && dragging.value?.index === index;
}
</script>

<template>
  <div class="grid gap-4 md:grid-cols-2">
    <div
      v-for="s in SECTIONS"
      :key="s.key"
      class="flex flex-col rounded-2xl border p-4 transition-colors"
      :class="dropTarget?.section === s.key && dragging?.section !== s.key
        ? 'border-teal-300 bg-teal-50/50 dark:border-teal-700 dark:bg-teal-900/10'
        : 'border-slate-200 bg-slate-50 dark:border-zinc-700 dark:bg-zinc-800/40'"
      @dragover="onDragOverSection($event, s.key)"
      @drop="onDropSection($event, s.key)"
    >
      <div class="mb-3 flex items-center justify-between">
        <p class="label">{{ s.label }}</p>
        <button type="button" class="text-xs font-extrabold text-teal-700 hover:text-teal-900 dark:text-teal-400" @click="addItem(s.key)">+ Add</button>
      </div>

      <div class="flex-1 space-y-0.5">
        <template v-for="(item, i) in get(s.key)" :key="i">
          <!-- drop indicator above item -->
          <div v-if="isDropTarget(s.key, i)" class="mx-1 my-0.5 h-0.5 rounded-full bg-teal-500" />
          <div
            class="group flex items-center gap-1 rounded-lg border border-transparent py-0.5 pr-1 transition-colors hover:border-slate-200 hover:bg-white dark:hover:border-zinc-600 dark:hover:bg-zinc-700/50"
            :class="isDraggingFrom(s.key, i) ? 'opacity-40' : ''"
            :style="{ paddingLeft: `${item.depth * 16 + 4}px` }"
            draggable="true"
            @dragstart="onDragStart($event, s.key, i)"
            @dragend="onDragEnd"
            @dragover="onDragOverItem($event, s.key, i)"
            @drop="onDropItem($event, s.key, i)"
          >
            <span class="shrink-0 cursor-grab text-slate-300 opacity-0 transition-opacity group-hover:opacity-100 dark:text-zinc-600" title="Drag to move">
              <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor">
                <circle cx="5.5" cy="4" r="1.2"/><circle cx="10.5" cy="4" r="1.2"/>
                <circle cx="5.5" cy="8" r="1.2"/><circle cx="10.5" cy="8" r="1.2"/>
                <circle cx="5.5" cy="12" r="1.2"/><circle cx="10.5" cy="12" r="1.2"/>
              </svg>
            </span>
            <input
              :ref="(el) => { inputRefs[`${s.key}-${i}`] = el as HTMLInputElement; }"
              :value="item.text"
              type="text"
              class="min-w-0 flex-1 bg-transparent py-1 text-sm text-slate-700 outline-none placeholder:text-slate-300 dark:text-zinc-200 dark:placeholder:text-zinc-600"
              placeholder="Item…"
              @input="updateItem(s.key, i, ($event.target as HTMLInputElement).value)"
              @keydown="onKeyDown($event, s.key, i)"
            />
            <button
              type="button"
              class="shrink-0 px-0.5 text-slate-300 opacity-0 transition-opacity hover:text-red-500 group-hover:opacity-100 dark:text-zinc-600 dark:hover:text-red-400"
              tabindex="-1"
              @click="removeItem(s.key, i)"
            >
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
          </div>
        </template>
        <!-- drop indicator at bottom of list -->
        <div v-if="isDropTarget(s.key, get(s.key).length)" class="mx-1 my-0.5 h-0.5 rounded-full bg-teal-500" />
        <p v-if="!get(s.key).length" class="px-1 py-1 text-xs text-slate-400 dark:text-zinc-600">Kosong — klik + Add</p>
      </div>

      <p class="mt-3 text-xs text-slate-400 dark:text-zinc-600">Tab indent · Shift+Tab outdent · Enter baris baru</p>
    </div>
  </div>
</template>
