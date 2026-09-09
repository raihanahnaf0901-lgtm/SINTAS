import GuestLayout from '@/Layouts/GuestLayout';
import { Field, Notice } from './Partials/AuthFields';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ResetPassword({ token, email }) {
    const form = useForm({ token, email: email ?? '', password: '', password_confirmation: '' });

    function submit(event) {
        event.preventDefault();
        form.post(route('password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
    }

    return (
        <GuestLayout>
            <Head title="Buat password baru" />
            <h1 className="text-2xl font-extrabold text-slate-900">Buat password baru</h1>
            <p className="mb-6 mt-2 text-sm leading-6 text-slate-500">Simpan password baru untuk melanjutkan aktivitas belajarmu.</p>
            <form onSubmit={submit} className="space-y-4" aria-busy={form.processing}>
                <Field label="Email akun" field="email" type="email" form={form} autoComplete="username" disabled={form.processing} />
                <Field label="Password baru" field="password" type="password" form={form} minLength={8} autoComplete="new-password" disabled={form.processing} autoFocus hint="Gunakan minimal 8 karakter." />
                <Field label="Ulangi password baru" field="password_confirmation" type="password" form={form} minLength={8} autoComplete="new-password" disabled={form.processing} />
                <Notice error>{form.errors.token}</Notice>
                <button className="button-primary w-full" type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan password baru'}</button>
                <Link href={route('password.request')} className="block text-center text-sm font-semibold text-teal-700">Tautan kedaluwarsa? Minta kode pemulihan</Link>
            </form>
        </GuestLayout>
    );
}
