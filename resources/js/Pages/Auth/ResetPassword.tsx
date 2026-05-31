import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { FieldError } from '../../Components/UI';
import { AuthShell } from './Login';

export default function ResetPassword({ email, token }: { email: string; token: string }) {
  const form = useForm({ email, token, password: '', password_confirmation: '' });
  const submit = (e: FormEvent) => { e.preventDefault(); form.post(route('password.store')); };
  return <AuthShell title="Set new password"><Head title="Reset Password" /><form onSubmit={submit} className="space-y-4"><input type="hidden" value={token} /><div><label className="label">Email</label><input className="field mt-1" value={form.data.email} onChange={e => form.setData('email', e.target.value)} /><FieldError message={form.errors.email} /></div><div><label className="label">Password</label><input className="field mt-1" type="password" value={form.data.password} onChange={e => form.setData('password', e.target.value)} /><FieldError message={form.errors.password} /></div><div><label className="label">Confirm password</label><input className="field mt-1" type="password" value={form.data.password_confirmation} onChange={e => form.setData('password_confirmation', e.target.value)} /></div><button className="btn btn-primary w-full">Update password</button></form></AuthShell>;
}
