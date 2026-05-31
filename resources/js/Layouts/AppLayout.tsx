import { Link, router, usePage } from '@inertiajs/react';
import { BookOpen, CheckSquare, Command, Flag, FolderKanban, Home, ListCollapse, LogOut, Plus, Search, Target, User, Workflow } from 'lucide-react';
import clsx from 'clsx';
import { FormEvent, ReactNode, useEffect, useState } from 'react';
import { Page } from '../types';

const nav = [
  ['Dashboard', 'dashboard', Home],
  ['Daily Progress', 'daily-progress.index', Target],
  ['Tasks', 'tasks.index', CheckSquare],
  ['Projects', 'projects.index', FolderKanban],
  ['Work Logs', 'work-logs.index', Workflow],
  ['Learning', 'learning.index', BookOpen],
  ['Milestones', 'milestones.index', Flag],
  ['Daily Review', 'reviews.daily', Command],
  ['Weekly Report', 'reports.show', Search, { period: 'weekly' }],
];

const mobileNav = [
  ['Home', 'dashboard', Home],
  ['Tasks', 'tasks.index', CheckSquare],
  ['Logs', 'work-logs.index', Workflow],
  ['Learn', 'learning.index', BookOpen],
  ['More', 'reviews.daily', Command],
];

export default function AppLayout({ children }: { children: ReactNode }) {
  const { auth, flash } = usePage<Page>().props;
  const [q, setQ] = useState('');
  const [quick, setQuick] = useState(false);
  const [palette, setPalette] = useState(false);
  const [compact, setCompact] = useState(() => localStorage.getItem('density') === 'compact');
  const submit = (e: FormEvent) => { e.preventDefault(); if (q.trim()) router.get(route('search'), { q }); };
  useEffect(() => {
    const handler = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        setPalette(true);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);
  useEffect(() => {
    document.documentElement.classList.toggle('density-compact', compact);
    localStorage.setItem('density', compact ? 'compact' : 'comfortable');
  }, [compact]);

  return <div className="min-h-screen bg-slate-50 text-slate-950 dark:bg-zinc-950 dark:text-zinc-50 lg:flex">
    <aside className="hidden border-r border-slate-200 bg-white/92 px-4 py-4 backdrop-blur dark:bg-zinc-950/90 lg:fixed lg:inset-y-0 lg:block lg:w-64">
      <div className="mb-6">
        <Link href={route('dashboard')} className="text-lg font-semibold">ProgressOS</Link>
      </div>
      <nav className="space-y-1">
        {nav.map(([label, name, Icon, params]: any) => <Link key={label} href={route(name, params || {})} className={clsx('flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-900', route().current(name) && 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200')}><Icon size={16} />{label}</Link>)}
      </nav>
    </aside>
    <main className="min-w-0 flex-1 lg:pl-64">
      <header className="sticky top-0 z-20 border-b border-slate-200 bg-slate-50/92 px-3 py-3 backdrop-blur dark:bg-zinc-950/90 sm:px-4">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-2 sm:flex-nowrap sm:gap-3">
          <div className="flex w-full items-center justify-between lg:hidden">
            <Link href={route('dashboard')} className="text-base font-semibold">ProgressOS</Link>
            <div className="flex items-center gap-2">
              <button className="btn btn-primary p-2" onClick={() => setQuick(true)} title="Quick add"><Plus size={17} /></button>
              <Link href={route('profile.edit')} className="btn btn-muted p-2"><User size={17} /></Link>
            </div>
          </div>
          <form onSubmit={submit} className="relative min-w-0 flex-1"><Search className="absolute left-3 top-3 text-zinc-400" size={17} /><input className="field pl-9" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search ProgressOS" /></form>
          <button className="btn btn-primary hidden p-2 sm:px-3 lg:inline-flex" onClick={() => setQuick(true)} title="Quick add"><Plus size={17} /><span>Quick Add</span></button>
          <button className="btn btn-muted p-2" onClick={() => setPalette(true)} title="Command palette"><Command size={17} /></button>
          <button className="btn btn-muted hidden p-2 sm:inline-flex" onClick={() => setCompact(!compact)} title="Toggle density"><ListCollapse size={17} /></button>
          <Link href={route('profile.edit')} className="hidden text-sm font-medium lg:block">{auth.user?.name}</Link>
          <button className="btn btn-muted hidden p-2 sm:inline-flex" onClick={() => router.post(route('logout'))} title="Log out"><LogOut size={17} /></button>
        </div>
      </header>
      <section className="bottom-nav-safe mx-auto max-w-7xl px-3 py-4 sm:px-4 sm:py-6">
        {flash.success && <div className="mb-4 rounded-lg border border-teal-200 bg-teal-50 p-3 text-sm text-teal-900">{flash.success}</div>}
        {children}
      </section>
    </main>
    <nav className="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))] pt-2 shadow-[0_-8px_30px_rgb(15_23_42/0.08)] backdrop-blur lg:hidden">
      <div className="mx-auto grid max-w-md grid-cols-5 gap-1">
        {mobileNav.map(([label, name, Icon]: any) => <Link key={label} href={route(name)} className={clsx('flex flex-col items-center gap-1 rounded-xl px-2 py-2 text-[11px] font-semibold text-slate-500', route().current(name) && 'bg-teal-50 text-teal-800')}><Icon size={18} />{label}</Link>)}
      </div>
    </nav>
    {quick && <QuickAdd onClose={() => setQuick(false)} />}
    {palette && <CommandPalette onClose={() => setPalette(false)} />}
  </div>;
}

function QuickAdd({ onClose }: { onClose: () => void }) {
  const [data, setData] = useState({ type: 'task', title: '', date: new Date().toISOString().slice(0, 10), project_name: '', duration_minutes: '30', notes: '' });
  const submit = (event: FormEvent) => {
    event.preventDefault();
    router.post(route('quick-capture'), data, { onSuccess: onClose, preserveScroll: true });
  };

  return <div className="fixed inset-0 z-40 overflow-hidden bg-zinc-950/40 sm:grid sm:place-items-center sm:p-4">
    <form onSubmit={submit} className="card fixed inset-x-3 bottom-3 top-3 flex flex-col overflow-hidden shadow-2xl sm:static sm:max-h-[calc(100dvh-2rem)] sm:w-full sm:max-w-xl">
      <div className="flex shrink-0 items-center justify-between border-b px-4 py-4 sm:px-5"><h2 className="text-lg font-semibold">Quick Add</h2><div className="flex gap-2"><button type="button" className="btn btn-muted px-3 py-1" onClick={onClose}>Close</button><button className="btn btn-primary px-3 py-1 sm:hidden" type="submit">Capture</button></div></div>
      <div className="grid max-h-[calc(100dvh-11rem)] gap-3 overflow-y-auto px-4 py-4 sm:grid-cols-2 sm:px-5">
        <select className="field" value={data.type} onChange={(e) => setData({ ...data, type: e.target.value })}><option value="task">Task</option><option value="blocker">Blocker</option><option value="work_log">Work log</option><option value="daily_progress">Daily progress</option><option value="learning">Learning</option></select>
        <input className="field" type="date" value={data.date} onChange={(e) => setData({ ...data, date: e.target.value })} />
        <input className="field sm:col-span-2" placeholder="Title" value={data.title} onChange={(e) => setData({ ...data, title: e.target.value })} autoFocus />
        <input className="field" placeholder="Project" value={data.project_name} onChange={(e) => setData({ ...data, project_name: e.target.value })} />
        <input className="field" type="number" min="1" placeholder="Minutes" value={data.duration_minutes} onChange={(e) => setData({ ...data, duration_minutes: e.target.value })} />
        <textarea className="field min-h-28 sm:col-span-2" placeholder="Notes" value={data.notes} onChange={(e) => setData({ ...data, notes: e.target.value })} />
      </div>
      <div className="relative z-10 hidden shrink-0 justify-end border-t bg-white px-4 py-3 dark:bg-zinc-950 sm:flex sm:px-5"><button className="btn btn-primary" type="submit">Capture</button></div>
    </form>
  </div>;
}

function CommandPalette({ onClose }: { onClose: () => void }) {
  const items = [
    ['Dashboard', route('dashboard')],
    ['Today review', route('reviews.daily')],
    ['New task', route('tasks.create')],
    ['New progress', route('daily-progress.create')],
    ['New work log', route('work-logs.create')],
    ['New learning', route('learning.create')],
    ['New milestone', route('milestones.create')],
    ['Weekly review', route('reviews.period', 'weekly')],
    ['Monthly review', route('reviews.period', 'monthly')],
  ];

  return <div className="fixed inset-0 z-40 grid place-items-start bg-zinc-950/40 p-4 pt-24">
    <div className="card mx-auto w-full max-w-xl overflow-hidden shadow-2xl">
      <div className="border-b p-3"><input className="field" placeholder="Jump to..." autoFocus onKeyDown={(e) => e.key === 'Escape' && onClose()} /></div>
      <div className="p-2">{items.map(([label, href]) => <Link key={label} href={href} onClick={onClose} className="block rounded-lg px-3 py-2 text-sm font-semibold hover:bg-teal-50 hover:text-teal-800 dark:hover:bg-zinc-900">{label}</Link>)}</div>
    </div>
  </div>;
}
