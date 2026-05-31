import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { DetailText, PageHeader, Tags } from '../../Components/UI';
import { formatDate, minutes } from '../../lib/format';

export default function Show({ log }: any) {
  const meta = { ...log, date: formatDate(log.date), estimated_duration: minutes(log.estimated_duration), actual_duration: minutes(log.actual_duration) };
  return <AppLayout><Head title={log.title} /><PageHeader title={log.title} eyebrow={`${log.project_name} · ${log.status}`} backHref={route('work-logs.index')} action={<Link className="btn btn-muted" href={route('work-logs.edit', log.id)}>Edit</Link>} /><article className="card p-5"><Tags tags={log.tags} /><div className="mt-5 grid gap-3 lg:grid-cols-3">{['date', 'ticket_code', 'category', 'priority', 'estimated_duration', 'actual_duration'].map(k => <p className="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900" key={k}><span className="label mb-1">{k.replaceAll('_', ' ')}</span>{meta[k] || 'None'}</p>)}</div><Text title="Description" value={log.description} /><Text title="Outcome" value={log.resolution_or_outcome} /></article></AppLayout>;
}
function Text({ title, value }: any) { return <section className="mt-6 rounded-lg border p-4"><h2 className="mb-2 font-semibold">{title}</h2><DetailText value={value} /></section>; }
