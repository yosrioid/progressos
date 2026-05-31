import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { DetailText, Empty, PageHeader, ProgressBar, Stat, Tags } from '../../Components/UI';
import { compactDate, formatDate, minutes } from '../../lib/format';

export default function Show({ project, metrics, tasks, blockers, todayLogs, workLogs, weeklyTrend, categoryBreakdown, options }: any) {
  return <AppLayout><Head title={project.name} /><PageHeader title={project.name} eyebrow="Project workspace" backHref={route('projects.index')} action={<div className="flex flex-wrap gap-2"><Link className="btn btn-muted" href={route('work-logs.index', { project: project.name })}>All logs</Link><Link className="btn btn-muted" href={route('tasks.index', { search: project.name })}>Tasks</Link></div>} />
    <div className="mb-5 grid grid-cols-2 gap-3 lg:mb-6 lg:grid-cols-5"><Stat label="Open tasks" value={metrics.open_tasks} tone="sky" /><Stat label="Done tasks" value={metrics.completed_tasks} tone="teal" /><Stat label="This week" value={minutes(metrics.week_minutes)} /><Stat label="Today logs" value={metrics.today_logs} /><Stat label="Blockers" value={metrics.blockers} tone="rose" /></div>

    <section className="card mb-5 p-4 sm:p-5">
      <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h2 className="font-semibold">Today in {project.name}</h2><p className="text-sm text-slate-500">Capture work and keep the project moving without leaving this page.</p></div><ProjectSettings project={project} /></div>
      <div className="grid gap-4 xl:grid-cols-2"><QuickWorkLog project={project} categories={options.workCategories} /><QuickTask project={project} options={options} /></div>
    </section>

    <div className="grid gap-5 xl:grid-cols-3">
      <div className="space-y-5 xl:col-span-2">
        <TrendChart trend={weeklyTrend} />
        <section className="card p-5"><h2 className="mb-4 font-semibold">Today work logs</h2>{todayLogs.length === 0 ? <Empty text="No work logged for this project today." /> : <div className="grid gap-3 md:grid-cols-2">{todayLogs.map((log: any) => <WorkLogCard key={log.id} log={log} />)}</div>}</section>
        <section className="card p-5"><h2 className="mb-4 font-semibold">Recent work logs</h2>{workLogs.length === 0 ? <Empty text="No work logs for this project." /> : <div className="space-y-3">{workLogs.map((log: any) => <WorkLogCard key={log.id} log={log} compact />)}</div>}</section>
      </div>
      <div className="space-y-5">
        <section className="card p-5"><h2 className="mb-4 font-semibold">Active tasks</h2>{tasks.length === 0 ? <Empty text="No active tasks for this project." /> : <div className="space-y-2">{tasks.map((task: any) => <TaskCard key={task.id} task={task} />)}</div>}</section>
        <section className="card p-5"><h2 className="mb-4 font-semibold">Blockers</h2>{blockers.length === 0 ? <Empty text="No blockers right now." /> : <div className="space-y-2">{blockers.map((item: any) => <Link key={`${item.type}-${item.id}`} href={item.href} className="block rounded-lg border border-rose-100 bg-rose-50/60 p-3 hover:border-rose-300"><p className="font-semibold text-rose-900">{item.title}</p><p className="mt-1 text-xs font-semibold uppercase text-rose-600">{item.type.replace('_', ' ')}{item.date && ` · ${formatDate(item.date)}`}</p></Link>)}</div>}</section>
        <CategoryBreakdown rows={categoryBreakdown} />
      </div>
    </div>
  </AppLayout>;
}

function QuickWorkLog({ project, categories }: any) {
  const form = useForm({ date: new Date().toISOString().slice(0, 10), title: '', category: 'feature', actual_duration: '30', description: '' });
  const submit = (event: FormEvent) => { event.preventDefault(); form.post(route('projects.work-logs.store', project.id), { preserveScroll: true, onSuccess: () => form.reset('title', 'actual_duration', 'description') }); };
  return <form onSubmit={submit} className="rounded-xl border bg-slate-50 p-4"><h3 className="mb-3 text-sm font-bold uppercase text-slate-500">Log work</h3><div className="grid gap-3 sm:grid-cols-2"><input className="field" type="date" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} /><select className="field" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}>{categories.map((category: string) => <option key={category} value={category}>{category}</option>)}</select><input className="field sm:col-span-2" placeholder={`What did you do for ${project.name}?`} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} /><input className="field" type="number" min="1" placeholder="Minutes" value={form.data.actual_duration} onChange={(e) => form.setData('actual_duration', e.target.value)} /><button className="btn btn-primary" disabled={form.processing}>Log work</button><textarea className="field min-h-24 sm:col-span-2" placeholder="Outcome, notes, link, or decision" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></div></form>;
}

function QuickTask({ project, options }: any) {
  const form = useForm({ title: '', notes: '', status: 'todo', priority: 'medium', due_date: '' });
  const submit = (event: FormEvent) => { event.preventDefault(); form.post(route('projects.tasks.store', project.id), { preserveScroll: true, onSuccess: () => form.reset() }); };
  return <form onSubmit={submit} className="rounded-xl border bg-slate-50 p-4"><h3 className="mb-3 text-sm font-bold uppercase text-slate-500">Add task</h3><div className="grid gap-3 sm:grid-cols-2"><input className="field sm:col-span-2" placeholder={`Next task for ${project.name}`} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} /><select className="field" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>{options.taskStatuses.map((status: string) => <option key={status} value={status}>{status}</option>)}</select><select className="field" value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)}>{options.taskPriorities.map((priority: string) => <option key={priority} value={priority}>{priority}</option>)}</select><input className="field" type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} /><button className="btn btn-primary" disabled={form.processing}>Add task</button><textarea className="field min-h-24 sm:col-span-2" placeholder="Context or acceptance notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} /></div></form>;
}

function ProjectSettings({ project }: any) {
  const form = useForm({ name: project.name, archived: project.archived || false });
  const save = (event: FormEvent) => { event.preventDefault(); form.patch(route('projects.update', project.id), { preserveScroll: true }); };
  return <form onSubmit={save} className="flex flex-wrap gap-2"><input className="field max-w-52" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /><button className="btn btn-muted" disabled={form.processing}>Rename</button><button type="button" className="btn btn-muted text-rose-700" onClick={() => confirm('Archive this project?') && router.patch(route('projects.update', project.id), { name: form.data.name, archived: true })}>Archive</button></form>;
}

function TrendChart({ trend }: any) {
  const max = Math.max(1, ...trend.map((item: any) => item.minutes));
  return <section className="card p-5"><div className="mb-4 flex items-center justify-between"><h2 className="font-semibold">30 day activity</h2><span className="text-sm text-slate-500">{minutes(trend.reduce((sum: number, item: any) => sum + item.minutes, 0))}</span></div><div className="flex h-44 items-end gap-1 rounded-xl bg-slate-50 p-3">{trend.length === 0 ? <div className="grid flex-1 place-items-center text-sm text-slate-500">No recent activity.</div> : trend.map((item: any) => <div key={item.date} className="flex flex-1 flex-col items-center gap-2"><div className="w-full rounded-t-md bg-gradient-to-t from-teal-700 to-sky-400" title={`${formatDate(item.date)}: ${minutes(item.minutes)}`} style={{ height: `${Math.max(6, (item.minutes / max) * 130)}px` }} /><span className="text-[10px] font-medium text-slate-500">{compactDate(item.date)}</span></div>)}</div></section>;
}

function TaskCard({ task }: any) {
  return <Link href={route('tasks.show', task.id)} className="block rounded-lg border bg-white p-3 hover:border-teal-300 hover:bg-teal-50/40"><div className="flex items-start justify-between gap-3"><p className="font-semibold">{task.title}</p><span className="rounded bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{task.priority}</span></div><p className="mt-1 text-sm text-slate-500">{task.status}{task.due_date && ` · due ${formatDate(task.due_date)}`}</p>{task.notes && <div className="mt-2"><DetailText value={task.notes} /></div>}</Link>;
}

function WorkLogCard({ log, compact = false }: any) {
  return <Link href={route('work-logs.show', log.id)} className="block rounded-lg border bg-white p-3 hover:border-teal-300 hover:bg-teal-50/40"><div className="flex items-start justify-between gap-3"><p className="font-semibold">{log.title}</p><span className="rounded bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{minutes(log.actual_duration)}</span></div><p className="mt-1 text-sm text-slate-500">{formatDate(log.date)} · {log.category}</p>{!compact && log.description && <div className="mt-2"><DetailText value={log.description} /></div>}<div className="mt-3"><Tags tags={log.tags} /></div></Link>;
}

function CategoryBreakdown({ rows }: any) {
  const max = Math.max(1, ...rows.map((row: any) => row.minutes));
  return <section className="card p-5"><h2 className="mb-4 font-semibold">This week by category</h2>{rows.length === 0 ? <Empty text="No logged work this week." /> : <div className="space-y-4">{rows.map((row: any) => <div key={row.category}><div className="mb-2 flex justify-between text-sm"><span className="font-semibold">{row.category}</span><span className="text-slate-500">{minutes(row.minutes)} · {row.total}</span></div><ProgressBar value={(row.minutes / max) * 100} /></div>)}</div>}</section>;
}
