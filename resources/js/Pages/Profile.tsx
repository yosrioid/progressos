import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppLayout from '../Layouts/AppLayout';
import { FieldError, PageHeader } from '../Components/UI';

export default function Profile({ user }: any) {
  const profile = useForm({ name: user.name, email: user.email, timezone: user.timezone, theme: user.theme, avatar: null as any });
  const password = useForm({ current_password: '', password: '', password_confirmation: '' });
  const submitProfile = (e: FormEvent) => { e.preventDefault(); profile.post(route('profile.update'), { forceFormData: true, method: 'patch' }); };
  const submitPassword = (e: FormEvent) => { e.preventDefault(); password.put(route('profile.password')); };
  return <AppLayout><Head title="Profile" /><PageHeader title="Profile" />
    <div className="grid gap-4 lg:grid-cols-2"><form onSubmit={submitProfile} className="card space-y-4 p-5">
      {(['name', 'email', 'timezone'] as const).map(k => <div key={k}><label className="label">{k}</label><input className="field mt-1" value={profile.data[k]} onChange={e => profile.setData(k, e.target.value)} /><FieldError message={profile.errors[k]} /></div>)}
      <div><label className="label">Theme</label><select className="field mt-1" value={profile.data.theme} onChange={e => profile.setData('theme', e.target.value)}><option>system</option><option>light</option><option>dark</option></select></div>
      <div><label className="label">Avatar</label><input className="field mt-1" type="file" onChange={e => profile.setData('avatar', e.target.files?.[0])} /></div>
      <button className="btn btn-primary">Save profile</button>
    </form><form onSubmit={submitPassword} className="card space-y-4 p-5">
      <div><label className="label">Current password</label><input className="field mt-1" type="password" value={password.data.current_password} onChange={e => password.setData('current_password', e.target.value)} /><FieldError message={password.errors.current_password} /></div>
      <div><label className="label">New password</label><input className="field mt-1" type="password" value={password.data.password} onChange={e => password.setData('password', e.target.value)} /><FieldError message={password.errors.password} /></div>
      <div><label className="label">Confirm password</label><input className="field mt-1" type="password" value={password.data.password_confirmation} onChange={e => password.setData('password_confirmation', e.target.value)} /></div>
      <button className="btn btn-primary">Change password</button>
    </form></div>
  </AppLayout>;
}
