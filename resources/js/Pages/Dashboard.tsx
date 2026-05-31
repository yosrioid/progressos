import { Head, Link } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';
import { PageHeader, ProgressBar, Stat, Tags } from '../Components/UI';
import { compactDate, formatDate, inputDate } from '../lib/format';

export default function Dashboard({ today, summary, weekly_activity, monthly_activity, latest_progress, latest_work_logs, milestones, streaks, projects }: any) {
  return <AppLayout><Head title="Dashboard" /><PageHeader title="Dashboard" eyebrow="Today" action={<Link className="btn btn-primary" href={route('daily-progress.create')}>New progress</Link>} />
    <section className="card mb-5 p-4 sm:mb-6 sm:p-5">
      <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h2 className="text-lg font-semibold">Today Workspace</h2><p className="text-sm text-zinc-500">{formatDate(today.date)}</p></div><Link className="btn btn-muted" href={route('reviews.daily')}>Run daily review</Link></div>
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <TodayColumn title="Focus tasks" items={today.tasks} routeName="tasks.show" empty="No focus tasks due." />
        <TodayColumn title="Progress" items={today.progress} routeName="daily-progress.show" empty="No progress entry yet." />
        <TodayColumn title="Work logged" items={today.work_logs} routeName="work-logs.show" empty="No work logged today." />
        <TodayColumn title="Learning" items={today.learning} routeName="learning.show" titleKey="topic" empty="No learning today." />
      </div>
    </section>
    <div className="grid gap-3 grid-cols-2 xl:grid-cols-4">{Object.entries(summary).map(([k, v], i) => <Stat key={k} label={k.replaceAll('_', ' ')} value={String(v)} tone={['teal', 'sky', 'neutral', 'rose'][i % 4] as any} />)}</div>
    <div className="mt-5 grid gap-4 lg:grid-cols-2 sm:mt-6">
      <Chart title="Weekly activity" data={weekly_activity} />
      <Chart title="Monthly activity" data={monthly_activity} compact />
    </div>
    <div className="mt-5 grid gap-4 xl:grid-cols-3 sm:mt-6">
      <section className="card p-5 xl:col-span-1"><h2 className="mb-4 font-semibold">Streaks</h2><div className="grid grid-cols-2 gap-3"><Stat label="Daily progress" value={`${streaks.daily_progress}d`} tone="teal" /><Stat label="Learning" value={`${streaks.learning}d`} tone="sky" /></div></section>
      <section className="card p-5 xl:col-span-2"><h2 className="mb-4 font-semibold">Milestone progress</h2><div className="space-y-5">{milestones.map((m: any) => <div key={m.id}><div className="mb-2 flex justify-between gap-4 text-sm"><Link href={route('milestones.show', m.id)} className="font-semibold hover:text-teal-700">{m.title}</Link><span className="rounded bg-zinc-100 px-2 py-1 text-xs font-bold dark:bg-zinc-800">{m.progress_percent}%</span></div><ProgressBar value={m.progress_percent} /></div>)}</div></section>
    </div>
    <section className="card mt-5 p-4 sm:mt-6 sm:p-5"><h2 className="mb-4 font-semibold">Active projects</h2><div className="grid gap-2 sm:grid-cols-2 md:grid-cols-4">{projects.map((project: any) => <div key={project.id} className="rounded-lg border bg-slate-50 p-3"><p className="font-semibold">{project.name}</p><p className="text-sm text-zinc-500">{project.open_tasks_count} open tasks</p></div>)}</div></section>
    <div className="mt-5 grid gap-4 lg:grid-cols-2 sm:mt-6"><Feed title="Latest daily progress" items={latest_progress} routeName="daily-progress.show" /><Feed title="Latest work logs" items={latest_work_logs} routeName="work-logs.show" /></div>
  </AppLayout>;
}

function TodayColumn({ title, items, routeName, titleKey = 'title', empty }: any) {
  return <div className="rounded-xl border bg-slate-50 p-3 dark:bg-zinc-900"><h3 className="mb-3 text-xs font-bold uppercase text-zinc-500">{title}</h3><div className="space-y-2">{items.map((item: any) => <Link key={item.id} href={route(routeName, item.id)} className="block rounded-lg bg-white px-3 py-2 text-sm font-semibold shadow-sm hover:text-teal-700 dark:bg-zinc-950">{item[titleKey]}</Link>)}{items.length === 0 && <p className="text-sm text-zinc-500">{empty}</p>}</div></div>;
}

function Chart({ title, data, compact = false }: any) {
  const max = Math.max(1, ...data.map((d: any) => d.work + d.learning + d.progress));
  return <section className="card p-4 sm:p-5"><h2 className="mb-4 font-semibold">{title}</h2><div className="flex h-40 items-end gap-1 rounded-xl bg-slate-50 p-3 dark:bg-zinc-900 sm:h-48 sm:gap-1.5">{data.map((d: any) => <div key={d.date} className="flex flex-1 flex-col items-center gap-2"><div className="w-full rounded-t-md bg-gradient-to-t from-teal-700 to-sky-400" title={`${formatDate(d.date)}: ${d.work + d.learning + d.progress}`} style={{ height: `${Math.max(6, ((d.work + d.learning + d.progress) / max) * 150)}px` }} /><span className="text-[10px] font-medium text-zinc-500">{compact ? inputDate(d.date).slice(8) : compactDate(d.date)}</span></div>)}</div></section>;
}

function Feed({ title, items, routeName }: any) {
  return <section className="card p-5"><h2 className="mb-4 font-semibold">{title}</h2><div className="space-y-3">{items.map((item: any) => <Link key={item.id} href={route(routeName, item.id)} className="block rounded-lg border bg-white p-3 hover:border-teal-300 hover:bg-teal-50/40 dark:bg-zinc-950 dark:hover:bg-zinc-900"><div className="mb-2 flex justify-between gap-3"><p className="font-semibold">{item.title}</p><span className="text-xs font-medium text-zinc-500">{formatDate(item.date)}</span></div><Tags tags={item.tags} /></Link>)}</div></section>;
}
