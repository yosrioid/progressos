import { computed, onScopeDispose, ref } from 'vue';
import { defineStore } from 'pinia';

const SENSITIVE_PATHS = ['/money', '/bills'];
const UNLOCK_MS = 5 * 60 * 1000;

export const usePrivacyStore = defineStore('privacy', () => {
  const hideSensitive = ref(localStorage.getItem('privacy-hide') === '1');
  const pinStored = ref(localStorage.getItem('privacy-pin') ?? '');
  const unlockedAt = ref<number | null>(
    sessionStorage.getItem('privacy-unlocked-at')
      ? Number(sessionStorage.getItem('privacy-unlocked-at'))
      : null,
  );

  // Reactive clock so isUnlocked re-evaluates every 30s without user interaction.
  const now = ref(Date.now());
  const _tick = setInterval(() => { now.value = Date.now(); }, 30_000);
  onScopeDispose(() => clearInterval(_tick));

  const hasPin = computed(() => pinStored.value.length > 0);

  const isUnlocked = computed(() => {
    if (!hasPin.value) return true;
    if (unlockedAt.value === null) return false;
    return now.value - unlockedAt.value < UNLOCK_MS;
  });

  function isSensitivePath(path: string) {
    return SENSITIVE_PATHS.some((p) => path === p || path.startsWith(p + '/'));
  }

  function toggleHide() {
    hideSensitive.value = !hideSensitive.value;
    localStorage.setItem('privacy-hide', hideSensitive.value ? '1' : '0');
  }

  function tryUnlock(input: string): boolean {
    if (input === pinStored.value) {
      unlockedAt.value = Date.now();
      sessionStorage.setItem('privacy-unlocked-at', String(unlockedAt.value));
      return true;
    }
    return false;
  }

  function setPin(newPin: string) {
    pinStored.value = newPin;
    localStorage.setItem('privacy-pin', newPin);
    unlockedAt.value = Date.now();
    sessionStorage.setItem('privacy-unlocked-at', String(unlockedAt.value));
  }

  function removePin() {
    pinStored.value = '';
    localStorage.removeItem('privacy-pin');
    unlockedAt.value = null;
    sessionStorage.removeItem('privacy-unlocked-at');
  }

  function lock() {
    unlockedAt.value = null;
    sessionStorage.removeItem('privacy-unlocked-at');
  }

  return { hideSensitive, hasPin, isUnlocked, isSensitivePath, toggleHide, tryUnlock, setPin, removePin, lock };
});
