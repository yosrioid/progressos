import { defineStore } from 'pinia';
import { api, unwrap } from '../api';

const defaultGroups = {
  general: {
    app_name: 'ProgressOS',
    project_name: 'ProgressOS',
    tagline: 'Personal operating system',
    timezone: 'Asia/Jakarta',
  },
  appearance: {
    theme: 'system',
    favicon_url: '',
  },
  notifications: {
    daily_review_enabled: false,
    weekly_review_enabled: false,
  },
  ai: {
    provider: 'groq',
    model: 'claude-sonnet-4-6',
    api_key: '',
    groq_api_key: '',
    journal_provider: 'groq',
    usage_requests: 0,
    usage_tokens: 0,
    request_limit: 14400,
  },
};

export const timezones = [
  'Asia/Jakarta',
  'Asia/Makassar',
  'Asia/Jayapura',
  'Asia/Singapore',
  'Asia/Kuala_Lumpur',
  'Asia/Bangkok',
  'Asia/Tokyo',
  'Asia/Seoul',
  'Asia/Dubai',
  'Australia/Sydney',
  'Europe/London',
  'Europe/Amsterdam',
  'Europe/Berlin',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'UTC',
];

export const useConfigurationStore = defineStore('configuration', {
  state: () => ({
    groups: structuredClone(defaultGroups) as any,
    appVersion: 'dev' as string,
    loaded: false,
  }),
  getters: {
    general: (state) => state.groups.general || defaultGroups.general,
    appearance: (state) => state.groups.appearance || defaultGroups.appearance,
    notifications: (state) => state.groups.notifications || defaultGroups.notifications,
    ai: (state) => state.groups.ai || defaultGroups.ai,
    appName(): string {
      return this.general.app_name || 'ProgressOS';
    },
    projectName(): string {
      return this.general.project_name || this.appName;
    },
    tagline(): string {
      return this.general.tagline || 'Personal operating system';
    },
    timezone(): string {
      return this.general.timezone || 'Asia/Jakarta';
    },
    aiProvider(state) {
      return state.groups.ai?.provider || 'groq';
    },
    isAdaCode(state) {
      return state.groups.ai?.provider === 'adacode';
    },
    usagePercentage(state) {
      const usage = state.groups.ai?.usage_requests || 0;
      const limit = state.groups.ai?.request_limit || 14400;
      return limit > 0 ? Math.round((usage / limit) * 100) : 0;
    },
  },
  actions: {
    async load() {
      const data = await api.get('/api/v1/configuration').then(unwrap);
      this.groups = { ...structuredClone(defaultGroups), ...(data.configuration?.groups || {}) };
      
      // Merge ai_config (provider, model, api key status) from API response
      if (data.configuration?.ai_config) {
        this.groups.ai = {
          ...this.groups.ai,
          ...data.configuration.ai_config,
        };
      }
      
      // Load usage for current provider
      await this.fetchUsage();
      
      this.appVersion = data.configuration?.app_version || 'dev';
      this.loaded = true;
    },
    applyGroups(groups: any) {
      this.groups = { ...this.groups, ...(groups || {}) };
      this.loaded = true;
    },
    async fetchUsage(provider?: string) {
      try {
        const p = provider || this.groups.ai?.provider || 'groq';
        const data = await api.get(`/api/v1/ai/config?provider=${p}`).then(unwrap);
        if (data.usage) {
          this.groups.ai.usage_requests = data.usage.requests || 0;
          this.groups.ai.usage_tokens = data.usage.tokens || 0;
          
          // Set limit based on provider
          const isAdaCode = p === 'adacode';
          this.groups.ai.request_limit = isAdaCode ? 1000 : 14400;
          this.groups.ai.token_limit = isAdaCode ? 1000000 : 10000000;
        }
      } catch (error) {
        console.error('Failed to fetch AI usage:', error);
      }
    },
    async checkQuota() {
      try {
        const data = await api.post('/api/v1/ai/quota/check').then(unwrap);
        if (data.quota) {
          this.groups.ai.usage_requests = data.quota.usage_requests || 0;
          this.groups.ai.usage_tokens = data.quota.usage_tokens || 0;
          this.groups.ai.request_limit = data.quota.request_limit || 14400;
          this.groups.ai.token_limit = data.quota.token_limit || 10000000;
        }
      } catch (error) {
        console.error('Failed to check AI quota:', error);
      }
    },
  },
});
