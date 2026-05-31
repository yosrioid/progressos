import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { ErrorSummary, FieldError, FormShell, handleSmartLinkPaste, PageHeader } from '../../Components/UI';
import { inputDate } from '../../lib/format';

export default function Form({ entry }: any) {
  const form = useForm({ date: inputDate(entry?.date) || new Date().toISOString().slice(0, 10), title: entry?.title || '', in_progress: entry?.in_progress || '', todo: entry?.todo || '', blockers: entry?.blockers || '', notes: entry?.notes || '', mood: entry?.mood || '', completed_items: (entry?.completed_items || []).join('\n'), tags: (entry?.tags || []).map((t: any) => t.name).join(', ') });
  const submit = (e: FormEvent) => {
    e.preventDefault();
    const payload: any = {
      ...form.data,
      completed_items: form.data.completed_items.split('\n').map((item) => item.trim()).filter(Boolean),
      tags: form.data.tags.split(',').map((tag) => tag.trim()).filter(Boolean),
    };

    form.clearErrors();
    form.transform(() => payload);
    entry ? form.put(route('daily-progress.update', entry.id), { preserveScroll: true }) : form.post(route('daily-progress.store'), { preserveScroll: true });
  };
  return <AppLayout><Head title={entry ? 'Edit progress' : 'New progress'} /><PageHeader title={entry ? 'Edit daily progress' : 'New daily progress'} backHref={entry ? route('daily-progress.show', entry.id) : route('daily-progress.index')} /><ErrorSummary errors={form.errors} /><form onSubmit={submit}><FormShell><Input form={form} name="date" type="date" /><Input form={form} name="title" /><Input form={form} name="mood" /><Input form={form} name="tags" /><Area form={form} name="in_progress" /><Area form={form} name="todo" /><Area form={form} name="blockers" /><Area form={form} name="completed_items" label="Completed items, one per line" smartLinks={false} /><Area form={form} name="notes" wide /><div className="lg:col-span-2 flex justify-end"><button className="btn btn-primary" type="submit" disabled={form.processing}>{form.processing ? 'Saving...' : 'Save entry'}</button></div></FormShell></form></AppLayout>;
}
function Input({ form, name, type = 'text' }: any) { return <div><label className="label">{name.replaceAll('_', ' ')}</label><input className="field mt-1" type={type} value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} /><FieldError message={form.errors[name]} /></div>; }
function Area({ form, name, label, wide = false, smartLinks = true }: any) {
  return <div className={wide ? 'lg:col-span-2' : ''}><label className="label">{label || name.replaceAll('_', ' ')}</label><textarea className="field mt-1 min-h-32" value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} onPaste={(e) => smartLinks && handleSmartLinkPaste(e, form.data[name], (value) => form.setData(name, value))} /><FieldError message={form.errors[name]} />{smartLinks && <p className="mt-1 text-xs text-zinc-500">Select text, paste a URL, and it becomes a clickable reference.</p>}</div>;
}
