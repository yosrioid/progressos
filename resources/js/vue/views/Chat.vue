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
  <!-- Full-height chat layout, no card wrapper -->
  <div class="-mx-4 -mt-4 md:-mx-8 md:-mt-8 flex h-[calc(100vh-4rem)] lg:h-screen overflow-hidden">

    <!-- Sidebar -->
    <div class="flex w-60 shrink-0 flex-col bg-slate-900 dark:bg-zinc-950">
      <!-- Top -->
      <div class="flex items-center justify-between gap-2 px-3 pt-4 pb-3">
        <p class="text-xs font-extrabold uppercase tracking-widest text-slate-400">AI Chat</p>
        <button
          class="grid h-7 w-7 place-items-center rounded-lg border border-slate-700 text-slate-400 hover:border-slate-500 hover:text-white transition-colors"
          title="Sesi baru"
          @click="showNewModal = true"
        >
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        </button>
      </div>

      <!-- Session list -->
      <div class="flex-1 overflow-y-auto px-2 pb-4 space-y-0.5">
        <div v-if="loadingSessions" class="space-y-1.5 p-2">
          <div v-for="i in 5" :key="i" class="h-9 animate-pulse rounded-lg bg-slate-800" />
        </div>
        <div v-else-if="sessions.length === 0" class="px-3 py-6 text-center">
          <p class="text-xs text-slate-500">Belum ada sesi.</p>
          <button class="mt-2 text-xs font-semibold text-teal-400 hover:underline" @click="showNewModal = true">Mulai chat</button>
        </div>
        <button
          v-for="s in sessions"
          :key="s.id"
          :class="[
            'group w-full rounded-lg px-3 py-2 text-left transition-colors',
            activeSession?.id === s.id
              ? 'bg-slate-700 text-white'
              : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200',
          ]"
          @click="openSession(s)"
        >
          <div class="flex items-center gap-2 min-w-0">
            <span class="text-xs shrink-0">{{ contextIcons[s.context_type] }}</span>
            <span class="truncate text-xs font-semibold flex-1">{{ s.title }}</span>
            <button
              class="shrink-0 opacity-0 group-hover:opacity-100 hover:text-red-400 transition-all"
              @click.stop="deleteSession(s)"
            >
              <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>
          <p class="mt-0.5 text-[10px] text-slate-600 truncate">{{ formatDate(s.updated_at) }}</p>
        </button>
      </div>
    </div>

    <!-- Main chat -->
    <div class="flex flex-1 flex-col min-w-0 bg-white dark:bg-zinc-900">

      <!-- Empty state -->
      <div v-if="!activeSession" class="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
        <div class="grid h-16 w-16 place-items-center rounded-2xl bg-teal-600 text-white shadow-lg shadow-teal-900/20">
          <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div>
          <p class="text-xl font-extrabold text-slate-800 dark:text-zinc-200">ProgressOS AI</p>
          <p class="mt-1 text-sm text-slate-400 dark:text-zinc-500">Pilih sesi di kiri atau mulai yang baru.</p>
        </div>
        <div class="mt-2 grid grid-cols-3 gap-3 text-left max-w-lg w-full">
          <button
            v-for="type in ['general', 'journal', 'project'] as const"
            :key="type"
            class="rounded-xl border border-slate-200 p-3 hover:border-teal-400 hover:bg-teal-50 transition-colors dark:border-zinc-700 dark:hover:border-teal-600 dark:hover:bg-teal-900/10"
            @click="newContextType = type; createSession()"
          >
            <p class="text-lg">{{ contextIcons[type] }}</p>
            <p class="mt-1 text-xs font-extrabold text-slate-700 dark:text-zinc-300">{{ contextLabels[type] }}</p>
          </button>
        </div>
      </div>

      <template v-else>
        <!-- Header -->
        <div class="flex items-center gap-3 border-b border-slate-100 bg-white px-6 py-3 dark:border-zinc-800 dark:bg-zinc-900">
          <span class="text-xl">{{ contextIcons[activeSession.context_type] }}</span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-extrabold text-slate-800 dark:text-zinc-200">{{ activeSession.title }}</p>
            <p class="text-[11px] text-slate-400 dark:text-zinc-600">{{ contextLabels[activeSession.context_type] }} · Groq llama-3.1-8b-instant</p>
          </div>
        </div>

        <!-- Messages -->
        <div class="flex-1 overflow-y-auto">
          <div class="mx-auto max-w-3xl px-4 py-6 space-y-6">
            <div v-if="loadingMessages" class="space-y-6">
              <div v-for="i in 3" :key="i" class="flex gap-4">
                <div class="h-8 w-8 shrink-0 animate-pulse rounded-full bg-slate-100 dark:bg-zinc-800" />
                <div class="flex-1 space-y-2 pt-1">
                  <div class="h-3 w-3/4 animate-pulse rounded bg-slate-100 dark:bg-zinc-800" />
                  <div class="h-3 w-1/2 animate-pulse rounded bg-slate-100 dark:bg-zinc-800" />
                </div>
              </div>
            </div>
            <template v-else>
              <p v-if="messages.length === 0" class="text-center text-sm text-slate-400 dark:text-zinc-600">
                Mulai percakapan — AI siap membantu.
              </p>

              <div
                v-for="msg in messages"
                :key="msg.id"
                :class="['flex gap-4', msg.role === 'user' ? 'flex-row-reverse' : 'flex-row']"
              >
                <!-- Avatar -->
                <div
                  :class="[
                    'grid h-8 w-8 shrink-0 place-items-center rounded-full text-[11px] font-extrabold',
                    msg.role === 'user'
                      ? 'bg-teal-600 text-white'
                      : 'bg-slate-800 text-slate-200 dark:bg-zinc-700',
                  ]"
                >{{ msg.role === 'user' ? 'U' : 'AI' }}</div>

                <!-- Content -->
                <div :class="['min-w-0 flex-1', msg.role === 'user' ? 'flex flex-col items-end' : '']">
                  <div
                    :class="[
                      'rounded-2xl px-4 py-3 text-sm leading-relaxed',
                      msg.role === 'user'
                        ? 'bg-teal-600 text-white rounded-tr-sm max-w-[85%]'
                        : 'bg-slate-50 text-slate-800 dark:bg-zinc-800 dark:text-zinc-200 rounded-tl-sm',
                    ]"
                  >
                    <p class="whitespace-pre-wrap font-medium">{{ msg.content }}</p>
                  </div>
                  <p :class="['mt-1 text-[10px]', msg.role === 'user' ? 'text-slate-400 dark:text-zinc-600 pr-1' : 'text-slate-400 dark:text-zinc-600 pl-1']">
                    {{ formatTime(msg.created_at) }}
                    <span v-if="msg.role === 'assistant' && msg.tokens"> · {{ msg.tokens }} token</span>
                  </p>
                </div>
              </div>

              <!-- Typing indicator -->
              <div v-if="sending" class="flex gap-4">
                <div class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-slate-800 text-[11px] font-extrabold text-slate-200 dark:bg-zinc-700">AI</div>
                <div class="flex items-center gap-1.5 rounded-2xl rounded-tl-sm bg-slate-50 px-4 py-3 dark:bg-zinc-800">
                  <span v-for="i in 3" :key="i" class="h-2 w-2 rounded-full bg-slate-400 dark:bg-zinc-500 animate-bounce" :style="{ animationDelay: `${(i - 1) * 160}ms` }" />
                </div>
              </div>

              <div ref="messagesEnd" />
            </template>
          </div>
        </div>

        <!-- Input area -->
        <div class="border-t border-slate-100 bg-white px-4 py-4 dark:border-zinc-800 dark:bg-zinc-900">
          <div class="mx-auto max-w-3xl">
            <div class="flex items-end gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 focus-within:border-teal-400 transition-colors dark:border-zinc-700 dark:bg-zinc-800 dark:focus-within:border-teal-600">
              <textarea
                ref="inputRef"
                v-model="input"
                rows="1"
                class="flex-1 resize-none bg-transparent text-sm font-medium text-slate-800 placeholder:text-slate-400 focus:outline-none dark:text-zinc-200 dark:placeholder:text-zinc-600"
                placeholder="Pesan... (Enter kirim · Shift+Enter baris baru)"
                :disabled="sending"
                style="max-height: 160px;"
                @keydown="onKeydown"
                @input="autoResize"
              />
              <button
                :class="[
                  'grid h-8 w-8 shrink-0 place-items-center rounded-xl transition-colors',
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
            <p class="mt-2 text-center text-[10px] text-slate-300 dark:text-zinc-700">Powered by Groq · llama-3.1-8b-instant · Hanya 12 pesan terakhir yang dikirim ke AI</p>
          </div>
        </div>
      </template>
    </div>
  </div>

  <!-- New session modal -->
  <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
    <div v-if="showNewModal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 px-4 pb-6 sm:items-center" @click.self="showNewModal = false">
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
