import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';
import { onMounted, onUnmounted, ref } from 'vue';

export const api = axios.create({
  headers: { Accept: 'application/json' },
  withCredentials: true,
});

// Get CSRF token from meta tag (set by Laravel)
function getCsrfToken(): string | null {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? null;
}

// Refresh CSRF token from server (fresh session cookie + new meta token)
async function refreshCsrf(): Promise<string | null> {
  try {
    await axios.get('/sanctum/csrf-cookie', {
      withCredentials: true,
      baseURL: window.location.origin,
    });
    // Reload the meta tag — Laravel sets new XSRF cookie + exposes token via meta
    const token = getCsrfToken();
    if (token) {
      api.defaults.headers.common['X-CSRF-TOKEN'] = token;
      api.defaults.headers.common['X-XSRF-TOKEN'] = token;
    }
    return token;
  } catch {
    return null;
  }
}

// Ensure token is present in defaults on init
if (typeof document !== 'undefined') {
  const initial = getCsrfToken();
  if (initial) {
    api.defaults.headers.common['X-CSRF-TOKEN'] = initial;
    api.defaults.headers.common['X-XSRF-TOKEN'] = initial;
  }
}

// Attach token to mutating requests (stateful Sanctum)
api.interceptors.request.use((config) => {
  const method = (config.method ?? 'get').toLowerCase();
  const mutating = ['post', 'put', 'patch', 'delete'].includes(method);
  if (mutating) {
    const token = getCsrfToken();
    if (token) {
      config.headers['X-CSRF-TOKEN'] = token;
      config.headers['X-XSRF-TOKEN'] = token;
    }
  }
  return config;
});

// Global response interceptor: handle 401, auto-refresh on 419, and dispatch error events
api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const status = error.response?.status;
    const original = error.config as InternalAxiosRequestConfig & { __csrfRetried?: boolean };

    // 419 = CSRF token mismatch → refresh and retry once
    if (status === 419 && original && !original.__csrfRetried) {
      original.__csrfRetried = true;
      const token = await refreshCsrf();
      if (token) {
        original.headers = original.headers ?? {};
        original.headers['X-CSRF-TOKEN'] = token;
        original.headers['X-XSRF-TOKEN'] = token;
        return api.request(original);
      }
    }

    // 401 → redirect to login (unless already there)
    if (status === 401) {
      const path = window.location.pathname;
      const safe =
        path.startsWith('/login') ||
        path.startsWith('/forgot-password') ||
        path.startsWith('/reset-password') ||
        path.startsWith('/share/');
      if (!safe && !window.location.hash.includes('redirect=')) {
        window.location.href = '/login';
      }
    }

    // Emit a global event so views/stores can react (loading-state cleanup, toast, ...)
    if (typeof window !== 'undefined') {
      window.dispatchEvent(
        new CustomEvent('api:error', {
          detail: { status, message: extractMessage(error), url: original?.url },
        }),
      );
    }

    return Promise.reject(error);
  },
);

function extractMessage(error: AxiosError): string {
  const data = error.response?.data as { message?: string; error?: string } | undefined;
  return data?.message ?? data?.error ?? error.message ?? 'Request failed';
}

export function unwrap<T = any>(response: { data: T }) {
  return response.data;
}

/**
 * Lightweight toast helper driven by the global `api:error` event.
 * Mount once in App.vue. Views/components don't need to handle errors manually.
 */
export function useApiErrorToast() {
  const message = ref<string | null>(null);
  let timer: number | undefined;
  const onError = (e: Event) => {
    const detail = (e as CustomEvent).detail as { status?: number; message?: string };
    // Don't spam toasts on auth/redirect — page navigation handles it
    if (detail.status === 401 || detail.status === 419) return;
    message.value = detail.message ?? 'Something went wrong';
    clearTimeout(timer);
    timer = window.setTimeout(() => (message.value = null), 4000);
  };
  onMounted(() => window.addEventListener('api:error', onError));
  onUnmounted(() => {
    window.removeEventListener('api:error', onError);
    clearTimeout(timer);
  });
  return { message };
}
