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
  },
  actions: {
    async load() {
      const data = await api.get('/api/v1/configuration').then(unwrap);
      this.groups = { ...structuredClone(defaultGroups), ...(data.configuration?.groups || {}) };
      this.appVersion = data.configuration?.app_version || 'dev';
      this.loaded = true;
    },
    applyGroups(groups: any) {
      this.groups = { ...this.groups, ...(groups || {}) };
      this.loaded = true;
    },
  },
});
