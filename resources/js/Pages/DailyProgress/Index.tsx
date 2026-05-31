import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Empty, PageHeader, Pagination, Tags } from '../../Components/UI';
import { formatDate } from '../../lib/format';

export default function Index({ entries, filters }: any) {
  return <AppLayout><Head title="Daily Progress" /><PageHeader title="Daily Progress" action={<div className="flex gap-2"><button className="btn btn-muted" onClick={() => router.post(route('daily-progress.duplicate'))}>Duplicate previous</button><Link className="btn btn-primary" href={route('daily-progress.create')}>New entry</Link></div>} />
    <Filters filters={filters} />
    {entries.data.length === 0 ? <Empty text="No progress entries match this view." /> : <>
      <div className="mobile-card-list md:hidden">{entries.data.map((e: any) => <Link key={e.id} href={route('daily-progress.show', e.id)} className="mobile-record"><div className="mb-3 flex items-start justify-between gap-3"><div><p className="font-semibold">{e.title}</p><p className="mt-1 text-sm text-slate-500">{formatDate(e.date)}</p></div><span className="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{e.mood || '-'}</span></div><Tags tags={e.tags} /></Link>)}</div>
      <div className="table-wrap hidden md:block"><table className="data-table"><thead><tr><th>Date</th><th>Title</th><th>Mood</th><th>Tags</th></tr></thead><tbody>{entries.data.map((e: any) => <tr key={e.id}><td className="font-medium text-zinc-500">{formatDate(e.date)}</td><td><Link className="font-semibold hover:text-teal-700" href={route('daily-progress.show', e.id)}>{e.title}</Link></td><td>{e.mood || '-'}</td><td><Tags tags={e.tags} /></td></tr>)}</tbody></table></div>
    </>}
    <Pagination links={entries.links} />
  </AppLayout>;
}
function Filters({ filters }: any) { return <form className="card mb-4 grid gap-3 p-4 md:grid-cols-5" onSubmit={e => { e.preventDefault(); const data = Object.fromEntries(new FormData(e.currentTarget)); router.get(route('daily-progress.index'), data); }}><input className="field" name="search" placeholder="Search" defaultValue={filters.search || ''} /><input className="field" type="date" name="from" defaultValue={filters.from || ''} /><input className="field" type="date" name="to" defaultValue={filters.to || ''} /><input className="field" name="tag" placeholder="Tag" defaultValue={filters.tag || ''} /><button className="btn btn-muted">Filter</button></form>; }
