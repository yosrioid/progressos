import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Empty, PageHeader, Pagination, Stat } from '../../Components/UI';
import { formatDate, minutes } from '../../lib/format';

export default function Index({ entries, filters, summary }: any) {
  return <AppLayout><Head title="Learning" /><PageHeader title="Learning Tracker" action={<Link className="btn btn-primary" href={route('learning.create')}>New session</Link>} />
    <div className="mb-4 grid grid-cols-2 gap-3"><Stat label="Weekly minutes" value={summary.weekly_minutes} tone="sky" /><Stat label="Monthly minutes" value={summary.monthly_minutes} tone="teal" /></div><Filters filters={filters} />
    {entries.data.length === 0 ? <Empty text="No learning sessions match this view." /> : <>
      <div className="mobile-card-list md:hidden">{entries.data.map((e: any) => <Link key={e.id} href={route('learning.show', e.id)} className="mobile-record"><div className="mb-2 flex items-start justify-between gap-3"><div><p className="font-semibold">{e.topic}</p><p className="mt-1 text-sm text-slate-500">{formatDate(e.date)} · {e.category}</p></div><span className="rounded-md bg-teal-50 px-2 py-1 text-xs font-bold text-teal-800">{minutes(e.duration_minutes)}</span></div><p className="text-sm text-slate-500">{e.source_type}</p></Link>)}</div>
      <div className="table-wrap hidden md:block"><table className="data-table"><thead><tr><th>Date</th><th>Topic</th><th>Category</th><th>Source</th><th>Duration</th></tr></thead><tbody>{entries.data.map((e: any) => <tr key={e.id}><td className="font-medium text-zinc-500">{formatDate(e.date)}</td><td><Link className="font-semibold hover:text-teal-700" href={route('learning.show', e.id)}>{e.topic}</Link></td><td>{e.category}</td><td>{e.source_type}</td><td>{minutes(e.duration_minutes)}</td></tr>)}</tbody></table></div>
    </>}<Pagination links={entries.links} /></AppLayout>;
}
function Filters({ filters }: any) { return <form className="card mb-4 grid gap-3 p-4 md:grid-cols-4" onSubmit={e => { e.preventDefault(); router.get(route('learning.index'), Object.fromEntries(new FormData(e.currentTarget))); }}><input className="field" name="search" placeholder="Search" defaultValue={filters.search || ''} /><input className="field" name="category" placeholder="Category" defaultValue={filters.category || ''} /><input className="field" name="source_type" placeholder="Source" defaultValue={filters.source_type || ''} /><button className="btn btn-muted">Filter</button></form>; }
