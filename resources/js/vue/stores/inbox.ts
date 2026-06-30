import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import { api, unwrap } from '../api';
import { useAuthStore } from './auth';

export type InboxUser = { id: number; name: string; email?: string; avatar_url: string | null };
export type InboxMessage = {
  id: number;
  conversation_id: number;
  sender_id: number;
  sender_name: string | null;
  sender_avatar: string | null;
  body: string | null;
  type: 'text' | 'image' | 'file' | 'gif';
  file_name: string | null;
  file_size: number | null;
  file_mime: string | null;
  file_url: string | null;
  deleted: boolean;
  created_at: string;
  pending?: boolean;
};
export type GifResult = { id: string; url: string; title: string };
export type InboxConversation = {
  id: number;
  other_user: InboxUser;
  last_message: InboxMessage | null;
  unread_count: number;
  last_message_at: string | null;
};

export const useInboxStore = defineStore('inbox', () => {
  const open = ref(false);
  const conversations = ref<InboxConversation[]>([]);
  const activeId = ref<number | null>(null);
  const messages = ref<InboxMessage[]>([]);
  const unreadTotal = ref(0);
  const loadingMessages = ref(false);
  const searchResults = ref<InboxUser[]>([]);
  const gifResults = ref<GifResult[]>([]);

  const activeConversation = computed(() =>
    conversations.value.find((c) => c.id === activeId.value) ?? null,
  );

  async function loadConversations() {
    const data = await api.get('/api/v1/inbox/conversations').then(unwrap);
    const fresh: InboxConversation[] = data.conversations ?? [];

    if (!conversations.value.length) {
      conversations.value = fresh;
      return;
    }

    // Merge in-place so array reference stays stable (no re-render)
    const freshMap = new Map(fresh.map((c) => [c.id, c]));
    for (const existing of conversations.value) {
      const fc = freshMap.get(existing.id);
      if (fc) {
        existing.unread_count = fc.unread_count;
        existing.last_message = fc.last_message;
        existing.last_message_at = fc.last_message_at;
        freshMap.delete(existing.id);
      }
    }
    // Append brand-new conversations
    for (const nc of freshMap.values()) conversations.value.push(nc);

    // Re-sort in-place by last_message_at desc
    conversations.value.sort((a, b) => {
      const ta = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
      const tb = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;
      return tb - ta;
    });
  }

  async function loadUnread() {
    const res = await api.get('/api/v1/inbox/unread');
    unreadTotal.value = res.data?.unread ?? 0;
  }

  async function openConversation(id: number) {
    activeId.value = id;
    loadingMessages.value = true;
    try {
      const data = await api.get(`/api/v1/inbox/conversations/${id}/messages`).then(unwrap);
      messages.value = data.messages ?? [];
      const conv = conversations.value.find((c) => c.id === id);
      if (conv) conv.unread_count = 0;
      await loadUnread();
    } finally {
      loadingMessages.value = false;
    }
  }

  async function refreshMessages() {
    if (!activeId.value) return;
    const data = await api.get(`/api/v1/inbox/conversations/${activeId.value}/messages`).then(unwrap);
    const incoming: InboxMessage[] = data.messages ?? [];
    const existingIds = new Set(messages.value.map((m) => m.id));
    for (const msg of incoming) {
      if (existingIds.has(msg.id)) {
        const idx = messages.value.findIndex((m) => m.id === msg.id);
        if (idx !== -1 && messages.value[idx].deleted !== msg.deleted) {
          messages.value[idx] = msg;
        }
      } else {
        messages.value.push(msg);
      }
    }
    const conv = conversations.value.find((c) => c.id === activeId.value);
    if (conv) conv.unread_count = 0;
  }

  async function startConversation(userId: number): Promise<number> {
    const data = await api.post('/api/v1/inbox/conversations', { user_id: userId }).then(unwrap);
    const conv: InboxConversation = data.conversation;
    if (!conversations.value.find((c) => c.id === conv.id)) {
      conversations.value.unshift(conv);
    }
    return conv.id;
  }

  async function sendMessage(conversationId: number, body: string, file?: File) {
    const auth = useAuthStore();
    const tempId = -Date.now();

    // Optimistic: push immediately so UI is instant, no component blink
    const optimistic: InboxMessage = {
      id: tempId,
      conversation_id: conversationId,
      sender_id: auth.user!.id,
      sender_name: null,
      sender_avatar: null,
      body: body.trim() || null,
      type: file ? (file.type.startsWith('image/') ? 'image' : 'file') : 'text',
      file_name: file?.name ?? null,
      file_size: file?.size ?? null,
      file_mime: file?.type ?? null,
      file_url: null,
      deleted: false,
      created_at: new Date().toISOString(),
      pending: true,
    };
    messages.value.push(optimistic);

    try {
      const form = new FormData();
      if (body.trim()) form.append('body', body.trim());
      if (file) form.append('file', file);
      const data = await api.post(
        `/api/v1/inbox/conversations/${conversationId}/messages`,
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      ).then(unwrap);

      // Replace optimistic message in-place with confirmed one
      const idx = messages.value.findIndex((m) => m.id === tempId);
      if (idx !== -1) messages.value.splice(idx, 1, { ...data.message, pending: false });

      // Update conversation in-place — never replace the array
      const conv = conversations.value.find((c) => c.id === conversationId);
      if (conv) {
        conv.last_message = data.message;
        conv.last_message_at = data.message.created_at;
        // Move to top via splice (mutates in-place, no new array)
        const pos = conversations.value.indexOf(conv);
        if (pos > 0) {
          conversations.value.splice(pos, 1);
          conversations.value.unshift(conv);
        }
      }
    } catch (err) {
      // Remove optimistic message on failure
      const idx = messages.value.findIndex((m) => m.id === tempId);
      if (idx !== -1) messages.value.splice(idx, 1);
      throw err;
    }
  }

  async function searchGif(q: string) {
    const data = await api.get('/api/v1/inbox/gif', { params: { q: q || 'trending' } }).then(unwrap);
    gifResults.value = data.gifs ?? [];
  }

  async function sendGif(conversationId: number, gifUrl: string) {
    const auth = useAuthStore();
    const tempId = -Date.now();
    const optimistic: InboxMessage = {
      id: tempId,
      conversation_id: conversationId,
      sender_id: auth.user!.id,
      sender_name: null,
      sender_avatar: null,
      body: gifUrl,
      type: 'gif',
      file_name: null,
      file_size: null,
      file_mime: null,
      file_url: null,
      deleted: false,
      created_at: new Date().toISOString(),
      pending: true,
    };
    messages.value.push(optimistic);

    try {
      const form = new FormData();
      form.append('gif_url', gifUrl);
      const data = await api.post(
        `/api/v1/inbox/conversations/${conversationId}/messages`,
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      ).then(unwrap);

      const idx = messages.value.findIndex((m) => m.id === tempId);
      if (idx !== -1) messages.value.splice(idx, 1, { ...data.message, pending: false });

      const conv = conversations.value.find((c) => c.id === conversationId);
      if (conv) {
        conv.last_message = data.message;
        conv.last_message_at = data.message.created_at;
        const pos = conversations.value.indexOf(conv);
        if (pos > 0) {
          conversations.value.splice(pos, 1);
          conversations.value.unshift(conv);
        }
      }
    } catch (err) {
      const idx = messages.value.findIndex((m) => m.id === tempId);
      if (idx !== -1) messages.value.splice(idx, 1);
      throw err;
    }
  }

  async function deleteMessage(messageId: number) {
    const data = await api.delete(`/api/v1/inbox/messages/${messageId}`).then(unwrap);
    const idx = messages.value.findIndex((m) => m.id === messageId);
    if (idx !== -1) messages.value[idx] = data.message;
  }

  async function searchUsers(q: string) {
    const data = await api.get('/api/v1/inbox/users', { params: { q } }).then(unwrap);
    searchResults.value = data.users ?? [];
  }

  function closeConversation() {
    activeId.value = null;
    messages.value = [];
  }

  function togglePanel() {
    open.value = !open.value;
    if (!open.value) closeConversation();
  }

  return {
    open, conversations, activeId, messages, unreadTotal, loadingMessages,
    searchResults, gifResults, activeConversation,
    loadConversations, loadUnread, openConversation, refreshMessages,
    startConversation, sendMessage, sendGif, deleteMessage, searchUsers, searchGif,
    closeConversation, togglePanel,
  };
});
