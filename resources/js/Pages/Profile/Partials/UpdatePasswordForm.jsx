import Icon from '@/Components/Icon';
import { Field, Notice } from '@/Pages/Auth/Partials/AuthFields';
import { useForm } from '@inertiajs/react';

export default function UpdatePasswordForm({ className = '' }) {
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });

    function submit(event) {
        event.preventDefault();
        form.put(route('password.update'), { preserveScroll: true, onSuccess: () => form.reset(), onError: () => form.reset('password', 'password_confirmation') });
    }

    return (
        <section className={className}>
            <div className="flex items-center gap-3">
                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-teal-700"><Icon name="lock" /></span>
                <div><h2 className="text-base font-extrabold text-slate-900">Keamanan akun</h2><p className="mt-1 text-xs text-slate-500">Ubah password SINTAS secara berkala.</p></div>
            </div>
            <form onSubmit={submit} className="mt-5 space-y-4" aria-busy={form.processing}>
                <Field label="Password saat ini" field="current_password" type="password" form={form} disabled={form.processing} autoComplete="current-password" />
                <Field label="Password baru" field="password" type="password" form={form} disabled={form.processing} autoComplete="new-password" minLength={8} hint="Minimal 8 karakter. Pilih password yang hanya kamu ketahui." />
                <Field label="Ulangi password baru" field="password_confirmation" type="password" form={form} disabled={form.processing} autoComplete="new-password" minLength={8} />
                <button type="submit" className="button-primary" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Perbarui password'}</button>
                <Notice>{form.recentlySuccessful && 'Password berhasil diperbarui.'}</Notice>
            </form>
        </section>
    );
}
