import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { DetailText, Empty, PageHeader, Stat, Tags } from '../../Components/UI';
import { formatDate, minutes } from '../../lib/format';

export default function Daily({ date, tomorrow, review, progress, tasks, completedTasks, blockers, workLogs, learning, projects }: any) {
  const form = useForm({ period_type: 'daily', period_start: date, period_end: date, summary: review?.summary || '', answers: review?.answers || { shipped: '', learned: '', blockers: '', carry: '', tomorrow: '', stop: '' } as any });
  const save = (event: FormEvent) => { event.preventDefault(); form.post(route('reviews.save'), { preserveScroll: true }); };
  return <AppLayout><Head title="Daily Review" /><PageHeader title="Daily Review" eyebrow={formatDate(date)} action={<Link className="btn btn-primary" href={route('daily-progress.create')}>Write entry</Link>} />
    <div className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5"><Stat label="Work logs" value={workLogs.length} tone="teal" /><Stat label="Done tasks" value={completedTasks.length} tone="sky" /><Stat label="Learning" value={minutes(learning.reduce((sum: number, entry: any) => sum + Number(entry.duration_minutes || 0), 0))} /><Stat label="Carry candidates" value={tasks.filter((task: any) => task.status !== 'done').length} /><Stat label="Blockers" value={blockers.length} tone="rose" /></div>

    <section className="card mb-5 p-4 sm:p-5">
      <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h2 className="font-semibold">Close today, prepare tomorrow</h2><p className="text-sm text-slate-500">Carry unfinished work, turn tasks into logs, and capture tomorrow's first move.</p></div><span className="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-600">Next: {formatDate(tomorrow)}</span></div>
      <PlanTask tomorrow={tomorrow} projects={projects} />
    </section>

    <div className="grid gap-4 xl:grid-cols-3">
      <Panel title="Carry forward">{tasks.length ? tasks.map((task: any) => <TaskReviewCard key={task.id} task={task} tomorrow={tomorrow} />) : <Empty text="No tasks to review." />}</Panel>
      <Panel title="Blockers">{blockers.length ? blockers.map((item: any) => <Link key={`${item.type}-${item.id}`} className="block rounded-lg border border-rose-100 bg-rose-50/60 p-3 hover:border-rose-300" href={item.href}><b className="text-rose-900">{item.title}</b><p className="text-sm text-rose-700">{item.type.replace('_', ' ')} · {item.project || 'No project'}{item.date && ` · ${formatDate(item.date)}`}</p></Link>) : <Empty text="No blockers right now." />}</Panel>
      <Panel title="Completed tasks">{completedTasks.length ? completedTasks.map((task: any) => <Link className="block rounded-lg border p-3 hover:bg-teal-50/40" href={route('tasks.show', task.id)} key={task.id}><b>{task.title}</b><p className="text-sm text-zinc-500">{task.project?.name || 'No project'}</p></Link>) : <Empty text="No completed tasks today." />}</Panel>
      <Panel title="Progress">{progress.length ? progress.map((entry: any) => <Link className="block rounded-lg border p-3 hover:bg-teal-50/40" href={route('daily-progress.show', entry.id)} key={entry.id}><b>{entry.title}</b><Tags tags={entry.tags} /></Link>) : <Empty text="No daily progress yet." />}</Panel>
      <Panel title="Work logs">{workLogs.length ? workLogs.map((log: any) => <Link className="block rounded-lg border p-3 hover:bg-teal-50/40" href={route('work-logs.show', log.id)} key={log.id}><b>{log.title}</b><p className="text-sm text-zinc-500">{log.project_name} · {minutes(log.actual_duration)}</p></Link>) : <Empty text="No work logged." />}</Panel>
      <Panel title="Learning">{learning.length ? learning.map((entry: any) => <Link className="block rounded-lg border p-3 hover:bg-teal-50/40" href={route('learning.show', entry.id)} key={entry.id}><b>{entry.topic}</b><p className="text-sm text-zinc-500">{entry.category} · {minutes(entry.duration_minutes)}</p></Link>) : <Empty text="No learning logged." />}</Panel>
    </div>

    <form onSubmit={save} className="card mt-5 grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
      {['shipped', 'learned', 'blockers', 'carry', 'tomorrow', 'stop'].map((key) => <label key={key}><span className="label mb-1">{labelFor(key)}</span><textarea className="field min-h-28" value={form.data.answers[key] || ''} onChange={(e) => form.setData('answers', { ...form.data.answers, [key]: e.target.value })} /></label>)}
      <label className="md:col-span-2"><span className="label mb-1">Summary</span><textarea className="field min-h-24" value={form.data.summary} onChange={(e) => form.setData('summary', e.target.value)} /></label>
      <div className="md:col-span-2 xl:col-span-3 flex justify-end"><button className="btn btn-primary">Save review</button></div>
    </form>
  </AppLayout>;
}

function Panel({ title, children }: any) { return <section className="card space-y-3 p-5"><h2 className="font-semibold">{title}</h2>{children}</section>; }

function TaskReviewCard({ task, tomorrow }: any) {
  return <div className="rounded-lg border p-3"><Link className="font-semibold hover:text-teal-700" href={route('tasks.show', task.id)}>{task.title}</Link><p className="text-sm text-zinc-500">{task.status} · {task.project?.name || 'No project'}{task.due_date && ` · due ${formatDate(task.due_date)}`}</p>{task.notes && <div className="mt-2"><DetailText value={task.notes} /></div>}<div className="mt-3 flex flex-wrap gap-2"><button className="btn btn-muted px-2 py-1 text-xs" onClick={() => router.patch(route('reviews.tasks.carry', task.id), { due_date: tomorrow }, { preserveScroll: true })}>Carry to tomorrow</button><button className="btn btn-muted px-2 py-1 text-xs" onClick={() => router.post(route('reviews.tasks.work-log', task.id), {}, { preserveScroll: true })}>Convert to work log</button></div></div>;
}

function PlanTask({ tomorrow, projects }: any) {
  const form = useForm({ title: '', project_id: '', priority: 'medium', due_date: tomorrow, notes: '' });
  const submit = (event: FormEvent) => { event.preventDefault(); form.post(route('reviews.plan-task'), { preserveScroll: true, onSuccess: () => form.reset('title', 'project_id', 'priority', 'notes') }); };
  return <form onSubmit={submit} className="grid gap-3 md:grid-cols-5"><input className="field md:col-span-2" placeholder="Tomorrow's first task" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} /><select className="field" value={form.data.project_id} onChange={(e) => form.setData('project_id', e.target.value)}><option value="">No project</option>{projects.map((project: any) => <option key={project.id} value={project.id}>{project.name}</option>)}</select><select className="field" value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)}><option value="low">low</option><option value="medium">medium</option><option value="high">high</option><option value="urgent">urgent</option></select><button className="btn btn-primary" disabled={form.processing}>Plan task</button><textarea className="field min-h-20 md:col-span-5" placeholder="Context for tomorrow" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} /></form>;
}

function labelFor(key: string) {
  return ({ shipped: 'What shipped?', learned: 'What did you learn?', blockers: 'What is blocked?', carry: 'What carries forward?', tomorrow: 'Tomorrow plan', stop: 'What should stop?' } as any)[key] || key;
}
