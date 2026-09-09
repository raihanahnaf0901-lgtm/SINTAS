import Icon from '@/Components/Icon';
import GuestLayout from '@/Layouts/GuestLayout';
import { Field } from './Partials/AuthFields';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ConfirmPassword() {
    const form = useForm({ password: '' });

    function submit(event) {
        event.preventDefault();
        form.post(route('password.confirm'), { onFinish: () => form.reset('password') });
    }

    return (
        <GuestLayout>
            <Head title="Konfirmasi password" />
            <span className="mb-5 inline-flex rounded-xl bg-teal-50 p-3 text-teal-700"><Icon name="lock" className="h-6 w-6" /></span>
            <h1 className="text-2xl font-extrabold text-slate-900">Konfirmasi password</h1>
            <p className="mb-6 mt-2 text-sm leading-6 text-slate-500">Masukkan kembali password SINTAS untuk melanjutkan ke pengaturan akun.</p>
            <form onSubmit={submit} className="space-y-4" aria-busy={form.processing}>
                <Field label="Password SINTAS" field="password" type="password" form={form} autoComplete="current-password" disabled={form.processing} autoFocus />
                <button className="button-primary w-full" type="submit" disabled={form.processing}>{form.processing ? 'Memeriksa...' : 'Konfirmasi dan lanjutkan'}</button>
                <Link href={route('profile.edit')} className="block text-center text-sm font-semibold text-slate-500 hover:text-teal-700">Kembali ke profil</Link>
            </form>
        </GuestLayout>
    );
}
