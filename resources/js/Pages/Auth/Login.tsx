import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import { FieldError } from '../../Components/UI';
import { Page } from '../../types';

export default function Login() {
  const { flash } = usePage<Page>().props;
  const form = useForm({ email: '', password: '', remember: false });
  const submit = (e: FormEvent) => { e.preventDefault(); form.post(route('login')); };
  return <AuthShell title="Welcome back">
    <Head title="Login" />{flash.status && <p className="mb-3 text-sm text-teal-700">{flash.status}</p>}
    <form onSubmit={submit} className="space-y-4">
      <div><label className="label">Email</label><input className="field mt-1" type="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)} autoFocus /><FieldError message={form.errors.email} /></div>
      <div><label className="label">Password</label><input className="field mt-1" type="password" value={form.data.password} onChange={e => form.setData('password', e.target.value)} /><FieldError message={form.errors.password} /></div>
      <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.remember} onChange={e => form.setData('remember', e.target.checked)} /> Remember this device</label>
      <button className="btn btn-primary w-full" disabled={form.processing}>Log in</button>
    </form>
    <div className="mt-5 flex justify-between text-sm"><Link href={route('password.request')}>Forgot password?</Link><Link href={route('register')}>Create account</Link></div>
  </AuthShell>;
}

export function AuthShell({ title, children }: { title: string; children: React.ReactNode }) {
  return <main className="grid min-h-screen place-items-center bg-stone-100 px-4 dark:bg-zinc-950"><section className="w-full max-w-md"><div className="mb-6"><h1 className="text-3xl font-semibold">ProgressOS</h1><p className="mt-2 text-zinc-600 dark:text-zinc-400">Personal operating system for progress, learning, and work review.</p></div><div className="card p-6"><h2 className="mb-5 text-xl font-semibold">{title}</h2>{children}</div></section></main>;
}
