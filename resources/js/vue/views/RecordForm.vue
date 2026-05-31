<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import { pasteLinkOverSelection } from '../linkPaste';
import { configs, normalizeForForm, serialize, type Field } from '../records';

const props = defineProps<{ type: string; id?: string }>();
const router = useRouter();
const route = useRoute();
const config = computed(() => configs[props.type]);
const form = ref<Record<string, any>>({});
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const errors = ref<Record<string, string[]>>({});
const isEdit = computed(() => Boolean(props.id));

function inputType(field: Field) {
  return field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text';
}

function handleTextareaPaste(event: ClipboardEvent, key: string) {
  pasteLinkOverSelection(event, form, key);
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    if (isEdit.value) {
      const data = await api.get(`${config.value.endpoint}/${props.id}`).then(unwrap);
      form.value = normalizeForForm(config.value, data[config.value.payloadKey]);
    } else {
      form.value = normalizeForForm(config.value);
      if (route.query.project_name && 'project_name' in form.value) form.value.project_name = String(route.query.project_name);
      if (route.query.project_id && 'project_id' in form.value) form.value.project_id = Number(route.query.project_id);
      if (route.query.category && 'category' in form.value) form.value.category = String(route.query.category);
    }
  } finally {
    loading.value = false;
  }
}

async function submit() {
  saving.value = true;
  error.value = '';
  errors.value = {};
  try {
    const payload = serialize(config.value, form.value);
    const response = isEdit.value
      ? await api.patch(`${config.value.endpoint}/${props.id}`, payload).then(unwrap)
      : await api.post(config.value.endpoint, payload).then(unwrap);
    const saved = response[config.value.payloadKey];
    await router.push(`/${props.type}/${saved.id}`);
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Could not save this record.';
    errors.value = e.response?.data?.errors || {};
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <p class="text-sm font-semibold text-teal-700">{{ config.singular }}</p>
      <h1 class="text-2xl font-semibold">{{ isEdit ? 'Edit' : 'New' }} {{ config.singular }}</h1>
    </div>
    <RouterLink class="btn btn-muted" :to="`/${type}`">Back</RouterLink>
  </div>

  <div v-if="loading" class="card p-8 text-center text-sm text-slate-500">Loading form...</div>
  <form v-else class="card p-5" @submit.prevent="submit">
    <p v-if="error" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
    <div class="grid gap-4 md:grid-cols-2">
      <label v-for="field in config.fields" :key="field.key" class="block" :class="field.span === 'full' ? 'md:col-span-2' : ''">
        <span class="label mb-1">{{ field.label }}</span>
        <textarea v-if="field.type === 'textarea' || field.type === 'tags'" v-model="form[field.key]" class="field min-h-28" :placeholder="field.type === 'tags' ? 'comma, separated, tags' : ''" @paste="handleTextareaPaste($event, field.key)" />
        <select v-else-if="field.type === 'select'" v-model="form[field.key]" class="field">
          <option v-for="option in field.options" :key="option" :value="option">{{ option.replaceAll('_', ' ') }}</option>
        </select>
        <label v-else-if="field.type === 'checkbox'" class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
          <input v-model="form[field.key]" type="checkbox" />
          <span>Yes</span>
        </label>
        <input v-else v-model="form[field.key]" class="field" :type="inputType(field)" :required="field.required" />
        <span v-if="errors[field.key]?.[0]" class="mt-1 block text-sm text-red-700">{{ errors[field.key][0] }}</span>
      </label>
    </div>
    <div class="mt-5 flex justify-end gap-2">
      <RouterLink class="btn btn-muted" :to="isEdit ? `/${type}/${id}` : `/${type}`">Cancel</RouterLink>
      <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
    </div>
  </form>
</template>
