<script setup lang="ts">
import Link from '@tiptap/extension-link';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { watch } from 'vue';

const props = defineProps<{ modelValue: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit,
    Link.configure({ openOnClick: false }),
  ],
  editorProps: {
    attributes: { class: 'prose prose-sm max-w-none focus:outline-none min-h-[8rem] px-4 py-3' },
  },
  onUpdate: ({ editor }) => emit('update:modelValue', editor.getHTML()),
});

watch(() => props.modelValue, (val) => {
  if (editor.value && editor.value.getHTML() !== val) {
    editor.value.commands.setContent(val, false);
  }
});
</script>

<template>
  <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
    <div v-if="editor" class="flex flex-wrap gap-1 border-b border-slate-100 bg-slate-50 px-2 py-1.5 dark:border-zinc-700 dark:bg-zinc-800">
      <button type="button" class="rounded px-2 py-0.5 text-xs font-bold text-slate-600 hover:bg-slate-200 dark:text-zinc-300 dark:hover:bg-zinc-700" :class="editor.isActive('bold') ? 'bg-slate-200 text-slate-900 dark:bg-zinc-700 dark:text-zinc-100' : ''" title="Bold" @click="editor.chain().focus().toggleBold().run()"><strong>B</strong></button>
      <button type="button" class="rounded px-2 py-0.5 text-xs font-bold text-slate-600 hover:bg-slate-200 dark:text-zinc-300 dark:hover:bg-zinc-700" :class="editor.isActive('italic') ? 'bg-slate-200 text-slate-900 dark:bg-zinc-700 dark:text-zinc-100' : ''" title="Italic" @click="editor.chain().focus().toggleItalic().run()"><em>I</em></button>
      <button type="button" class="rounded px-2 py-0.5 text-xs font-bold text-slate-600 hover:bg-slate-200 dark:text-zinc-300 dark:hover:bg-zinc-700" :class="editor.isActive('strike') ? 'bg-slate-200 text-slate-900 dark:bg-zinc-700 dark:text-zinc-100' : ''" title="Strikethrough" @click="editor.chain().focus().toggleStrike().run()"><s>S</s></button>
      <span class="mx-0.5 w-px self-stretch bg-slate-200 dark:bg-zinc-600" />
      <button type="button" class="rounded px-2 py-0.5 text-xs font-bold text-slate-600 hover:bg-slate-200 dark:text-zinc-300 dark:hover:bg-zinc-700" :class="editor.isActive('heading', { level: 2 }) ? 'bg-slate-200 text-slate-900 dark:bg-zinc-700 dark:text-zinc-100' : ''" title="Heading 2" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H2</button>
      <button type="button" class="rounded px-2 py-0.5 text-xs font-bold text-slate-600 hover:bg-slate-200 dark:text-zinc-300 dark:hover:bg-zinc-700" :class="editor.isActive('heading', { level: 3 }) ? 'bg-slate-200 text-slate-900 dark:bg-zinc-700 dark:text-zinc-100' : ''" title="Heading 3" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">H3</button>
      <span class="mx-0.5 w-px self-stretch bg-slate-200 dark:bg-zinc-600" />
      <button type="button" class="rounded px-2 py-0.5 text-xs font-bold text-slate-600 hover:bg-slate-200 dark:text-zinc-300 dark:hover:bg-zinc-700" :class="editor.isActive('bulletList') ? 'bg-slate-200 text-slate-900 dark:bg-zinc-700 dark:text-zinc-100' : ''" title="Bullet list" @click="editor.chain().focus().toggleBulletList().run()">• List</button>
      <button type="button" class="rounded px-2 py-0.5 text-xs font-bold text-slate-600 hover:bg-slate-200 dark:text-zinc-300 dark:hover:bg-zinc-700" :class="editor.isActive('orderedList') ? 'bg-slate-200 text-slate-900 dark:bg-zinc-700 dark:text-zinc-100' : ''" title="Ordered list" @click="editor.chain().focus().toggleOrderedList().run()">1. List</button>
      <span class="mx-0.5 w-px self-stretch bg-slate-200 dark:bg-zinc-600" />
      <button type="button" class="rounded px-2 py-0.5 text-xs font-bold text-slate-600 hover:bg-slate-200 dark:text-zinc-300 dark:hover:bg-zinc-700" :class="editor.isActive('code') ? 'bg-slate-200 text-slate-900 dark:bg-zinc-700 dark:text-zinc-100' : ''" title="Inline code" @click="editor.chain().focus().toggleCode().run()">Code</button>
      <button type="button" class="rounded px-2 py-0.5 text-xs font-bold text-slate-600 hover:bg-slate-200 dark:text-zinc-300 dark:hover:bg-zinc-700" :class="editor.isActive('blockquote') ? 'bg-slate-200 text-slate-900 dark:bg-zinc-700 dark:text-zinc-100' : ''" title="Blockquote" @click="editor.chain().focus().toggleBlockquote().run()">"</button>
      <span class="mx-0.5 w-px self-stretch bg-slate-200 dark:bg-zinc-600" />
      <button type="button" class="rounded px-2 py-0.5 text-xs font-bold text-slate-600 hover:bg-slate-200 dark:text-zinc-300 dark:hover:bg-zinc-700" title="Undo" @click="editor.chain().focus().undo().run()">↩</button>
      <button type="button" class="rounded px-2 py-0.5 text-xs font-bold text-slate-600 hover:bg-slate-200 dark:text-zinc-300 dark:hover:bg-zinc-700" title="Redo" @click="editor.chain().focus().redo().run()">↪</button>
    </div>
    <EditorContent :editor="editor" />
  </div>
</template>
