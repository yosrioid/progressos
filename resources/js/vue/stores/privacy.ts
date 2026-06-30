import { computed, onScopeDispose, ref } from 'vue';
import { defineStore } from 'pinia';

const SENSITIVE_PATHS = ['/money', '/bills'];
const UNLOCK_MS = 5 * 60 * 1000;

export const usePrivacyStore = defineStore('privacy', () => {
  const uid = ref<string | null>(null);
  const pinKey = () => uid.value ? `privacy-pin-${uid.value}` : null;
  const hideKey = () => uid.value ? `privacy-hide-${uid.value}` : null;
  const unlockKey = () => uid.value ? `privacy-unlocked-at-${uid.value}` : null;

  const hideSensitive = ref(false);
  const pinStored = ref('');
  const unlockedAt = ref<number | null>(null);

  function init(userId: string) {
    uid.value = userId;
    pinStored.value = localStorage.getItem(`privacy-pin-${userId}`) ?? '';
    hideSensitive.value = localStorage.getItem(`privacy-hide-${userId}`) === '1';
    const savedUnlock = sessionStorage.getItem(`privacy-unlocked-at-${userId}`);
    unlockedAt.value = savedUnlock ? Number(savedUnlock) : null;
  }

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
    const k = hideKey(); if (k) localStorage.setItem(k, hideSensitive.value ? '1' : '0');
  }

  function tryUnlock(input: string): boolean {
    if (input === pinStored.value) {
      unlockedAt.value = Date.now();
      const k = unlockKey(); if (k) sessionStorage.setItem(k, String(unlockedAt.value));
      return true;
    }
    return false;
  }

  function setPin(newPin: string) {
    pinStored.value = newPin;
    const k = pinKey(); if (k) localStorage.setItem(k, newPin);
    unlockedAt.value = Date.now();
    const uk = unlockKey(); if (uk) sessionStorage.setItem(uk, String(unlockedAt.value));
  }

  function removePin() {
    pinStored.value = '';
    const k = pinKey(); if (k) localStorage.removeItem(k);
    unlockedAt.value = null;
    const uk = unlockKey(); if (uk) sessionStorage.removeItem(uk);
  }

  function lock() {
    unlockedAt.value = null;
    const k = unlockKey(); if (k) sessionStorage.removeItem(k);
  }

  function bump() {
    if (unlockedAt.value !== null) {
      unlockedAt.value = Date.now();
      const k = unlockKey(); if (k) sessionStorage.setItem(k, String(unlockedAt.value));
    }
  }

  return { hideSensitive, hasPin, isUnlocked, isSensitivePath, toggleHide, tryUnlock, setPin, removePin, lock, bump, init };
});
