import { ref } from 'vue';
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
  type: 'text' | 'image' | 'file' | 'gif';
  file_name: string | null;
  file_size: number | null;
  file_mime: string | null;
  file_url: string | null;
  deleted: boolean;
  created_at: string;
  pending?: boolean;
};
export type InboxConversation = {
  id: number;
  other_user: InboxUser;
  last_message: InboxMessage | null;
  unread_count: number;
  last_message_at: string | null;
};
export type GifResult = { id: string; url: string; title: string };

export const useInboxStore = defineStore('inbox', () => {
  // Shared roster — both ChatBubble and Inbox.vue read from these.
  const open = ref(false);
  const conversations = ref<InboxConversation[]>([]);
  const unreadTotal = ref(0);
  const searchResults = ref<InboxUser[]>([]);
  const gifResults = ref<GifResult[]>([]);

  async function loadConversations() {
    const data = await api.get('/api/v1/inbox/conversations').then(unwrap);
    const fresh: InboxConversation[] = data.conversations ?? [];

    if (!conversations.value.length) {
      conversations.value = fresh;
      return;
    }

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
    for (const nc of freshMap.values()) conversations.value.push(nc);

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

  async function startConversation(userId: number): Promise<number> {
    const data = await api.post('/api/v1/inbox/conversations', { user_id: userId }).then(unwrap);
    const conv: InboxConversation = data.conversation;
    if (!conversations.value.find((c) => c.id === conv.id)) {
      conversations.value.unshift(conv);
    }
    return conv.id;
  }

  async function searchUsers(q: string) {
    const data = await api.get('/api/v1/inbox/users', { params: { q } }).then(unwrap);
    searchResults.value = data.users ?? [];
  }

  async function searchGif(q: string) {
    const data = await api.get('/api/v1/inbox/gif', { params: { q: q || 'trending' } }).then(unwrap);
    gifResults.value = data.gifs ?? [];
  }

  function togglePanel() {
    open.value = !open.value;
  }

  return {
    open,
    conversations,
    unreadTotal,
    searchResults,
    gifResults,
    loadConversations,
    loadUnread,
    startConversation,
    searchUsers,
    searchGif,
    togglePanel,
  };
});
