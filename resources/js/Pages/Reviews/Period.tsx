import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Empty, PageHeader, ProgressBar, Stat } from '../../Components/UI';
import { formatDate, minutes } from '../../lib/format';

export default function Period({ report, review }: any) {
  const form = useForm({ period_type: report.period, period_start: report.start, period_end: report.end, summary: review?.summary || '', answers: review?.answers || { wins: '', patterns: '', blockers: '', decisions: '', next: '', stop: '' } });
  const save = (event: FormEvent) => { event.preventDefault(); form.post(route('reviews.save'), { preserveScroll: true }); };
  return <AppLayout><Head title={`${report.period} review`} /><PageHeader title={`${report.period} Review`} eyebrow={`${formatDate(report.start)} to ${formatDate(report.end)}`} action={<Link className="btn btn-primary" href={route('reports.show', report.period)}>Open report</Link>} />
    <div className="grid gap-3 md:grid-cols-5"><Stat label="Completed work" value={report.completed_work_logs.length} tone="teal" /><Stat label="Open blockers" value={report.open_blockers.length} tone="rose" /><Stat label="Learning" value={`${report.learning_totals.hours}h`} tone="sky" /><Stat label="Logged delta" value={minutes(report.trends.logged_minutes_delta)} /><Stat label="Learning delta" value={minutes(report.trends.learning_minutes_delta)} /></div>

    <div className="mt-5 grid gap-5 xl:grid-cols-3">
      <section className="card p-5 xl:col-span-2"><h2 className="mb-4 font-semibold">Achievements</h2>{report.key_achievements.length === 0 ? <Empty text="No completed items recorded in this period." /> : <ul className="grid gap-2 md:grid-cols-2">{report.key_achievements.map((item: string) => <li key={item} className="rounded-lg border bg-teal-50/40 px-3 py-2 text-sm font-medium">{item}</li>)}</ul>}</section>
      <section className="card p-5"><h2 className="mb-4 font-semibold">Active projects</h2><RankedRows rows={report.most_active_projects} empty="No project activity." /></section>
      <section className="card p-5"><h2 className="mb-4 font-semibold">Time by category</h2><ProgressRows rows={report.time_by_category} /></section>
      <section className="card p-5"><h2 className="mb-4 font-semibold">Learning by category</h2><ProgressRows rows={report.learning_totals.by_category} /></section>
      <section className="card p-5"><h2 className="mb-4 font-semibold">Notable tags</h2><RankedRows rows={report.notable_tags} empty="No tags used." /></section>
    </div>

    <form onSubmit={save} className="mt-6 grid gap-4 xl:grid-cols-3">
      {['wins', 'patterns', 'blockers', 'decisions', 'next', 'stop'].map((field) => <ReviewPrompt key={field} title={labelFor(field)} field={field} form={form} />)}
      <label className="card p-5 xl:col-span-3"><span className="label mb-1">Review summary</span><textarea className="field min-h-28" value={form.data.summary} onChange={(e) => form.setData('summary', e.target.value)} /></label>
      <div className="xl:col-span-3 flex justify-end"><button className="btn btn-primary">Save review</button></div>
    </form>
  </AppLayout>;
}

function ReviewPrompt({ title, field, form }: any) { return <section className="card p-5"><h2 className="mb-4 font-semibold">{title}</h2><textarea className="field min-h-40" value={form.data.answers[field] || ''} onChange={(e) => form.setData('answers', { ...form.data.answers, [field]: e.target.value })} /></section>; }

function RankedRows({ rows, empty }: any) {
  const entries = Object.entries(rows || {});
  return entries.length === 0 ? <Empty text={empty} /> : <div className="space-y-2">{entries.map(([label, value]: any) => <div key={label} className="flex items-center justify-between rounded-lg border bg-white px-3 py-2 text-sm"><span className="font-semibold">{label}</span><span className="rounded bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{value}</span></div>)}</div>;
}

function ProgressRows({ rows }: any) {
  const entries = Object.entries(rows || {});
  const max = Math.max(1, ...entries.map(([, value]: any) => Number(value)));
  return entries.length === 0 ? <Empty text="No time logged." /> : <div className="space-y-4">{entries.map(([label, value]: any) => <div key={label}><div className="mb-2 flex justify-between text-sm"><span className="font-semibold">{label}</span><span className="text-slate-500">{minutes(value)}</span></div><ProgressBar value={(Number(value) / max) * 100} /></div>)}</div>;
}

function labelFor(field: string) {
  return ({ wins: 'Wins', patterns: 'Patterns', blockers: 'Blockers', decisions: 'Decisions', next: 'Next period', stop: 'Stop doing' } as any)[field] || field;
}
