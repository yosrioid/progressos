import { onMounted, onBeforeUnmount, type Ref } from 'vue';

/**
 * Calls `handler` when a pointerdown occurs outside the element(s) bound by the refs.
 * Uses `pointerdown` (not `mousedown`) so it works on touch devices too — `pointerdown`
 * fires for mouse, touch, and pen input, and precedes the synthetic `click` so the handler
 * runs before any `click` toggle on the trigger button.
 * Accepts a single Vue ref or an array of Vue refs to DOM elements.
 */
export function useClickOutside(
  refs: Ref<HTMLElement | null> | Array<Ref<HTMLElement | null>>,
  handler: (event: PointerEvent) => void,
): void {
  function resolved(): HTMLElement[] {
    const list = Array.isArray(refs) ? refs : [refs];
    return list.map((r) => r.value).filter((el): el is HTMLElement => el instanceof HTMLElement);
  }

  function onPointerDown(event: PointerEvent) {
    const target = event.target;
    if (!(target instanceof Node)) return;
    const inside = resolved().some((el) => el.contains(target));
    if (!inside) handler(event);
  }

  onMounted(() => document.addEventListener('pointerdown', onPointerDown));
  onBeforeUnmount(() => document.removeEventListener('pointerdown', onPointerDown));
}