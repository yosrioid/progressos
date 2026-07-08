<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import { confirmAction, toast } from '../feedback';

interface Session {
  id: number;
  title: string;
  context_type: 'general' | 'journal' | 'project';
  messages_count: number;
  updated_at: string;
}

interface Message {
  id: number;
  role: 'user' | 'assistant';
  content: string;
  tokens: number;
  created_at: string;
  streaming?: boolean;
}

const route = useRoute();
const router = useRouter();

const sessions = ref<Session[]>([]);
const activeSession = ref<Session | null>(null);
const messages = ref<Message[]>([]);
const loadingSessions = ref(true);
const loadingMessages = ref(false);
const sending = ref(false);
const input = ref('');
const messagesEnd = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLTextAreaElement | null>(null);

const aiConfig = ref<{ provider: string; model: string }>({ provider: 'groq', model: 'llama-3.1-8b-instant' });

// Streaming state — separated from `sending` so the UI can show a
// "Stop" button only while the upstream connection is still open.
// `streamController` lets the user abort mid-flight; we also abort
// it on unmount so a navigation away can't leak an open SSE handle.
let streamController: AbortController | null = null;
let streamAborted = false;

const showNewModal = ref(false);
const newContextType = ref<'general' | 'journal' | 'project'>('general');
const creating = ref(false);

const contextLabels: Record<string, string> = {
  general: 'Chat Umum',
  journal: 'Bahas Jurnal',
  project: 'Bahas Proyek',
};

const contextIcons: Record<string, string> = {
  general: '💬',
  journal: '📔',
  project: '📁',
};

async function loadSessions() {
  loadingSessions.value = true;
  try {
    const res = await api.get('/api/v1/chat-sessions').then(unwrap);
    sessions.value = res.sessions ?? [];
  } catch {
    toast({ tone: 'error', title: 'Gagal memuat sesi' });
  } finally {
    loadingSessions.value = false;
  }
}

async function openSession(session: Session) {
  if (activeSession.value?.id === session.id) return;
  activeSession.value = session;
  messages.value = [];
  loadingMessages.value = true;
  router.replace({ query: { session: String(session.id) } });
  try {
    const res = await api.get(`/api/v1/chat-sessions/${session.id}`).then(unwrap);
    messages.value = res.messages ?? [];
    await scrollToBottom();
  } catch {
    toast({ tone: 'error', title: 'Gagal memuat pesan' });
  } finally {
    loadingMessages.value = false;
  }
}

async function createSession() {
  creating.value = true;
  try {
    const res = await api.post('/api/v1/chat-sessions', { context_type: newContextType.value }).then(unwrap);
    sessions.value.unshift(res.session);
    showNewModal.value = false;
    await openSession(res.session);
    nextTick(() => inputRef.value?.focus());
  } catch {
    toast({ tone: 'error', title: 'Gagal membuat sesi' });
  } finally {
    creating.value = false;
  }
}

async function deleteSession(session: Session) {
  const ok = await confirmAction({ title: 'Hapus sesi?', message: `"${session.title}" akan dihapus permanen.`, confirmLabel: 'Hapus' });
  if (!ok) return;
  try {
    await api.delete(`/api/v1/chat-sessions/${session.id}`);
    sessions.value = sessions.value.filter(s => s.id !== session.id);
    if (activeSession.value?.id === session.id) {
      activeSession.value = null;
      messages.value = [];
      router.replace({ query: {} });
    }
  } catch {
    toast({ tone: 'error', title: 'Gagal menghapus' });
  }
}

async function send() {
  const content = input.value.trim();
  if (!content || !activeSession.value || sending.value) return;

  input.value = '';
  if (inputRef.value) { inputRef.value.style.height = 'auto'; }
  sending.value = true;
  streamAborted = false;

  const tempUserId = Date.now();
  const userMsg: Message = {
    id: tempUserId,
    role: 'user',
    content,
    tokens: 0,
    created_at: new Date().toISOString(),
  };
  // Placeholder for the assistant's reply. Its `id` is a temporary
  // negative-ish number so Vue can track it while streaming — we
  // replace it with the real DB id when 'done' arrives.
  const assistantMsg: Message = {
    id: -Date.now(),
    role: 'assistant',
    content: '',
    tokens: 0,
    created_at: new Date().toISOString(),
    streaming: true,
  };
  messages.value.push(userMsg, assistantMsg);
  await scrollToBottom();

  streamController = new AbortController();

  try {
    const response = await fetch(`/api/v1/chat-sessions/${activeSession.value.id}/messages/stream`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'text/event-stream',
      },
      body: JSON.stringify({ content }),
      credentials: 'include',
      signal: streamController.signal,
    });

    if (!response.ok || !response.body) {
      // Streaming endpoint is unavailable (e.g. nginx buffering the SSE
      // response, or a misconfigured proxy). Fall back to the regular
      // non-streaming POST so the user still gets a reply.
      await fallbackNonStreaming(content, userMsg, assistantMsg);
      return;
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    // SSE parsing: events are terminated by a blank line ("\n\n"),
    // so we accumulate chunks and split on that boundary. We also
    // tolerate CRLF line endings (per the SSE spec).
    const dispatchLine = (line: string) => {
      if (line.startsWith('event:')) {
        (assistantMsg as any).__nextEvent = line.slice(6).trim();
      } else if (line.startsWith('data:')) {
        const data = line.slice(5).trim();
        if (!data) return;
        try {
          const payload = JSON.parse(data);
          handleStreamEvent((assistantMsg as any).__nextEvent ?? 'message', payload, assistantMsg);
        } catch {
          // Non-JSON payloads are ignored; the protocol guarantees JSON.
        }
      }
    };

    const handleStreamEvent = (event: string, payload: any, target: Message) => {
      switch (event) {
        case 'start':
          // Optional metadata about provider/model. No UI side-effect
          // today but we keep the hook so we can show "(via Groq · llama)"
          // in a future iteration without re-plumbing.
          break;
        case 'chunk':
          target.content = (target.content || '') + (payload.content ?? '');
          scrollToBottom();
          break;
        case 'fallback':
          toast({ tone: 'info', title: `Beralih ke ${payload.to}`, message: `${payload.from} gagal, mencoba ulang.` });
          break;
        case 'done':
          if (payload.message) {
            target.id = payload.message.id;
            target.tokens = payload.message.tokens ?? 0;
            target.created_at = payload.message.created_at ?? target.created_at;
          }
          target.streaming = false;
          if (payload.session) {
            activeSession.value = payload.session;
            const idx = sessions.value.findIndex(s => s.id === payload.session.id);
            if (idx >= 0) sessions.value[idx] = payload.session;
          }
          scrollToBottom();
          break;
        case 'error':
          // Remove the empty assistant placeholder so the user doesn't
          // see an empty bubble next to their question.
          const errIdx = messages.value.indexOf(target);
          if (errIdx >= 0) messages.value.splice(errIdx, 1);
          toast({
            tone: 'error',
            title: 'AI tidak bisa menjawab',
            message: payload.message ?? 'Coba lagi nanti.',
          });
          // Put the user's text back so they can retry.
          input.value = content;
          break;
      }
    };

    while (true) {
      const { value, done } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true });

      // Split on the SSE event boundary. We keep the trailing partial
      // event in the buffer for the next chunk.
      const parts = buffer.split(/\r?\n\r?\n/);
      buffer = parts.pop() ?? '';
      for (const part of parts) {
        const lines = part.split(/\r?\n/);
        (assistantMsg as any).__nextEvent = null;
        for (const line of lines) {
          dispatchLine(line);
        }
      }
    }
  } catch (e: any) {
    if (e?.name === 'AbortError') {
      // User pressed Stop — don't toast, just mark the assistant
      // placeholder as finished and keep whatever streamed in.
      streamAborted = true;
      assistantMsg.streaming = false;
      assistantMsg.content = (assistantMsg.content || '') + (assistantMsg.content ? '' : '\n_[dihentikan]_');
      toast({ tone: 'info', title: 'Dihentikan', message: 'Generasi AI dihentikan.' });
    } else {
      // Network or parse error — fall back to the non-streaming POST
      // so the user isn't stuck staring at a half-rendered bubble.
      await fallbackNonStreaming(content, userMsg, assistantMsg);
    }
  } finally {
    sending.value = false;
    streamController = null;
    nextTick(() => inputRef.value?.focus());
  }
}

// Non-streaming fallback used when the SSE endpoint errors or the
// browser blocks streaming (some proxies / corporate firewalls do).
// The semantics mirror the streaming path: optimistic message added,
// then replaced with the real response, errors restore the input.
async function fallbackNonStreaming(content: string, userMsg: Message, assistantMsg: Message) {
  try {
    const res = await api
      .post(`/api/v1/chat-sessions/${activeSession.value!.id}/messages`, { content })
      .then(unwrap);
    const idx = messages.value.indexOf(assistantMsg);
    if (idx >= 0) messages.value[idx] = res.message;
    if (res.session) {
      activeSession.value = res.session;
      const sIdx = sessions.value.findIndex(s => s.id === res.session.id);
      if (sIdx >= 0) sessions.value[sIdx] = res.session;
    }
    await scrollToBottom();
  } catch (e: any) {
    const idx = messages.value.indexOf(assistantMsg);
    if (idx >= 0) messages.value.splice(idx, 1);
    toast({
      tone: 'error',
      title: 'Gagal mengirim',
      message: e?.response?.data?.message ?? 'Coba lagi.',
    });
    input.value = content;
  }
}

function stopStream() {
  if (streamController) {
    streamController.abort();
  }
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    send();
  }
}

function autoResize(e: Event) {
  const el = e.target as HTMLTextAreaElement;
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 160) + 'px';
}

async function scrollToBottom() {
  await nextTick();
  messagesEnd.value?.scrollIntoView({ behavior: 'smooth' });
}

function formatTime(iso: string) {
  return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function formatDate(iso: string) {
  const d = new Date(iso);
  const today = new Date();
  if (d.toDateString() === today.toDateString()) return 'Hari ini';
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

onMounted(async () => {
  await loadSessions();
  // Fetch the active provider/model so the footer caption matches
  // reality. Failures here are silent — the footer just keeps the
  // default label.
  try {
    const res = await api.get('/api/v1/ai/config').then(unwrap);
    if (res?.provider) aiConfig.value.provider = res.provider;
    if (res?.model) aiConfig.value.model = res.model;
  } catch { /* keep defaults */ }
  const sessionId = route.query.session;
  if (sessionId) {
    const found = sessions.value.find(s => s.id === Number(sessionId));
    if (found) await openSession(found);
  }
});

watch(() => route.query.session, async (id) => {
  if (!id) return;
  const found = sessions.value.find(s => s.id === Number(id));
  if (found && found.id !== activeSession.value?.id) await openSession(found);
});

// If the user navigates away mid-stream, abort the in-flight request
// so we don't leak server-side connections or accumulate orphaned
// sockets on the upstream provider.
onBeforeUnmount(() => {
  if (streamController) {
    streamController.abort();
  }
});
</script>

<template>
  <div class="-mx-3 -my-4 sm:-mx-5 sm:-my-6 flex gap-0 sm:gap-5 h-[calc(100dvh-7.5rem)] sm:h-[calc(100dvh-4.375rem)] overflow-hidden">

    <!-- Session list -->
    <div class="hidden sm:flex w-56 shrink-0 flex-col gap-3 px-5 py-5">
      <button class="btn btn-primary w-full text-sm" @click="showNewModal = true">+ Sesi Baru</button>

      <div class="flex-1 overflow-y-auto space-y-1 pr-1">
        <div v-if="loadingSessions" class="space-y-2">
          <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded-xl bg-slate-100 dark:bg-zinc-800" />
        </div>
        <div v-else-if="sessions.length === 0" class="rounded-xl border border-dashed border-slate-200 dark:border-zinc-700 p-4 text-center">
          <p class="text-xs text-slate-400 dark:text-zinc-600">Belum ada sesi</p>
        </div>
        <button
          v-for="s in sessions"
          :key="s.id"
          :class="[
            'group w-full rounded-xl border px-3 py-2.5 text-left transition-colors',
            activeSession?.id === s.id
              ? 'border-teal-200 bg-teal-50 dark:border-teal-800 dark:bg-teal-900/20'
              : 'border-transparent hover:border-slate-200 hover:bg-slate-50 dark:hover:border-zinc-700 dark:hover:bg-zinc-800/50',
          ]"
          @click="openSession(s)"
        >
          <div class="flex items-start justify-between gap-1">
            <span class="text-xs font-extrabold truncate text-slate-700 dark:text-zinc-300 leading-tight">
              {{ contextIcons[s.context_type] }} {{ s.title }}
            </span>
            <button
              class="mt-0.5 shrink-0 opacity-0 group-hover:opacity-100 text-slate-300 hover:text-red-400 transition-all"
              @click.stop="deleteSession(s)"
            >
              <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>
          <p class="mt-0.5 text-[10px] text-slate-400 dark:text-zinc-600">{{ formatDate(s.updated_at) }}</p>
        </button>
      </div>
    </div>

    <!-- Chat area -->
    <div class="card flex flex-1 flex-col min-w-0 overflow-hidden p-0">

      <!-- Mobile session bar -->
      <div class="flex items-center gap-2 border-b border-slate-100 px-3 py-2 sm:hidden dark:border-zinc-800">
        <div class="flex-1 overflow-x-auto">
          <div class="flex gap-1.5 min-w-0">
            <button
              v-for="s in sessions"
              :key="s.id"
              :class="[
                'shrink-0 rounded-lg px-2.5 py-1 text-xs font-bold transition-colors whitespace-nowrap',
                activeSession?.id === s.id
                  ? 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400'
                  : 'text-slate-500 hover:bg-slate-100 dark:text-zinc-500 dark:hover:bg-zinc-800',
              ]"
              @click="openSession(s)"
            >{{ contextIcons[s.context_type] }} {{ s.title }}</button>
          </div>
        </div>
        <button class="shrink-0 btn btn-primary px-2.5 py-1 text-xs" @click="showNewModal = true">+</button>
      </div>

      <!-- Empty state -->
      <div v-if="!activeSession" class="flex flex-1 flex-col items-center justify-center gap-4 p-10 text-center">
        <div class="grid h-14 w-14 place-items-center rounded-2xl bg-teal-600 text-white shadow-lg shadow-teal-900/10">
          <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div>
          <p class="font-extrabold text-slate-700 dark:text-zinc-300">ProgressOS AI</p>
          <p class="mt-1 text-sm text-slate-400 dark:text-zinc-500">Pilih sesi atau mulai yang baru.</p>
        </div>
        <div class="mt-1 grid grid-cols-3 gap-3 max-w-sm w-full">
          <button
            v-for="type in ['general', 'journal', 'project'] as const"
            :key="type"
            class="rounded-xl border border-slate-200 p-3 text-center hover:border-teal-400 hover:bg-teal-50 transition-colors dark:border-zinc-700 dark:hover:border-teal-700 dark:hover:bg-teal-900/10"
            @click="newContextType = type; createSession()"
          >
            <p class="text-xl">{{ contextIcons[type] }}</p>
            <p class="mt-1 text-xs font-extrabold text-slate-600 dark:text-zinc-400">{{ contextLabels[type] }}</p>
          </button>
        </div>
      </div>

      <template v-else>
        <!-- Header -->
        <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 dark:border-zinc-800">
          <span class="text-lg">{{ contextIcons[activeSession.context_type] }}</span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-extrabold text-slate-800 dark:text-zinc-200">{{ activeSession.title }}</p>
            <p class="text-[11px] text-slate-400 dark:text-zinc-600">{{ contextLabels[activeSession.context_type] }}</p>
          </div>
        </div>

        <!-- Messages -->
        <div class="flex-1 overflow-y-auto">
          <div class="mx-auto max-w-2xl px-5 py-5 space-y-5">
            <div v-if="loadingMessages" class="space-y-4">
              <div v-for="i in 3" :key="i" class="h-12 animate-pulse rounded-xl bg-slate-100 dark:bg-zinc-800" />
            </div>
            <template v-else>
              <p v-if="messages.length === 0" class="text-center text-sm text-slate-400 dark:text-zinc-600 py-6">
                Mulai percakapan — ketik pesan di bawah.
              </p>

              <div
                v-for="msg in messages"
                :key="msg.id"
                :class="['flex gap-3', msg.role === 'user' ? 'justify-end' : 'justify-start']"
              >
                <!-- AI avatar -->
                <div
                  v-if="msg.role === 'assistant'"
                  class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-teal-100 text-[10px] font-extrabold text-teal-700 dark:bg-teal-900/40 dark:text-teal-400 mt-0.5"
                >AI</div>

                <div class="max-w-[80%] space-y-1">
                  <div
                    :class="[
                      'rounded-2xl px-4 py-2.5 text-sm font-medium leading-relaxed',
                      msg.role === 'user'
                        ? 'rounded-tr-sm bg-teal-600 text-white'
                        : 'rounded-tl-sm bg-slate-100 text-slate-800 dark:bg-zinc-800 dark:text-zinc-200',
                    ]"
                  >
                    <!-- Empty streaming bubble shows typing dots so the
                         user gets feedback that the request is in-flight
                         even before the first token arrives. -->
                    <p v-if="msg.role === 'assistant' && msg.streaming && !msg.content" class="flex items-center gap-1.5">
                      <span v-for="i in 3" :key="i" class="h-1.5 w-1.5 rounded-full bg-slate-400 dark:bg-zinc-500 animate-bounce" :style="{ animationDelay: `${(i - 1) * 160}ms` }" />
                    </p>
                    <p v-else class="whitespace-pre-wrap">
                      {{ msg.content }}<span v-if="msg.streaming" class="ml-0.5 inline-block h-3 w-1.5 -mb-0.5 bg-teal-500 animate-pulse align-middle" />
                    </p>
                  </div>
                  <p :class="['text-[10px] text-slate-400 dark:text-zinc-600', msg.role === 'user' ? 'text-right' : 'pl-1']">
                    {{ formatTime(msg.created_at) }}<span v-if="msg.role === 'assistant' && msg.tokens"> · {{ msg.tokens }} token</span>
                  </p>
                </div>

                <!-- User avatar -->
                <div
                  v-if="msg.role === 'user'"
                  class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-teal-600 text-[10px] font-extrabold text-white mt-0.5"
                >U</div>
              </div>

              <div ref="messagesEnd" />
            </template>
          </div>
        </div>

        <!-- Input -->
        <div class="border-t border-slate-100 px-5 py-4 dark:border-zinc-800">
          <div class="flex items-end gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 focus-within:border-teal-400 transition-colors dark:border-zinc-700 dark:bg-zinc-800 dark:focus-within:border-teal-600">
            <textarea
              ref="inputRef"
              v-model="input"
              rows="1"
              class="flex-1 resize-none bg-transparent text-sm font-medium text-slate-800 placeholder:text-slate-400 focus:outline-none dark:text-zinc-200 dark:placeholder:text-zinc-500"
              placeholder="Pesan... (Enter kirim · Shift+Enter baris baru)"
              :disabled="sending"
              style="max-height: 160px;"
              @keydown="onKeydown"
              @input="autoResize"
            />
            <button
              v-if="!sending"
              :class="[
                'grid h-8 w-8 shrink-0 place-items-center rounded-lg transition-colors',
                input.trim()
                  ? 'bg-teal-600 text-white hover:bg-teal-700'
                  : 'bg-slate-200 text-slate-400 dark:bg-zinc-700 dark:text-zinc-500 cursor-not-allowed',
              ]"
              :disabled="!input.trim()"
              @click="send"
            >
              <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
            <button
              v-else
              class="grid h-8 w-8 shrink-0 place-items-center rounded-lg transition-colors bg-red-500 text-white hover:bg-red-600"
              @click="stopStream"
              title="Stop generasi"
            >
              <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h12v12H6z"/></svg>
            </button>
          </div>
          <p class="mt-1.5 text-center text-[10px] text-slate-300 dark:text-zinc-700">{{ aiConfig.provider }} · {{ aiConfig.model }} · 12 pesan terakhir dikirim ke AI</p>
        </div>
      </template>
    </div>
  </div>

  <!-- New session modal -->
  <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
    <div v-if="showNewModal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 px-4 pb-6 sm:items-center" @click.self="showNewModal = false">
      <div class="card w-full max-w-sm p-6 space-y-4">
        <p class="font-extrabold text-slate-800 dark:text-zinc-200">Sesi Chat Baru</p>
        <div class="space-y-2">
          <button
            v-for="type in ['general', 'journal', 'project'] as const"
            :key="type"
            :class="[
              'w-full rounded-xl border px-4 py-3 text-left transition-colors',
              newContextType === type
                ? 'border-teal-500 bg-teal-50 dark:border-teal-600 dark:bg-teal-900/20'
                : 'border-slate-200 hover:border-teal-300 dark:border-zinc-700 dark:hover:border-teal-700',
            ]"
            @click="newContextType = type"
          >
            <p class="text-sm font-extrabold text-slate-800 dark:text-zinc-200">{{ contextIcons[type] }} {{ contextLabels[type] }}</p>
            <p class="mt-0.5 text-xs text-slate-400 dark:text-zinc-500">
              {{ type === 'general' ? 'Chat bebas tentang apa saja' : type === 'journal' ? 'AI baca jurnal 30 hari terakhir sebagai konteks' : 'AI baca daftar proyekmu sebagai konteks' }}
            </p>
          </button>
        </div>
        <div class="flex justify-end gap-2 pt-1">
          <button class="btn" @click="showNewModal = false">Batal</button>
          <button class="btn btn-primary" :disabled="creating" @click="createSession">
            {{ creating ? 'Membuat...' : 'Mulai Chat' }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>
