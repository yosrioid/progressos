import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { DetailText, Empty, PageHeader, Pagination, Stat } from '../../Components/UI';
import { formatDate } from '../../lib/format';

export default function Index({ tasks, filters, summary, savedViews }: any) {
  return <AppLayout><Head title="Tasks" /><PageHeader title="Tasks" action={<Link className="btn btn-primary" href={route('tasks.create')}>New task</Link>} />
    <div className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">{['todo', 'in_progress', 'done', 'blocked'].map((s, i) => <Stat key={s} label={s.replace('_', ' ')} value={summary[s] || 0} tone={(['neutral', 'sky', 'teal', 'rose'] as any)[i]} />)}</div>
    <div className="mb-4 flex flex-wrap gap-2">{savedViews.map((view: any) => <Link key={view.id} href={route('tasks.index', view.filters)} className="btn btn-muted px-3 py-1">{view.name}</Link>)}<button className="btn btn-muted px-3 py-1" onClick={() => { const name = prompt('Save this task view as'); if (name) router.post(route('saved-views.store'), { module: 'tasks', name, filters, pinned: true }, { preserveScroll: true }); }}>Save view</button></div>
    <form className="card mb-4 grid gap-3 p-4 md:grid-cols-4" onSubmit={(e) => { e.preventDefault(); router.get(route('tasks.index'), Object.fromEntries(new FormData(e.currentTarget))); }}>
      <input className="field" name="search" placeholder="Search" defaultValue={filters.search || ''} />
      <select className="field" name="status" defaultValue={filters.status || ''}><option value="">Any status</option><option value="todo">todo</option><option value="in_progress">in progress</option><option value="done">done</option><option value="blocked">blocked</option></select>
      <select className="field" name="priority" defaultValue={filters.priority || ''}><option value="">Any priority</option><option value="low">low</option><option value="medium">medium</option><option value="high">high</option><option value="urgent">urgent</option></select>
      <button className="btn btn-muted">Filter</button>
    </form>
    {tasks.data.length === 0 ? <Empty text="No tasks match this view." /> : <div className="grid gap-3">{tasks.data.map((task: any) => <article className="card p-4" key={task.id}>
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div className="min-w-0"><Link href={route('tasks.show', task.id)} className="text-base font-semibold hover:text-teal-700">{task.title}</Link><p className="mt-1 text-sm text-zinc-500">{task.project?.name || 'No project'}{task.due_date && ` · due ${formatDate(task.due_date)}`}</p></div>
        <div className="flex flex-wrap gap-2">
          {['todo', 'in_progress', 'done', 'blocked'].map((status) => <button key={status} onClick={() => router.patch(route('tasks.status', task.id), { status }, { preserveScroll: true })} className={`rounded-md px-2 py-1 text-xs font-bold ${task.status === status ? 'bg-teal-700 text-white' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800'}`}>{status}</button>)}
        </div>
      </div>
      {task.notes && <div className="mt-3"><DetailText value={task.notes} /></div>}
    </article>)}</div>}
    <Pagination links={tasks.links} />
  </AppLayout>;
}
