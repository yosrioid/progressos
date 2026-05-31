import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { DetailText, PageHeader, Tags } from '../../Components/UI';
import { formatDate } from '../../lib/format';

export default function Show({ entry }: any) {
  return <AppLayout><Head title={entry.title} /><PageHeader title={entry.title} eyebrow={formatDate(entry.date)} backHref={route('daily-progress.index')} action={<div className="flex gap-2"><Link className="btn btn-muted" href={route('daily-progress.edit', entry.id)}>Edit</Link><button className="btn btn-muted" onClick={() => router.patch(route('daily-progress.archive', entry.id))}>Archive</button></div>} />
    <article className="card p-5"><Tags tags={entry.tags} /><Grid data={entry} /><h2 className="mt-6 font-semibold">Completed</h2><ul className="mt-2 space-y-2 text-sm">{(entry.completed_items || []).map((i: string) => <li className="rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-900" key={i}>{i}</li>)}</ul></article>
  </AppLayout>;
}
function Grid({ data }: any) { return <div className="mt-5 grid gap-4 lg:grid-cols-2">{['in_progress', 'todo', 'blockers', 'notes'].map(k => <section className="rounded-lg border bg-white p-4 dark:bg-zinc-950" key={k}><h2 className="mb-2 text-sm font-semibold uppercase text-zinc-500">{k.replaceAll('_', ' ')}</h2><DetailText value={data[k]} /></section>)}</div>; }
