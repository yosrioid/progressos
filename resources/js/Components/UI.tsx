import { Link, router, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { ClipboardEvent, FormEvent, ReactNode, useState } from 'react';
import { ArrowLeft } from 'lucide-react';

export function PageHeader({ title, action, eyebrow, backHref }: { title: string; action?: ReactNode; eyebrow?: string; backHref?: string }) {
  return <div className="mb-5 flex flex-col gap-3 sm:mb-6 sm:flex-row sm:items-end sm:justify-between">
    <div className="min-w-0">
      {backHref && <Link href={backHref} className="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-zinc-500 hover:text-teal-700"><ArrowLeft size={16} /> Back</Link>}
      {eyebrow && <p className="mb-1 text-sm font-semibold text-teal-700 dark:text-teal-300">{eyebrow}</p>}
      <h1 className="truncate text-xl font-semibold tracking-normal sm:text-2xl">{title}</h1>
    </div>
    {action}
  </div>;
}

export function Stat({ label, value, tone = 'neutral' }: { label: string; value: ReactNode; tone?: 'neutral' | 'teal' | 'amber' | 'rose' | 'sky' }) {
  const tones = {
    neutral: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200',
    teal: 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200',
    amber: 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    rose: 'bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-200',
    sky: 'bg-sky-50 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
  };
  return <div className="card p-3.5 sm:p-4"><p className="text-[11px] font-semibold uppercase text-zinc-500 sm:text-xs">{label}</p><div className={clsx('mt-2 inline-flex min-w-10 items-center justify-center rounded-lg px-2.5 py-1.5 text-xl font-semibold sm:mt-3 sm:min-w-12 sm:px-3 sm:py-2 sm:text-2xl', tones[tone])}>{value}</div></div>;
}

export function ProgressBar({ value }: { value: number }) {
  return <div className="h-2 rounded-full bg-zinc-200 dark:bg-zinc-800"><div className="h-2 rounded-full bg-teal-500" style={{ width: `${Math.min(100, Math.max(0, value))}%` }} /></div>;
}

export function Tags({ tags = [] }: { tags?: { name: string }[] }) {
  return <div className="flex flex-wrap gap-1.5">{tags.map((tag) => <span key={tag.name} className="rounded-md bg-teal-50 px-2 py-1 text-xs font-semibold text-teal-800 dark:bg-teal-950 dark:text-teal-200">{tag.name}</span>)}</div>;
}

export function Empty({ text }: { text: string }) {
  return <div className="card p-8 text-center text-sm text-zinc-500">{text}</div>;
}

export function Pagination({ links }: { links: { url: string | null; label: string; active: boolean }[] }) {
  return <div className="mt-5 flex flex-wrap gap-2">{links.map((link, i) => link.url ? <Link key={i} href={link.url} className={clsx('btn btn-muted px-3 py-2', link.active && 'border-teal-500 text-teal-700')} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={i} className="btn btn-muted px-3 py-2 opacity-40" dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>;
}

export function FieldError({ message }: { message?: string }) {
  return message ? <p className="mt-1 text-xs font-medium text-red-600">{message}</p> : null;
}

export function Section({ title, children }: { title: string; children: ReactNode }) {
  return <section className="card p-5"><h2 className="mb-4 text-sm font-bold uppercase text-zinc-500">{title}</h2>{children}</section>;
}

export function DetailText({ value }: { value?: string | null }) {
  if (!value) return <p className="text-sm text-zinc-500">None</p>;
  const tokenPattern = /(\[[^\]]+\]\(https?:\/\/[^)\s]+\)|https?:\/\/[^\s]+)/g;
  const parts = value.split(tokenPattern);

  return <p className="whitespace-pre-wrap text-sm leading-6 text-zinc-700 dark:text-zinc-200">{parts.map((part, index) => {
    const markdown = part.match(/^\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)$/);
    if (markdown) return <SmartLink key={index} href={markdown[2]} label={markdown[1]} />;
    if (!part.match(/^https?:\/\//)) return <span key={index}>{part}</span>;

    return <SmartLink key={index} href={part} label={shortUrlLabel(part)} />;
  })}</p>;
}

export function FormShell({ children }: { children: ReactNode }) {
  return <div className="card overflow-hidden"><div className="border-b bg-slate-50 px-4 py-3 dark:bg-zinc-900 sm:px-5 sm:py-4"><p className="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Keep it concise, searchable, and reviewable later.</p></div><div className="grid gap-4 p-4 pb-28 sm:gap-5 sm:p-5 lg:grid-cols-2 lg:pb-5">{children}</div></div>;
}

export function ReferencesPanel({ type, id, references = [] }: { type: string; id: number; references?: any[] }) {
  const [open, setOpen] = useState(false);
  const form = useForm({ referenceable_type: type, referenceable_id: id, label: '', url: '', type: 'link', notes: '' });
  const submit = (event: FormEvent) => {
    event.preventDefault();
    form.post(route('references.store'), { preserveScroll: true, onSuccess: () => { form.reset('label', 'url', 'notes'); setOpen(false); } });
  };

  return <section className="card p-5">
    <div className="mb-4 flex items-center justify-between"><h2 className="font-semibold">References</h2><button className="btn btn-muted px-3 py-1" onClick={() => setOpen(!open)}>{open ? 'Cancel' : 'Add'}</button></div>
    {open && <form onSubmit={submit} className="mb-4 grid gap-3 md:grid-cols-2">
      <input className="field" placeholder="Label" value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} />
      <select className="field" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}><option value="link">link</option><option value="doc">doc</option><option value="ticket">ticket</option><option value="pr">pr</option><option value="article">article</option><option value="course">course</option><option value="other">other</option></select>
      <input className="field md:col-span-2" placeholder="https://..." value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} />
      <textarea className="field md:col-span-2" placeholder="Notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
      <div className="md:col-span-2 flex justify-end"><button className="btn btn-primary" disabled={form.processing}>Save reference</button></div>
    </form>}
    <div className="space-y-2">{references.map((ref) => <div key={ref.id} className="flex items-center justify-between gap-3 rounded-lg border p-3"><a className="font-semibold text-teal-700 hover:underline" href={ref.url} target="_blank" rel="noreferrer">{ref.label}</a><button className="text-xs font-semibold text-red-600" onClick={() => confirm('Remove reference?') && router.delete(route('references.destroy', ref.id), { preserveScroll: true })}>Remove</button></div>)}{references.length === 0 && <p className="text-sm text-zinc-500">No references attached.</p>}</div>
  </section>;
}

export function ErrorSummary({ errors }: { errors: Record<string, string> }) {
  const items = Object.entries(errors);
  if (items.length === 0) return null;

  return <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
    <p className="font-semibold">Save failed. Check these fields:</p>
    <ul className="mt-2 list-disc pl-5">{items.map(([field, message]) => <li key={field}>{message}</li>)}</ul>
  </div>;
}

export function handleSmartLinkPaste(event: ClipboardEvent<HTMLTextAreaElement>, currentValue: string, setValue: (value: string) => void) {
  const pasted = event.clipboardData.getData('text').trim();
  if (!pasted.match(/^https?:\/\/\S+$/)) return;

  const input = event.currentTarget;
  const start = input.selectionStart;
  const end = input.selectionEnd;
  const selected = currentValue.slice(start, end).trim();

  if (!selected || selected.match(/^https?:\/\//)) return;

  event.preventDefault();
  const replacement = `[${selected}](${pasted})`;
  setValue(`${currentValue.slice(0, start)}${replacement}${currentValue.slice(end)}`);

  requestAnimationFrame(() => {
    const cursor = start + replacement.length;
    input.setSelectionRange(cursor, cursor);
  });
}

function SmartLink({ href, label }: { href: string; label: string }) {
  return <a href={href} target="_blank" rel="noreferrer" className="font-semibold text-teal-700 underline decoration-teal-300 hover:text-teal-900 dark:text-teal-300">{label}</a>;
}

function shortUrlLabel(value: string) {
  try {
    const url = new URL(value);
    return `${url.hostname}${url.pathname === '/' ? '' : url.pathname}`;
  } catch {
    return value;
  }
}
