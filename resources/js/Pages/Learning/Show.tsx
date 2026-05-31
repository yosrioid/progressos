import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { DetailText, PageHeader } from '../../Components/UI';
import { minutes } from '../../lib/format';

export default function Show({ entry }: any) {
  return <AppLayout><Head title={entry.topic} /><PageHeader title={entry.topic} eyebrow={`${entry.category} · ${minutes(entry.duration_minutes)}`} backHref={route('learning.index')} action={<Link className="btn btn-muted" href={route('learning.edit', entry.id)}>Edit</Link>} /><article className="card grid gap-4 p-5 lg:grid-cols-3">{['progress_notes', 'takeaway', 'next_action'].map(k => <section className="rounded-lg border p-4" key={k}><h2 className="mb-2 font-semibold">{k.replaceAll('_', ' ')}</h2><DetailText value={entry[k]} /></section>)}</article></AppLayout>;
}
