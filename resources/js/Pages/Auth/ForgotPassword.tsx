import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { FieldError } from '../../Components/UI';
import { AuthShell } from './Login';

export default function ForgotPassword() {
  const form = useForm({ email: '' });
  const submit = (e: FormEvent) => { e.preventDefault(); form.post(route('password.email')); };
  return <AuthShell title="Reset access"><Head title="Forgot Password" /><form onSubmit={submit} className="space-y-4"><div><label className="label">Email</label><input className="field mt-1" type="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)} /><FieldError message={form.errors.email} /></div><button className="btn btn-primary w-full">Send reset link</button></form><p className="mt-5 text-sm"><Link href={route('login')}>Back to login</Link></p></AuthShell>;
}
