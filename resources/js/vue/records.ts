export type Field = {
  key: string;
  label: string;
  type?: 'text' | 'date' | 'number' | 'textarea' | 'select' | 'tags' | 'checkbox';
  options?: string[];
  required?: boolean;
  span?: 'full';
};

export type RecordConfig = {
  type: string;
  singular: string;
  titleKey: string;
  payloadKey: string;
  listKey: string;
  endpoint: string;
  fields: Field[];
  defaults: Record<string, any>;
};

const today = () => new Date().toISOString().slice(0, 10);

export const configs: Record<string, RecordConfig> = {
  'daily-progress': {
    type: 'daily-progress',
    singular: 'Daily Progress',
    titleKey: 'title',
    payloadKey: 'entry',
    listKey: 'entries',
    endpoint: '/api/v1/daily-progress',
    defaults: { date: today(), title: '', mood: '', in_progress: '', todo: '', blockers: '', notes: '', completed_items: '', tags: '', archived: false },
    fields: [
      { key: 'date', label: 'Date', type: 'date', required: true },
      { key: 'mood', label: 'Mood / Status' },
      { key: 'title', label: 'Title', required: true, span: 'full' },
      { key: 'completed_items', label: 'Completed items', type: 'textarea', span: 'full' },
      { key: 'in_progress', label: 'In progress', type: 'textarea', span: 'full' },
      { key: 'todo', label: 'Todo', type: 'textarea', span: 'full' },
      { key: 'blockers', label: 'Blockers', type: 'textarea', span: 'full' },
      { key: 'notes', label: 'Notes', type: 'textarea', span: 'full' },
      { key: 'tags', label: 'Tags', type: 'tags', span: 'full' },
      { key: 'archived', label: 'Archived', type: 'checkbox' },
    ],
  },
  'work-logs': {
    type: 'work-logs',
    singular: 'Work Log',
    titleKey: 'title',
    payloadKey: 'log',
    listKey: 'logs',
    endpoint: '/api/v1/work-logs',
    defaults: { date: today(), project_name: '', ticket_code: '', title: '', category: 'feature', status: 'done', priority: 'medium', estimated_duration: '', actual_duration: '', description: '', resolution_or_outcome: '', tags: '' },
    fields: [
      { key: 'date', label: 'Date', type: 'date', required: true },
      { key: 'project_name', label: 'Project', required: true },
      { key: 'ticket_code', label: 'Ticket' },
      { key: 'title', label: 'Title', required: true, span: 'full' },
      { key: 'category', label: 'Category', type: 'select', options: ['bug', 'feature', 'research', 'testing', 'setup', 'meeting', 'documentation', 'refactor', 'other'] },
      { key: 'status', label: 'Status', type: 'select', options: ['todo', 'in_progress', 'done', 'blocked'] },
      { key: 'priority', label: 'Priority', type: 'select', options: ['low', 'medium', 'high', 'urgent'] },
      { key: 'estimated_duration', label: 'Estimated minutes', type: 'number' },
      { key: 'actual_duration', label: 'Actual minutes', type: 'number' },
      { key: 'description', label: 'Description', type: 'textarea', span: 'full' },
      { key: 'resolution_or_outcome', label: 'Outcome', type: 'textarea', span: 'full' },
      { key: 'tags', label: 'Tags', type: 'tags', span: 'full' },
    ],
  },
  tasks: {
    type: 'tasks',
    singular: 'Task',
    titleKey: 'title',
    payloadKey: 'task',
    listKey: 'tasks',
    endpoint: '/api/v1/tasks',
    defaults: { title: '', project_id: '', status: 'todo', priority: 'medium', due_date: today(), notes: '' },
    fields: [
      { key: 'title', label: 'Title', required: true, span: 'full' },
      { key: 'project_id', label: 'Project ID', type: 'number' },
      { key: 'due_date', label: 'Due date', type: 'date' },
      { key: 'status', label: 'Status', type: 'select', options: ['todo', 'in_progress', 'done', 'blocked'] },
      { key: 'priority', label: 'Priority', type: 'select', options: ['low', 'medium', 'high', 'urgent'] },
      { key: 'notes', label: 'Notes', type: 'textarea', span: 'full' },
    ],
  },
  learning: {
    type: 'learning',
    singular: 'Learning Entry',
    titleKey: 'topic',
    payloadKey: 'entry',
    listKey: 'entries',
    endpoint: '/api/v1/learning',
    defaults: { date: today(), topic: '', category: 'programming', source_type: 'practice', duration_minutes: 30, rating: '', progress_notes: '', takeaway: '', next_action: '' },
    fields: [
      { key: 'date', label: 'Date', type: 'date', required: true },
      { key: 'topic', label: 'Topic', required: true },
      { key: 'category', label: 'Category', type: 'select', options: ['programming', 'english', 'japanese', 'german', 'books', 'career', 'other'] },
      { key: 'source_type', label: 'Source', type: 'select', options: ['book', 'article', 'video', 'course', 'practice', 'podcast', 'other'] },
      { key: 'duration_minutes', label: 'Minutes', type: 'number', required: true },
      { key: 'rating', label: 'Rating', type: 'number' },
      { key: 'progress_notes', label: 'Progress notes', type: 'textarea', span: 'full' },
      { key: 'takeaway', label: 'Takeaway', type: 'textarea', span: 'full' },
      { key: 'next_action', label: 'Next action', type: 'textarea', span: 'full' },
    ],
  },
  milestones: {
    type: 'milestones',
    singular: 'Milestone',
    titleKey: 'title',
    payloadKey: 'milestone',
    listKey: 'milestones',
    endpoint: '/api/v1/milestones',
    defaults: { title: '', category: '', target_type: 'count', source_type: 'manual', target_value: 1, current_value: 0, start_date: today(), end_date: '', status: 'active', notes: '' },
    fields: [
      { key: 'title', label: 'Title', required: true, span: 'full' },
      { key: 'category', label: 'Category', required: true },
      { key: 'target_type', label: 'Target type', type: 'select', options: ['count', 'duration', 'streak', 'custom'] },
      { key: 'source_type', label: 'Source type', type: 'select', options: ['manual', 'work_log_count', 'work_log_minutes', 'learning_minutes', 'daily_progress_streak', 'task_done_count'] },
      { key: 'target_value', label: 'Target', type: 'number', required: true },
      { key: 'current_value', label: 'Current', type: 'number' },
      { key: 'start_date', label: 'Start', type: 'date' },
      { key: 'end_date', label: 'End', type: 'date' },
      { key: 'status', label: 'Status', type: 'select', options: ['active', 'paused', 'completed', 'cancelled'] },
      { key: 'notes', label: 'Notes', type: 'textarea', span: 'full' },
    ],
  },
};

export function normalizeForForm(config: RecordConfig, record?: any) {
  const form = { ...config.defaults, ...(record || {}) };
  if (Array.isArray(form.completed_items)) form.completed_items = form.completed_items.join('\n');
  if (Array.isArray(form.tags)) form.tags = form.tags.map((tag: any) => tag.name || tag).join(', ');
  for (const key of ['date', 'due_date', 'start_date', 'end_date']) {
    if (form[key]) form[key] = String(form[key]).slice(0, 10);
  }
  return form;
}

export function serialize(config: RecordConfig, form: Record<string, any>) {
  const payload: Record<string, any> = { ...form };
  if ('completed_items' in payload) payload.completed_items = String(payload.completed_items || '').split('\n').map((item) => item.trim()).filter(Boolean);
  if ('tags' in payload) payload.tags = String(payload.tags || '').split(',').map((item) => item.trim()).filter(Boolean);
  for (const key of ['project_id', 'actual_duration', 'estimated_duration', 'duration_minutes', 'rating']) {
    if (payload[key] === '') payload[key] = null;
    else if (payload[key] !== undefined && payload[key] !== null) payload[key] = Number(payload[key]);
  }
  if (config.type === 'milestones') {
    payload.target_value = Number(payload.target_value || 0);
    payload.current_value = Number(payload.current_value || 0);
  }
  return payload;
}
