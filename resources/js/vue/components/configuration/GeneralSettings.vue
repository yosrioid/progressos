<script setup lang="ts">
import { computed } from 'vue';
import { timezones } from '../../stores/configuration';
import SettingsSection from './SettingsSection.vue';
import SettingsField from './SettingsField.vue';

interface GeneralSettings {
    app_name: string;
    project_name: string;
    tagline: string;
    timezone: string;
}

const props = defineProps<{
    expanded: boolean;
    settings: GeneralSettings;
}>();

const emit = defineEmits<{
    toggle: [];
    'update:settings': [settings: GeneralSettings];
}>();

const timezonePreview = computed(() => {
    try {
        return new Intl.DateTimeFormat('en', {
            dateStyle: 'full',
            timeStyle: 'medium',
            timeZone: props.settings.timezone || 'Asia/Jakarta',
        }).format(new Date());
    } catch {
        return 'Invalid timezone';
    }
});

function update<K extends keyof GeneralSettings>(key: K, value: GeneralSettings[K]) {
    emit('update:settings', { ...props.settings, [key]: value });
}
</script>

<template>
    <SettingsSection
        title="General"
        subtitle="Core labels used across the application shell and future exports."
        :expanded="expanded"
        @toggle="emit('toggle')"
    >
        <template #heading>Project Identity</template>
        <SettingsField label="Application name" v-model="settings.app_name" @update:modelValue="(val) => update('app_name', String(val ?? ''))">
            <input :value="settings.app_name" @input="update('app_name', ($event.target as HTMLInputElement).value)" class="field" placeholder="ProgressOS" />
        </SettingsField>
        <SettingsField label="Project name" v-model="settings.project_name" @update:modelValue="(val) => update('project_name', String(val ?? ''))">
            <input :value="settings.project_name" @input="update('project_name', ($event.target as HTMLInputElement).value)" class="field" placeholder="ProgressOS" />
        </SettingsField>
        <SettingsField label="Tagline" v-model="settings.tagline" @update:modelValue="(val) => update('tagline', String(val ?? ''))">
            <input :value="settings.tagline" @input="update('tagline', ($event.target as HTMLInputElement).value)" class="field" placeholder="Personal operating system" />
        </SettingsField>
        <SettingsField label="Timezone">
            <div class="space-y-2">
                <select
                    :value="settings.timezone"
                    @change="update('timezone', ($event.target as HTMLSelectElement).value)"
                    class="field"
                >
                    <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz.replace('_', ' ') }}</option>
                </select>
                <p class="rounded-xl border border-teal-100 bg-teal-50 px-3 py-2 text-sm font-bold text-teal-900">
                    {{ timezonePreview }}
                </p>
            </div>
        </SettingsField>
    </SettingsSection>
</template>