import Icon from '@/Components/Icon';
import { Field, Notice, useResendCooldown } from '@/Pages/Auth/Partials/AuthFields';
import { useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({ status, className = '' }) {
    const user = usePage().props.auth.user;
    const form = useForm({ name: user.name, email: user.email });
    const verification = useForm({});
    const [seconds, startCooldown] = useResendCooldown();

    function submit(event) {
        event.preventDefault();
        form.transform((data) => ({ ...data, email: data.email.trim().toLowerCase() }))
            .patch(route('profile.update'), { preserveScroll: true });
    }

    return (
        <section className={className}>
            <div className="flex items-center gap-3">
                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-teal-700"><Icon name="user" /></span>
                <div><h2 className="text-base font-extrabold text-slate-900">Informasi akun</h2><p className="mt-1 text-xs text-slate-500">Nama dan email yang digunakan di SINTAS.</p></div>
            </div>
            <form onSubmit={submit} className="mt-5 space-y-4" aria-busy={form.processing}>
                <Field label="Nama lengkap" field="name" form={form} autoComplete="name" maxLength={255} disabled={form.processing} />
                <Field label="Email" field="email" type="email" form={form} autoComplete="username" maxLength={255} disabled={form.processing} hint="Mengubah email memerlukan verifikasi ulang." />
                <button type="submit" disabled={form.processing} className="button-primary">{form.processing ? 'Menyimpan...' : 'Simpan informasi akun'}</button>
                <Notice>{form.recentlySuccessful && 'Informasi akun berhasil disimpan.'}</Notice>
            </form>
            {!user.email_verified_at && <div className="mt-5 space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p className="text-sm leading-6 text-amber-900">Emailmu belum terverifikasi. Verifikasi email untuk mengakses kegiatan akademik.</p>
                <button type="button" className="text-sm font-bold text-teal-700 disabled:text-slate-400" disabled={verification.processing || seconds > 0}
                    onClick={() => verification.post(route('verification.send'), { preserveScroll: true, onSuccess: startCooldown })}>
                    {verification.processing ? 'Mengirim...' : seconds > 0 ? `Kirim ulang dalam ${seconds} detik` : 'Kirim tautan verifikasi'}
                </button>
                <Notice>{status === 'verification-link-sent' && 'Tautan verifikasi telah dikirim ke emailmu.'}</Notice>
                <Notice error>{Object.values(verification.errors)[0]}</Notice>
            </div>}
        </section>
    );
}
