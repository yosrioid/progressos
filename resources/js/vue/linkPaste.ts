import { nextTick, type Ref } from 'vue';

function pastedUrl(event: ClipboardEvent) {
  const text = event.clipboardData?.getData('text/plain')?.trim() || '';
  return /^https?:\/\/\S+$/i.test(text) ? text : '';
}

export async function pasteLinkOverSelection(event: ClipboardEvent, model: Ref<Record<string, any>>, key: string) {
  const url = pastedUrl(event);
  const target = event.target as HTMLTextAreaElement | HTMLInputElement | null;
  if (!url || !target || target.selectionStart === null || target.selectionEnd === null) return;

  const selected = target.value.slice(target.selectionStart, target.selectionEnd);
  if (!selected.trim()) return;

  event.preventDefault();
  const before = target.value.slice(0, target.selectionStart);
  const after = target.value.slice(target.selectionEnd);
  const replacement = `[${selected}](${url})`;
  model.value[key] = `${before}${replacement}${after}`;

  await nextTick();
  const cursor = before.length + replacement.length;
  target.setSelectionRange(cursor, cursor);
}
