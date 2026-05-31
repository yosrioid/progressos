import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { DetailText, PageHeader, ProgressBar } from '../../Components/UI';

export default function Show({ milestone }: any) {
  return <AppLayout><Head title={milestone.title} /><PageHeader title={milestone.title} eyebrow={`${milestone.category} · ${milestone.status}`} backHref={route('milestones.index')} action={<Link className="btn btn-muted" href={route('milestones.edit', milestone.id)}>Edit</Link>} /><article className="card p-5"><ProgressBar value={milestone.progress_percent} /><p className="mt-3 text-sm font-medium text-zinc-500">{milestone.current_value} / {milestone.target_value} {milestone.target_type}</p><section className="mt-6 rounded-lg border p-4"><h2 className="mb-2 font-semibold">Notes</h2><DetailText value={milestone.notes} /></section></article></AppLayout>;
}
