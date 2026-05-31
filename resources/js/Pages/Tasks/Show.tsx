import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { DetailText, PageHeader, ReferencesPanel } from '../../Components/UI';
import { formatDate } from '../../lib/format';

export default function Show({ task }: any) {
  return <AppLayout><Head title={task.title} /><PageHeader title={task.title} eyebrow={`${task.status} · ${task.priority}`} backHref={route('tasks.index')} action={<div className="flex gap-2"><Link className="btn btn-muted" href={route('tasks.edit', task.id)}>Edit</Link><button className="btn btn-muted" onClick={() => confirm('Delete this task?') && router.delete(route('tasks.destroy', task.id))}>Delete</button></div>} />
    <article className="card p-5">
      <div className="grid gap-3 md:grid-cols-3">
        <Meta label="Project" value={task.project?.name || 'None'} />
        <Meta label="Milestone" value={task.milestone?.title || 'None'} />
        <Meta label="Due" value={formatDate(task.due_date)} />
      </div>
      <section className="mt-6 rounded-lg border p-4"><h2 className="mb-2 font-semibold">Notes</h2><DetailText value={task.notes} /></section>
    </article>
    <div className="mt-4"><ReferencesPanel type="task" id={task.id} references={task.references || []} /></div>
  </AppLayout>;
}

function Meta({ label, value }: any) { return <p className="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900"><span className="label mb-1">{label}</span>{value}</p>; }
