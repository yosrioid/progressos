import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { PageHeader, Stat } from '../../Components/UI';
import { formatDate } from '../../lib/format';

export default function Report({ report }: any) {
  return <AppLayout><Head title={`${report.period} report`} /><PageHeader title={`${report.period} report`} eyebrow={`${formatDate(report.start)} to ${formatDate(report.end)}`} action={<Link className="btn btn-primary" href={route('reports.export', report.period)}>Export CSV</Link>} />
    <div className="grid gap-3 sm:grid-cols-3"><Stat label="Completed work" value={report.completed_work_logs.length} tone="teal" /><Stat label="Open blockers" value={report.open_blockers.length} tone="rose" /><Stat label="Learning hours" value={report.learning_totals.hours} tone="sky" /></div>
    <div className="mt-6 grid gap-4 lg:grid-cols-2"><Panel title="Time by category" data={report.time_by_category} suffix=" min" /><Panel title="Learning by category" data={report.learning_totals.by_category} suffix=" min" /><Panel title="Most active projects" data={report.most_active_projects} /><Panel title="Notable tags" data={report.notable_tags} /><List title="Key achievements" items={report.key_achievements} /><Panel title="Trends vs previous period" data={report.trends} /></div>
  </AppLayout>;
}
function Panel({ title, data, suffix = '' }: any) { return <section className="card p-5"><h2 className="mb-4 font-semibold">{title}</h2><div className="space-y-2">{Object.entries(data).map(([k, v]: any) => <div className="flex justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-900" key={k}><span>{k}</span><b>{v}{suffix}</b></div>)}</div></section>; }
function List({ title, items }: any) { return <section className="card p-5"><h2 className="mb-4 font-semibold">{title}</h2><ul className="space-y-2 text-sm">{items.map((i: string, idx: number) => <li className="rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-900" key={idx}>{i}</li>)}</ul></section>; }
