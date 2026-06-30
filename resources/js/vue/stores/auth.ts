import { defineStore } from 'pinia';
import { api, unwrap } from '../api';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as any,
    booted: false,
  }),
  getters: {
    isAdmin: (state) => state.user?.is_admin === true,
  },
  actions: {
    async boot() {
      try {
        const data = await api.get('/api/me').then(unwrap);
        this.user = data.user;
      } catch {
        this.user = null;
      } finally {
        this.booted = true;
      }
    },
    async login(payload: { email: string; password: string; remember?: boolean }) {
      const data = await api.post('/api/login', payload).then(unwrap);
      this.user = data.user;
    },
    async register(payload: { name: string; email: string; password: string; password_confirmation: string; timezone?: string }) {
      const data = await api.post('/api/register', payload).then(unwrap);
      this.user = data.user;
    },
    async logout() {
      await api.post('/api/logout');
      this.user = null;
    },
    async updateProfile(payload: { name: string; email: string; timezone: string; theme: string }) {
      const data = await api.patch('/api/profile', payload).then(unwrap);
      this.user = data.user;
    },
    async updatePassword(payload: { current_password: string; password: string; password_confirmation: string }) {
      await api.put('/api/profile/password', payload);
    },
    async updateAvatar(payload: FormData) {
      const data = await api.post('/api/profile/avatar', payload, { headers: { 'Content-Type': 'multipart/form-data' } }).then(unwrap);
      this.user = data.user;
    },
  },
});
