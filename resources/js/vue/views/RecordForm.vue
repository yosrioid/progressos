<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { api, unwrap } from '../api';
import DatePicker from '../components/DatePicker.vue';
import DailyProgressLists from '../components/DailyProgressLists.vue';
import { toast } from '../feedback';
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
const taskOptions = ref<{ id: number; title: string; status: string }[]>([]);
const projectNames = ref<string[]>([]);
const projectList = ref<{ id: number; name: string }[]>([]);
const isEdit = computed(() => Boolean(props.id));
const formSections = computed(() => {
  const fields = config.value.fields;
  const primary = fields.filter((field) => !['textarea', 'tags', 'checkbox'].includes(field.type || ''));
  const notes = fields.filter((field) => field.type === 'textarea' || field.type === 'tags');
  const settings = fields.filter((field) => field.type === 'checkbox');
  return [
    { title: 'Core details', description: 'The required structure that makes this record searchable and reportable.', fields: primary },
    { title: 'Notes and context', description: 'Write outcomes, blockers, references, and next actions. Select text then paste a URL to turn it into a link.', fields: notes },
    { title: 'Settings', description: 'Optional record state.', fields: settings },
  ].filter((section) => section.fields.length);
});

function inputType(field: Field) {
  return field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text';
}

function handleTextareaPaste(event: ClipboardEvent, key: string) {
  pasteLinkOverSelection(event, form, key);
}

async function loadProjectNames() {
  try {
    const res = await api.get('/api/v1/projects?per_page=200&sort=name&direction=asc').then(unwrap);
    projectList.value = (res.data ?? []).map((p: any) => ({ id: p.id, name: p.name })).filter((p: any) => p.name);
    projectNames.value = projectList.value.map((p) => p.name);
  } catch {
    // non-critical
  }
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
    isDirty.value = false;
    toast({
      tone: 'success',
      title: isEdit.value ? `${config.value.singular} updated` : `${config.value.singular} created`,
      message: saved[config.value.titleKey] || 'Changes saved.',
    });
    await router.push(`/${props.type}/${saved.id}`);
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Could not save this record.';
    errors.value = e.response?.data?.errors || {};
  } finally {
    saving.value = false;
  }
}

const isDirty = ref(false);
watch(form, () => { if (!loading.value) isDirty.value = true; }, { deep: true });

onBeforeRouteLeave(() => {
  if (isDirty.value && !saving.value) {
    return window.confirm('Ada perubahan yang belum disimpan. Yakin mau pergi?');
  }
});

onMounted(async () => {
  await load();
  const needsProjectList = config.value.fields.some((f) => f.type === 'project-autocomplete' || f.type === 'project-select');
  if (needsProjectList) loadProjectNames();
  if (props.type === 'work-logs') {
    const data = await api.get('/api/v1/tasks', { params: { status: 'todo,in_progress', per_page: 100, sort: 'created_at', direction: 'desc' } }).then(unwrap);
    taskOptions.value = (data.tasks?.data || []).map((t: any) => ({ id: t.id, title: t.title, status: t.status }));
  }
});
</script>

<template>
  <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <p class="text-sm font-extrabold text-teal-700">{{ config.singular }}</p>
      <h1 class="mt-1 text-3xl font-extrabold tracking-tight">{{ isEdit ? 'Edit' : 'New' }} {{ config.singular }}</h1>
      <p class="mt-1 text-sm font-medium text-slate-500">Capture clean data now so dashboard, reports, and search stay useful later.</p>
    </div>
    <RouterLink class="btn btn-muted" :to="`/${type}`">Back</RouterLink>
  </div>

  <div v-if="loading" class="grid gap-4">
    <div class="skeleton h-28 rounded-2xl"></div>
    <div class="skeleton h-72 rounded-2xl"></div>
  </div>
  <form v-else class="grid gap-6 pb-24" @submit.prevent="submit">
    <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">{{ error }}</p>
    <section v-if="type === 'daily-progress'" class="card overflow-hidden p-0">
      <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
        <h2 class="font-extrabold text-slate-900">Progress items</h2>
        <p class="mt-1 text-sm font-medium text-slate-500">Tambah item per section. Enter untuk baris baru, drag untuk pindahkan antar section.</p>
      </div>
      <div class="p-5">
        <DailyProgressLists
          :model-value="{ completed_items: form.completed_items, in_progress: form.in_progress, todo: form.todo, blockers: form.blockers }"
          @update:model-value="(v) => Object.assign(form, v)"
        />
      </div>
    </section>
    <section v-for="section in formSections" :key="section.title" class="card overflow-hidden p-0">
      <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
        <h2 class="font-extrabold text-slate-900">{{ section.title }}</h2>
        <p class="mt-1 text-sm font-medium text-slate-500">{{ section.description }}</p>
      </div>
      <div class="grid gap-4 p-5 md:grid-cols-2">
        <label v-for="field in section.fields" :key="field.key" class="block" :class="field.span === 'full' || field.type === 'textarea' || field.type === 'tags' ? 'md:col-span-2' : ''">
          <span class="label mb-1">{{ field.label }}<span v-if="field.required" class="ml-0.5 text-red-500">*</span></span>
          <textarea v-if="field.type === 'textarea' || field.type === 'tags'" v-model="form[field.key]" class="field min-h-32" :placeholder="field.type === 'tags' ? 'comma, separated, tags' : 'Type notes, or select text and paste a URL'" @paste="handleTextareaPaste($event, field.key)" />
          <select v-else-if="field.type === 'select'" v-model="form[field.key]" class="field">
            <option v-for="option in field.options" :key="option" :value="option">{{ option.replaceAll('_', ' ') }}</option>
          </select>
          <label v-else-if="field.type === 'checkbox'" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold">
            <span>Enable {{ field.label.toLowerCase() }}</span>
            <input v-model="form[field.key]" type="checkbox" />
          </label>
          <DatePicker v-else-if="field.type === 'date'" v-model="form[field.key]" :label="field.label" :required="field.required" />
          <div v-else-if="field.type === 'project-autocomplete'">
            <input v-model="form[field.key]" :list="`projects-list-${field.key}`" class="field" :required="field.required" autocomplete="off" placeholder="Select or type project name" />
            <datalist :id="`projects-list-${field.key}`">
              <option v-for="name in projectNames" :key="name" :value="name" />
            </datalist>
          </div>
          <select v-else-if="field.type === 'project-select'" v-model.number="form[field.key]" class="field" :required="field.required">
            <option :value="null">— No project —</option>
            <option v-for="p in projectList" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <div v-else-if="field.type === 'task-link'" class="relative">
            <select v-model="form[field.key]" class="field">
              <option value="">— no linked task —</option>
              <option v-for="t in taskOptions" :key="t.id" :value="t.id">{{ t.title }} ({{ t.status.replaceAll('_', ' ') }})</option>
            </select>
          </div>
          <input v-else v-model="form[field.key]" class="field" :type="inputType(field)" :required="field.required" />
          <span v-if="errors[field.key]?.[0]" class="mt-1 block text-xs font-semibold text-red-600 dark:text-red-400">{{ errors[field.key][0] }}</span>
        </label>
      </div>
    </section>
    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/90 px-4 py-3 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/90 lg:left-72">
      <div class="mx-auto flex max-w-7xl justify-end gap-2">
        <RouterLink class="btn btn-muted" :to="isEdit ? `/${type}/${id}` : `/${type}`">Cancel</RouterLink>
        <button class="btn btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
      </div>
    </div>
  </form>
</template>
