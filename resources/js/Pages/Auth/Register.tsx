import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { FieldError } from '../../Components/UI';
import { AuthShell } from './Login';

export default function Register() {
  const form = useForm({ name: '', email: '', timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC', password: '', password_confirmation: '' });
  const submit = (e: FormEvent) => { e.preventDefault(); form.post(route('register')); };
  return <AuthShell title="Create your workspace"><Head title="Register" /><form onSubmit={submit} className="space-y-4">
    {(['name', 'email', 'timezone'] as const).map((key) => <div key={key}><label className="label">{key.replace('_', ' ')}</label><input className="field mt-1" value={form.data[key]} onChange={e => form.setData(key, e.target.value)} /><FieldError message={form.errors[key]} /></div>)}
    <div><label className="label">Password</label><input className="field mt-1" type="password" value={form.data.password} onChange={e => form.setData('password', e.target.value)} /><FieldError message={form.errors.password} /></div>
    <div><label className="label">Confirm password</label><input className="field mt-1" type="password" value={form.data.password_confirmation} onChange={e => form.setData('password_confirmation', e.target.value)} /></div>
    <button className="btn btn-primary w-full" disabled={form.processing}>Register</button>
  </form><p className="mt-5 text-sm"><Link href={route('login')}>Already have an account?</Link></p></AuthShell>;
}
