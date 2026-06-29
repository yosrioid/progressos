<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';
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

  const tempMsg: Message = { id: Date.now(), role: 'user', content, tokens: 0, created_at: new Date().toISOString() };
  messages.value.push(tempMsg);
  await scrollToBottom();

  try {
    const res = await api.post(`/api/v1/chat-sessions/${activeSession.value.id}/messages`, { content }).then(unwrap);
    messages.value[messages.value.length - 1] = { ...tempMsg };
    messages.value.push(res.message);
    const idx = sessions.value.findIndex(s => s.id === activeSession.value?.id);
    if (idx >= 0) sessions.value[idx] = res.session;
    activeSession.value = res.session;
    await scrollToBottom();
  } catch (e: any) {
    messages.value.pop();
    input.value = content;
    toast({ tone: 'error', title: 'Gagal mengirim', message: e?.response?.data?.message ?? 'Coba lagi.' });
  } finally {
    sending.value = false;
    nextTick(() => inputRef.value?.focus());
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
                    <p class="whitespace-pre-wrap">{{ msg.content }}</p>
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

              <!-- Typing indicator -->
              <div v-if="sending" class="flex gap-3 justify-start">
                <div class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-teal-100 text-[10px] font-extrabold text-teal-700 dark:bg-teal-900/40 dark:text-teal-400 mt-0.5">AI</div>
                <div class="flex items-center gap-1.5 rounded-2xl rounded-tl-sm bg-slate-100 px-4 py-3 dark:bg-zinc-800">
                  <span v-for="i in 3" :key="i" class="h-1.5 w-1.5 rounded-full bg-slate-400 dark:bg-zinc-500 animate-bounce" :style="{ animationDelay: `${(i - 1) * 160}ms` }" />
                </div>
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
              :class="[
                'grid h-8 w-8 shrink-0 place-items-center rounded-lg transition-colors',
                input.trim() && !sending
                  ? 'bg-teal-600 text-white hover:bg-teal-700'
                  : 'bg-slate-200 text-slate-400 dark:bg-zinc-700 dark:text-zinc-500 cursor-not-allowed',
              ]"
              :disabled="sending || !input.trim()"
              @click="send"
            >
              <svg v-if="sending" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
              <svg v-else class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
          </div>
          <p class="mt-1.5 text-center text-[10px] text-slate-300 dark:text-zinc-700">Groq · llama-3.1-8b-instant · 12 pesan terakhir dikirim ke AI</p>
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
