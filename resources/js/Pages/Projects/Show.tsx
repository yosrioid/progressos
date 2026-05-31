import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Empty, PageHeader, Stat, Tags } from '../../Components/UI';
import { formatDate, minutes } from '../../lib/format';

export default function Show({ project, metrics, tasks, workLogs }: any) {
  return <AppLayout><Head title={project.name} /><PageHeader title={project.name} backHref={route('projects.index')} />
    <div className="mb-5 grid grid-cols-2 gap-3 lg:mb-6 lg:grid-cols-4"><Stat label="Open tasks" value={metrics.open_tasks} tone="sky" /><Stat label="Done tasks" value={metrics.completed_tasks} tone="teal" /><Stat label="Logged time" value={minutes(metrics.logged_minutes)} /><Stat label="Blockers" value={metrics.blockers} tone="rose" /></div>
    <div className="grid gap-4 xl:grid-cols-2">
      <section className="card p-5"><h2 className="mb-4 font-semibold">Tasks</h2><div className="space-y-2">{tasks.map((task: any) => <Link key={task.id} href={route('tasks.show', task.id)} className="block rounded-lg border p-3 hover:bg-teal-50/40"><b>{task.title}</b><p className="text-sm text-zinc-500">{task.status}{task.due_date && ` · ${formatDate(task.due_date)}`}</p></Link>)}{tasks.length === 0 && <Empty text="No tasks for this project." />}</div></section>
      <section className="card p-5"><h2 className="mb-4 font-semibold">Work logs</h2><div className="space-y-2">{workLogs.map((log: any) => <Link key={log.id} href={route('work-logs.show', log.id)} className="block rounded-lg border p-3 hover:bg-teal-50/40"><b>{log.title}</b><p className="text-sm text-zinc-500">{formatDate(log.date)} · {minutes(log.actual_duration)}</p><Tags tags={log.tags} /></Link>)}{workLogs.length === 0 && <Empty text="No work logs for this project." />}</div></section>
    </div>
  </AppLayout>;
}
