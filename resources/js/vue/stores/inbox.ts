import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import { api, unwrap } from '../api';

export type InboxUser = { id: number; name: string; email?: string; avatar_url: string | null };
export type InboxMessage = {
  id: number;
  conversation_id: number;
  sender_id: number;
  sender_name: string | null;
  sender_avatar: string | null;
  body: string | null;
  type: 'text' | 'image' | 'file';
  file_name: string | null;
  file_size: number | null;
  file_mime: string | null;
  file_url: string | null;
  deleted: boolean;
  created_at: string;
};
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

  const activeConversation = computed(() =>
    conversations.value.find((c) => c.id === activeId.value) ?? null,
  );

  async function loadConversations() {
    const data = await api.get('/api/v1/inbox/conversations').then(unwrap);
    conversations.value = data.conversations ?? [];
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
    messages.value = data.messages ?? [];
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
    const form = new FormData();
    if (body.trim()) form.append('body', body.trim());
    if (file) form.append('file', file);
    const data = await api.post(`/api/v1/inbox/conversations/${conversationId}/messages`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then(unwrap);
    messages.value.push(data.message);
    const conv = conversations.value.find((c) => c.id === conversationId);
    if (conv) {
      conv.last_message = data.message;
      conv.last_message_at = data.message.created_at;
      conversations.value = [conv, ...conversations.value.filter((c) => c.id !== conversationId)];
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
    searchResults, activeConversation,
    loadConversations, loadUnread, openConversation, refreshMessages,
    startConversation, sendMessage, deleteMessage, searchUsers,
    closeConversation, togglePanel,
  };
});
