import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { ErrorSummary, FieldError, FormShell, handleSmartLinkPaste, PageHeader } from '../../Components/UI';
import { inputDate } from '../../lib/format';

export default function Form({ task, options }: any) {
  const form = useForm({ title: task?.title || '', project_id: task?.project_id || '', milestone_id: task?.milestone_id || '', notes: task?.notes || '', status: task?.status || 'todo', priority: task?.priority || 'medium', due_date: inputDate(task?.due_date) });
  const submit = (event: FormEvent) => { event.preventDefault(); task ? form.put(route('tasks.update', task.id), { preserveScroll: true }) : form.post(route('tasks.store'), { preserveScroll: true }); };

  return <AppLayout><Head title={task ? 'Edit task' : 'New task'} /><PageHeader title={task ? 'Edit task' : 'New task'} backHref={task ? route('tasks.show', task.id) : route('tasks.index')} /><ErrorSummary errors={form.errors} /><form onSubmit={submit}><FormShell>
    <Input form={form} name="title" wide />
    <Select form={form} name="project_id" options={options.projects} empty="No project" />
    <Select form={form} name="milestone_id" options={options.milestones} empty="No milestone" labelKey="title" />
    <SelectSimple form={form} name="status" options={options.statuses} />
    <SelectSimple form={form} name="priority" options={options.priorities} />
    <Input form={form} name="due_date" type="date" />
    <div className="lg:col-span-2"><label className="label">Notes</label><textarea className="field mt-1 min-h-32" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} onPaste={(e) => handleSmartLinkPaste(e, form.data.notes, (value) => form.setData('notes', value))} /><p className="mt-1 text-xs text-zinc-500">Select text, paste a URL, and it becomes a clickable reference.</p></div>
    <div className="lg:col-span-2 flex justify-end"><button className="btn btn-primary" type="submit" disabled={form.processing}>{form.processing ? 'Saving...' : 'Save task'}</button></div>
  </FormShell></form></AppLayout>;
}

function Input({ form, name, type = 'text', wide = false }: any) { return <div className={wide ? 'lg:col-span-2' : ''}><label className="label">{name.replaceAll('_', ' ')}</label><input className="field mt-1" type={type} value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} /><FieldError message={form.errors[name]} /></div>; }
function Select({ form, name, options, empty, labelKey = 'name' }: any) { return <div><label className="label">{name.replaceAll('_', ' ')}</label><select className="field mt-1" value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)}><option value="">{empty}</option>{options.map((o: any) => <option key={o.id} value={o.id}>{o[labelKey]}</option>)}</select></div>; }
function SelectSimple({ form, name, options }: any) { return <div><label className="label">{name}</label><select className="field mt-1" value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)}>{options.map((o: string) => <option key={o} value={o}>{o}</option>)}</select></div>; }
