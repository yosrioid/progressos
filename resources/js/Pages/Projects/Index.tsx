import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Empty, PageHeader, Stat } from '../../Components/UI';

export default function Index({ projects }: any) {
  return <AppLayout><Head title="Projects" /><PageHeader title="Projects" />
    {projects.length === 0 ? <Empty text="Projects appear automatically when work logs or tasks use a project name." /> : <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">{projects.map((project: any) => <Link key={project.id} href={route('projects.show', project.id)} className="card p-4 hover:border-teal-400 sm:p-5"><h2 className="text-lg font-semibold">{project.name}</h2><div className="mt-4 grid grid-cols-3 gap-2"><Stat label="Open" value={project.open_tasks_count} tone="sky" /><Stat label="Tasks" value={project.tasks_count} /><Stat label="Logs" value={project.work_logs_count} tone="teal" /></div></Link>)}</div>}
  </AppLayout>;
}
