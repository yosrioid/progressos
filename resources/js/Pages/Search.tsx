import { Head, Link } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';
import { Empty, PageHeader } from '../Components/UI';
import { formatDate } from '../lib/format';

export default function Search({ query, results }: any) {
  const groups = [
    ['Daily progress', 'daily-progress.show', results.daily_progress, 'title'],
    ['Work logs', 'work-logs.show', results.work_logs, 'title'],
    ['Learning', 'learning.show', results.learning, 'topic'],
    ['Milestones', 'milestones.show', results.milestones, 'title'],
  ];
  return <AppLayout><Head title="Search" /><PageHeader title={`Search: ${query || 'empty'}`} />{!query ? <Empty text="Type a search term in the top bar." /> : <div className="grid gap-4 lg:grid-cols-2">{groups.map(([title, routeName, items, key]: any) => <section className="card p-5" key={title}><h2 className="mb-4 font-semibold">{title}</h2><div className="space-y-2">{items.map((item: any) => <Link key={item.id} href={route(routeName, item.id)} className="block rounded-lg border bg-white p-3 text-sm hover:border-teal-300 hover:bg-teal-50/40 dark:bg-zinc-950 dark:hover:bg-zinc-900"><b>{item[key]}</b><p className="mt-1 text-zinc-500">{item.date ? formatDate(item.date) : item.status || item.category}</p></Link>)}{items.length === 0 && <p className="text-sm text-zinc-500">No matches.</p>}</div></section>)}</div>}</AppLayout>;
}
