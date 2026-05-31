import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { PageHeader, Stat } from '../../Components/UI';
import { formatDate } from '../../lib/format';

export default function Period({ report, review }: any) {
  const form = useForm({ period_type: report.period, period_start: report.start, period_end: report.end, summary: review?.summary || '', answers: review?.answers || { keep: '', change: '', next: '' } });
  const save = (event: any) => { event.preventDefault(); form.post(route('reviews.save'), { preserveScroll: true }); };
  return <AppLayout><Head title={`${report.period} review`} /><PageHeader title={`${report.period} Review`} eyebrow={`${formatDate(report.start)} to ${formatDate(report.end)}`} action={<Link className="btn btn-primary" href={route('reports.show', report.period)}>Open report</Link>} />
    <div className="grid gap-3 md:grid-cols-3"><Stat label="Completed work" value={report.completed_work_logs.length} tone="teal" /><Stat label="Open blockers" value={report.open_blockers.length} tone="rose" /><Stat label="Learning hours" value={report.learning_totals.hours} tone="sky" /></div>
    <form onSubmit={save} className="mt-6 grid gap-4 xl:grid-cols-3"><ReviewPrompt title="Keep" field="keep" form={form} /><ReviewPrompt title="Change" field="change" form={form} /><ReviewPrompt title="Next" field="next" form={form} /><label className="card p-5 xl:col-span-3"><span className="label mb-1">Summary</span><textarea className="field min-h-24" value={form.data.summary} onChange={(e) => form.setData('summary', e.target.value)} /></label><div className="xl:col-span-3 flex justify-end"><button className="btn btn-primary">Save review</button></div></form>
  </AppLayout>;
}

function ReviewPrompt({ title, field, form }: any) { return <section className="card p-5"><h2 className="mb-4 font-semibold">{title}</h2><textarea className="field min-h-40" value={form.data.answers[field] || ''} onChange={(e) => form.setData('answers', { ...form.data.answers, [field]: e.target.value })} /></section>; }
