import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Empty, PageHeader, Tags } from '../../Components/UI';
import { formatDate, minutes } from '../../lib/format';

export default function Daily({ date, progress, tasks, workLogs, learning }: any) {
  const form = useForm({ period_type: 'daily', period_start: date, period_end: date, summary: '', answers: { moved: '', blocked: '', carry: '', stop: '' } as any });
  const save = (event: any) => { event.preventDefault(); form.post(route('reviews.save'), { preserveScroll: true }); };
  const tomorrow = new Date(`${date}T00:00:00`); tomorrow.setDate(tomorrow.getDate() + 1);
  const tomorrowText = tomorrow.toISOString().slice(0, 10);
  return <AppLayout><Head title="Daily Review" /><PageHeader title="Daily Review" eyebrow={formatDate(date)} action={<Link className="btn btn-primary" href={route('daily-progress.create')}>Write entry</Link>} />
    <div className="grid gap-4 xl:grid-cols-2">
      <Panel title="Tasks">{tasks.length ? tasks.map((task: any) => <div className="rounded-lg border p-3" key={task.id}><Link className="font-semibold hover:text-teal-700" href={route('tasks.show', task.id)}>{task.title}</Link><p className="text-sm text-zinc-500">{task.status} · {task.project?.name || 'No project'}</p><div className="mt-2 flex flex-wrap gap-2"><button className="btn btn-muted px-2 py-1 text-xs" onClick={() => router.patch(route('reviews.tasks.carry', task.id), { due_date: tomorrowText }, { preserveScroll: true })}>Carry to tomorrow</button><button className="btn btn-muted px-2 py-1 text-xs" onClick={() => router.post(route('reviews.tasks.work-log', task.id), {}, { preserveScroll: true })}>Convert to work log</button></div></div>) : <Empty text="No tasks to review." />}</Panel>
      <Panel title="Progress">{progress.length ? progress.map((entry: any) => <Link className="block rounded-lg border p-3 hover:bg-teal-50/40" href={route('daily-progress.show', entry.id)} key={entry.id}><b>{entry.title}</b><Tags tags={entry.tags} /></Link>) : <Empty text="No daily progress yet." />}</Panel>
      <Panel title="Work logs">{workLogs.length ? workLogs.map((log: any) => <Link className="block rounded-lg border p-3 hover:bg-teal-50/40" href={route('work-logs.show', log.id)} key={log.id}><b>{log.title}</b><p className="text-sm text-zinc-500">{log.project_name} · {minutes(log.actual_duration)}</p></Link>) : <Empty text="No work logged." />}</Panel>
      <Panel title="Learning">{learning.length ? learning.map((entry: any) => <Link className="block rounded-lg border p-3 hover:bg-teal-50/40" href={route('learning.show', entry.id)} key={entry.id}><b>{entry.topic}</b><p className="text-sm text-zinc-500">{entry.category} · {minutes(entry.duration_minutes)}</p></Link>) : <Empty text="No learning logged." />}</Panel>
    </div>
    <form onSubmit={save} className="card mt-4 grid gap-4 p-5 md:grid-cols-2">
      {Object.keys(form.data.answers).map((key) => <label key={key}><span className="label mb-1">{key}</span><textarea className="field min-h-24" value={form.data.answers[key]} onChange={(e) => form.setData('answers', { ...form.data.answers, [key]: e.target.value })} /></label>)}
      <label className="md:col-span-2"><span className="label mb-1">Summary</span><textarea className="field min-h-24" value={form.data.summary} onChange={(e) => form.setData('summary', e.target.value)} /></label>
      <div className="md:col-span-2 flex justify-end"><button className="btn btn-primary">Save review</button></div>
    </form>
  </AppLayout>;
}

function Panel({ title, children }: any) { return <section className="card space-y-3 p-5"><h2 className="font-semibold">{title}</h2>{children}</section>; }
