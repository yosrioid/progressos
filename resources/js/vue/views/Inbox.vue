<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useInboxStore, type InboxUser } from '../stores/inbox';
import { useAuthStore } from '../stores/auth';
import { useConversationSession } from '../composables/useConversationSession';
import { useClickOutside } from '../composables/useClickOutside';

const inbox = useInboxStore();
const auth = useAuthStore();
const session = useConversationSession();

const searchQuery = ref('');
const searching = ref(false);
const bodyInput = ref('');
const fileInput = ref<HTMLInputElement | null>(null);
const pendingFile = ref<File | null>(null);
const pendingFilePreview = ref<string | null>(null);
const messagesEl = ref<HTMLElement | null>(null);
const showEmojiPicker = ref(false);
const showGifPicker = ref(false);
const gifSearch = ref('');
const gifLoading = ref(false);
const showNewChat = ref(false);
const contextMenu = ref<{ x: number; y: number; messageId: number } | null>(null);
const view = ref<'list' | 'chat'>('list');

// Click-outside for panels that live inside the inbox root
const inboxRoot = ref<HTMLElement | null>(null);
useClickOutside(inboxRoot, () => {
  showEmojiPicker.value = false;
  showGifPicker.value = false;
});

// Separate refs for each detachable panel
const newChatPanel = ref<HTMLDivElement | null>(null);
useClickOutside(newChatPanel, () => { if (showNewChat.value) showNewChat.value = false; });

const contextMenuRef = ref<HTMLDivElement | null>(null);
useClickOutside(contextMenuRef, () => { contextMenu.value = null; });

let pollInterval: ReturnType<typeof setInterval> | null = null;
let convInterval: ReturnType<typeof setInterval> | null = null;

const EMOJIS = ['😀','😂','😍','🥰','😎','🤔','😅','😭','❤️','👍','👎','🙏','🔥','✨','🎉','💯','😊','🤣','😘','😢','😡','🤯','😴','🤗','💪','🎯','🚀','💡','⚡','🌟','😏','🥳','😳','🤭','😬','🙄','😤','😩','🤩','😋','🤤','😇','🥺','😱','🫡','💀','🫶','🤝','👏','🫠'];

onMounted(async () => {
  await inbox.loadConversations();
  convInterval = setInterval(() => inbox.loadConversations(), 30_000);
});

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
  if (convInterval) clearInterval(convInterval);
});

watch(() => session.activeId.value, (id) => {
  if (pollInterval) clearInterval(pollInterval);
  if (id) {
    pollInterval = setInterval(() => session.refreshMessages(), 5_000);
  }
});

watch(() => session.messages.value.length, async () => {
  await nextTick();
  scrollBottom();
});

function scrollBottom() {
  if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
}

async function selectConversation(id: number) {
  showNewChat.value = false;
  view.value = 'chat';
  await session.openConversation(id);
  await nextTick();
  scrollBottom();
}

async function handleStartChat(user: InboxUser) {
  const id = await inbox.startConversation(user.id);
  showNewChat.value = false;
  searchQuery.value = '';
  inbox.searchResults.length = 0;
  await selectConversation(id);
}

async function handleSearchUsers() {
  if (!searchQuery.value.trim()) { inbox.searchResults.length = 0; return; }
  searching.value = true;
  try { await inbox.searchUsers(searchQuery.value); } finally { searching.value = false; }
}

function handleFileSelect(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  pendingFile.value = file;
  if (file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = (r) => { pendingFilePreview.value = r.target?.result as string; };
    reader.readAsDataURL(file);
  } else {
    pendingFilePreview.value = null;
  }
}

function removePendingFile() {
  pendingFile.value = null;
  pendingFilePreview.value = null;
  if (fileInput.value) fileInput.value.value = '';
}

async function handleSend() {
  if ((!bodyInput.value.trim() && !pendingFile.value) || !session.activeId.value) return;
  const body = bodyInput.value;
  const file = pendingFile.value ?? undefined;
  bodyInput.value = '';
  removePendingFile();
  showEmojiPicker.value = false;
  await session.sendMessage(session.activeId.value, body, file);
  await nextTick();
  scrollBottom();
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend(); }
}

function insertEmoji(emoji: string) {
  bodyInput.value += emoji;
  showEmojiPicker.value = false;
}

async function openGifPicker() {
  showEmojiPicker.value = false;
  showGifPicker.value = !showGifPicker.value;
  if (showGifPicker.value && !inbox.gifResults.length) {
    gifLoading.value = true;
    try { await inbox.searchGif(''); } finally { gifLoading.value = false; }
  }
}

async function handleGifSearch() {
  gifLoading.value = true;
  try { await inbox.searchGif(gifSearch.value); } finally { gifLoading.value = false; }
}

async function selectGif(url: string) {
  if (!session.activeId.value) return;
  showGifPicker.value = false;
  gifSearch.value = '';
  await session.sendGif(session.activeId.value, url);
  await nextTick();
  scrollBottom();
}

function openContextMenu(e: MouseEvent, messageId: number, senderId: number) {
  if (senderId !== auth.user?.id) return;
  e.preventDefault();
  contextMenu.value = { x: e.clientX, y: e.clientY, messageId };
}

async function handleDelete() {
  if (!contextMenu.value) return;
  await session.deleteMessage(contextMenu.value.messageId);
  contextMenu.value = null;
}

function goBack() {
  session.closeConversation();
  view.value = 'list';
}

function formatTime(iso: string) {
  return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function formatFileSize(bytes: number) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1024 / 1024).toFixed(1) + ' MB';
}

function avatarInitial(name: string) {
  return name ? name[0].toUpperCase() : '?';
}
</script>

<template>
  <div ref="inboxRoot" @click="contextMenu = null">
    <!-- Conversation list -->
    <template v-if="view === 'list'">
      <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-zinc-800">
        <h1 class="text-lg font-extrabold text-slate-800 dark:text-zinc-100">Pesan</h1>
        <button class="btn btn-muted px-3 py-1.5 text-sm" @click="showNewChat = !showNewChat">
          + Baru
        </button>
      </div>

      <!-- New chat search -->
      <div v-if="showNewChat" ref="newChatPanel" class="border-b border-slate-100 p-3 dark:border-zinc-800">
        <input
          v-model="searchQuery"
          class="field"
          placeholder="Cari user untuk dikirim pesan..."
          @input="handleSearchUsers"
        />
        <div v-if="searching" class="py-3 text-center text-sm text-slate-400">Mencari...</div>
        <div v-else-if="inbox.searchResults.length" class="mt-2 divide-y divide-slate-50 dark:divide-zinc-800">
          <button
            v-for="u in inbox.searchResults"
            :key="u.id"
            class="flex w-full items-center gap-3 py-3 text-left"
            @click="handleStartChat(u)"
          >
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-teal-100 font-bold text-teal-700 dark:bg-teal-900/30 dark:text-teal-400">
              {{ avatarInitial(u.name) }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate font-semibold text-slate-800 dark:text-zinc-200">{{ u.name }}</p>
              <p class="truncate text-sm text-slate-400">{{ u.email }}</p>
            </div>
          </button>
        </div>
        <div v-else-if="searchQuery && !searching" class="py-3 text-center text-sm text-slate-400">Tidak ada user ditemukan</div>
      </div>

      <!-- Conversation list -->
      <div class="flex-1 overflow-y-auto divide-y divide-slate-50 dark:divide-zinc-800/50">
        <button
          v-for="conv in inbox.conversations"
          :key="conv.id"
          class="flex w-full items-center gap-3 px-4 py-3.5 text-left transition hover:bg-slate-50 dark:hover:bg-zinc-800/50"
          @click="selectConversation(conv.id)"
        >
          <div class="relative shrink-0">
            <img v-if="conv.other_user.avatar_url" :src="conv.other_user.avatar_url" class="h-12 w-12 rounded-full object-cover" />
            <div v-else class="flex h-12 w-12 items-center justify-center rounded-full bg-teal-100 text-lg font-bold text-teal-700 dark:bg-teal-900/30 dark:text-teal-400">
              {{ avatarInitial(conv.other_user.name) }}
            </div>
            <span v-if="conv.unread_count > 0" class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[11px] font-bold text-white">{{ conv.unread_count > 9 ? '9+' : conv.unread_count }}</span>
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate font-extrabold text-slate-800 dark:text-zinc-200">{{ conv.other_user.name }}</p>
            <p class="truncate text-sm text-slate-400 dark:text-zinc-500">
              <template v-if="conv.last_message?.deleted">Pesan dihapus</template>
              <template v-else-if="conv.last_message?.type === 'image'">📷 Gambar</template>
              <template v-else-if="conv.last_message?.type === 'file'">📎 {{ conv.last_message.file_name }}</template>
              <template v-else-if="conv.last_message?.type === 'gif'">🎞️ GIF</template>
              <template v-else>{{ conv.last_message?.body ?? '' }}</template>
            </p>
          </div>
        </button>
        <div v-if="!inbox.conversations.length" class="flex flex-col items-center justify-center gap-3 py-16 text-slate-400 dark:text-zinc-600">
          <svg class="h-12 w-12 opacity-30" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          <p class="font-semibold">Belum ada percakapan</p>
          <button class="btn btn-muted text-sm" @click="showNewChat = true">Mulai chat baru</button>
        </div>
      </div>
    </template>

    <!-- Chat view -->
    <template v-else-if="view === 'chat' && session.activeConversation.value">
      <!-- Header -->
      <div class="flex items-center gap-3 border-b border-slate-100 px-3 py-3 dark:border-zinc-800">
        <button class="p-1 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200" @click="goBack">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <img v-if="session.activeConversation.value.other_user.avatar_url" :src="session.activeConversation.value.other_user.avatar_url" class="h-9 w-9 rounded-full object-cover" />
        <div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-teal-100 font-bold text-teal-700 dark:bg-teal-900/30 dark:text-teal-400">
          {{ avatarInitial(session.activeConversation.value.other_user.name) }}
        </div>
        <span class="flex-1 font-extrabold text-slate-800 dark:text-zinc-100">{{ session.activeConversation.value.other_user.name }}</span>
        <button class="p-1 text-slate-400 hover:text-teal-600" @click="session.refreshMessages">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
        </button>
      </div>

      <!-- Messages -->
      <div ref="messagesEl" class="max-h-[calc(100svh-16rem)] overflow-y-auto space-y-1 p-3">
        <div v-if="session.loadingMessages.value" class="py-8 text-center text-sm text-slate-400">Memuat pesan...</div>
        <template v-else>
          <div
            v-for="msg in session.messages.value"
            :key="msg.id"
            class="flex"
            :class="msg.sender_id === auth.user?.id ? 'justify-end' : 'justify-start'"
            @contextmenu.prevent="openContextMenu($event, msg.id, msg.sender_id)"
          >
            <div
              class="max-w-[78%] rounded-2xl px-3.5 py-2.5 text-sm transition-opacity"
              :class="[
                msg.sender_id === auth.user?.id
                  ? 'rounded-br-sm bg-teal-600 text-white'
                  : 'rounded-bl-sm bg-slate-100 text-slate-800 dark:bg-zinc-800 dark:text-zinc-200',
                msg.pending ? 'opacity-60' : 'opacity-100',
              ]"
            >
              <template v-if="msg.deleted">
                <em class="text-xs opacity-60">Pesan dihapus</em>
              </template>
              <template v-else>
                <img v-if="msg.type === 'image'" :src="msg.file_url ?? ''" class="mb-1.5 max-w-full rounded-xl" />
                <img v-else-if="msg.type === 'gif'" :src="msg.body ?? ''" class="mb-1.5 max-w-[200px] rounded-xl" />
                <a
                  v-else-if="msg.type === 'file'"
                  :href="msg.file_url ?? '#'"
                  target="_blank"
                  class="flex items-center gap-2 underline opacity-90"
                >
                  <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                  <span class="truncate text-xs">{{ msg.file_name }}</span>
                  <span v-if="msg.file_size" class="shrink-0 text-[10px] opacity-70">{{ formatFileSize(msg.file_size) }}</span>
                </a>
                <p v-if="msg.body && msg.type !== 'gif'" class="whitespace-pre-wrap break-words">{{ msg.body }}</p>
              </template>
              <span class="mt-0.5 flex items-center justify-end gap-1 text-[10px] opacity-60">
                {{ formatTime(msg.created_at) }}
                <template v-if="msg.sender_id === auth.user?.id && !msg.deleted">
                  <svg v-if="msg.pending" class="h-3 w-3 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  <svg v-else class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </template>
              </span>
            </div>
          </div>
        </template>
      </div>

      <!-- File preview -->
      <div v-if="pendingFile" class="flex items-center gap-2 border-t border-slate-100 px-4 py-2 dark:border-zinc-800">
        <img v-if="pendingFilePreview" :src="pendingFilePreview" class="h-12 w-12 rounded-lg object-cover" />
        <svg v-else class="h-10 w-10 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <span class="flex-1 truncate text-sm text-slate-600 dark:text-zinc-400">{{ pendingFile.name }}</span>
        <button class="text-slate-400 hover:text-red-500" @click="removePendingFile">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Emoji picker -->
      <div v-if="showEmojiPicker" class="grid grid-cols-10 gap-1 border-t border-slate-100 p-3 dark:border-zinc-800">
        <button
          v-for="emoji in EMOJIS"
          :key="emoji"
          class="flex h-8 w-8 items-center justify-center rounded-lg text-lg hover:bg-slate-100 dark:hover:bg-zinc-800"
          @click="insertEmoji(emoji)"
        >{{ emoji }}</button>
      </div>

      <!-- GIF picker -->
      <div v-if="showGifPicker" class="border-t border-slate-100 p-3 dark:border-zinc-800">
        <input
          v-model="gifSearch"
          class="field mb-2"
          placeholder="Cari GIF..."
          @input="handleGifSearch"
        />
        <div v-if="gifLoading" class="py-6 text-center text-sm text-slate-400">Memuat...</div>
        <div v-else-if="!inbox.gifResults.length" class="py-6 text-center text-sm text-slate-400">
          {{ gifSearch ? 'GIF tidak ditemukan' : 'Tambahkan TENOR_API_KEY di .env untuk GIF' }}
        </div>
        <div v-else class="grid grid-cols-3 gap-1.5 max-h-52 overflow-y-auto">
          <button
            v-for="gif in inbox.gifResults"
            :key="gif.id"
            class="overflow-hidden rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500"
            @click="selectGif(gif.url)"
          >
            <img :src="gif.url" :alt="gif.title" class="h-20 w-full object-cover" loading="lazy" />
          </button>
        </div>
      </div>

      <!-- Input bar -->
      <div class="flex items-end gap-2 border-t border-slate-100 px-3 py-2.5 dark:border-zinc-800">
        <button class="shrink-0 p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300" :class="showEmojiPicker ? 'text-teal-500' : ''" @click="showEmojiPicker = !showEmojiPicker; showGifPicker = false">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 13s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
        </button>
        <button class="shrink-0 p-1.5 text-xs font-extrabold leading-none text-slate-400 hover:text-teal-500" :class="showGifPicker ? 'text-teal-500' : ''" @click="openGifPicker">GIF</button>
        <label class="shrink-0 cursor-pointer p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
          <input ref="fileInput" type="file" class="sr-only" @change="handleFileSelect" />
        </label>
        <textarea
          v-model="bodyInput"
          rows="1"
          class="field max-h-24 flex-1 resize-none py-2"
          placeholder="Pesan..."
          @keydown="handleKeydown"
        />
        <button
          class="shrink-0 rounded-xl bg-teal-600 p-2.5 text-white transition hover:bg-teal-700 disabled:opacity-40"
          :disabled="!bodyInput.trim() && !pendingFile"
          @click="handleSend"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
      </div>
    </template>

    <!-- Context menu -->
    <div
      v-if="contextMenu"
      ref="contextMenuRef"
      class="fixed z-50 rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
      :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }"
    >
      <button class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20" @click="handleDelete">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        Hapus untuk semua
      </button>
    </div>
  </div>
</template>
