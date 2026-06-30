import { ref, computed } from 'vue';
import { api, unwrap } from '../api';
import { useAuthStore } from '../stores/auth';
import { useInboxStore, type InboxMessage } from '../stores/inbox';

// Per-component instance — each caller (ChatBubble, Inbox) gets its own
// activeId + messages state so desktop bubble and inbox page don't interfere.
export function useConversationSession() {
  const inbox = useInboxStore();
  const auth = useAuthStore();

  const activeId = ref<number | null>(null);
  const messages = ref<InboxMessage[]>([]);
  const loadingMessages = ref(false);

  const activeConversation = computed(() =>
    inbox.conversations.find((c) => c.id === activeId.value) ?? null,
  );

  async function openConversation(id: number) {
    activeId.value = id;
    loadingMessages.value = true;
    try {
      const data = await api.get(`/api/v1/inbox/conversations/${id}/messages`).then(unwrap);
      messages.value = data.messages ?? [];
      const conv = inbox.conversations.find((c) => c.id === id);
      if (conv) conv.unread_count = 0;
      await inbox.loadUnread();
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
    const conv = inbox.conversations.find((c) => c.id === activeId.value);
    if (conv) conv.unread_count = 0;
  }

  async function sendMessage(conversationId: number, body: string, file?: File) {
    const tempId = -Date.now();
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

      const idx = messages.value.findIndex((m) => m.id === tempId);
      if (idx !== -1) messages.value.splice(idx, 1, { ...data.message, pending: false });

      _updateConvAfterSend(conversationId, data.message);
    } catch (err) {
      const idx = messages.value.findIndex((m) => m.id === tempId);
      if (idx !== -1) messages.value.splice(idx, 1);
      throw err;
    }
  }

  async function sendGif(conversationId: number, gifUrl: string) {
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

      _updateConvAfterSend(conversationId, data.message);
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

  function closeConversation() {
    activeId.value = null;
    messages.value = [];
  }

  function _updateConvAfterSend(conversationId: number, message: InboxMessage) {
    const conv = inbox.conversations.find((c) => c.id === conversationId);
    if (!conv) return;
    conv.last_message = message;
    conv.last_message_at = message.created_at;
    const pos = inbox.conversations.indexOf(conv);
    if (pos > 0) {
      inbox.conversations.splice(pos, 1);
      inbox.conversations.unshift(conv);
    }
  }

  return {
    activeId,
    messages,
    loadingMessages,
    activeConversation,
    openConversation,
    refreshMessages,
    sendMessage,
    sendGif,
    deleteMessage,
    closeConversation,
  };
}
