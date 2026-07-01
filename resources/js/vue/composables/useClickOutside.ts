import { onMounted, onBeforeUnmount, type Ref } from 'vue';

/**
 * Calls `handler` when a mousedown occurs outside the element(s) bound by the refs.
 * Accepts a single Vue ref or an array of Vue refs to DOM elements.
 */
export function useClickOutside(
  refs: Ref<HTMLElement | null> | Array<Ref<HTMLElement | null>>,
  handler: (event: MouseEvent) => void,
): void {
  function resolved(): HTMLElement[] {
    const list = Array.isArray(refs) ? refs : [refs];
    return list.map((r) => r.value).filter((el): el is HTMLElement => el instanceof HTMLElement);
  }

  function onMouseDown(event: MouseEvent) {
    const target = event.target;
    if (!(target instanceof Node)) return;
    const inside = resolved().some((el) => el.contains(target));
    if (!inside) handler(event);
  }

  onMounted(() => document.addEventListener('mousedown', onMouseDown));
  onBeforeUnmount(() => document.removeEventListener('mousedown', onMouseDown));
}