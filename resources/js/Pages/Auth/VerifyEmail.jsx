import Icon from '@/Components/Icon';
import Modal from '@/Components/Modal';
import GuestLayout from '@/Layouts/GuestLayout';
import { Notice, useResendCooldown } from './Partials/AuthFields';
import { DialogTitle } from '@headlessui/react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function VerifyEmail({ status }) {
    const user = usePage().props.auth?.user;
    const form = useForm({});
    const logout = useForm({});
    const [showLogout, setShowLogout] = useState(false);
    const [seconds, startCooldown] = useResendCooldown();

    function submit(event) {
        event.preventDefault();
        if (form.processing || seconds > 0) return;
        form.post(route('verification.send'), { onSuccess: startCooldown });
    }

    return (
        <GuestLayout>
            <Head title="Verifikasi email" />
            <span className="mb-5 inline-flex rounded-xl bg-teal-50 p-3 text-teal-700"><Icon name="check" className="h-6 w-6" /></span>
            <h1 className="text-2xl font-extrabold text-slate-900">Verifikasi emailmu</h1>
            <p className="mb-5 mt-2 text-sm leading-6 text-slate-500">Buka email verifikasi dan klik tautan di dalamnya untuk mengaktifkan akses akunmu. Periksa folder spam jika email belum terlihat.</p>
            <p className="mb-5 break-all rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">{user?.email}</p>
            <div className="space-y-4">
                <Notice>{status === 'verification-link-sent' && 'Tautan verifikasi sudah dikirim. Silakan periksa kotak masuk.'}</Notice>
                <Notice error>{Object.values(form.errors)[0]}</Notice>
                <form onSubmit={submit}>
                    <button className="button-primary w-full" disabled={form.processing || seconds > 0} type="submit">
                        {form.processing ? 'Mengirim...' : seconds > 0 ? `Kirim ulang dalam ${seconds} detik` : 'Kirim tautan verifikasi'}
                    </button>
                </form>
                <Link href={route('profile.edit')} className="block text-center text-sm font-semibold text-teal-700">Periksa atau ubah email di profil</Link>
                <button type="button" onClick={() => setShowLogout(true)} className="w-full text-center text-sm font-semibold text-slate-500">Keluar dari akun</button>
            </div>
            <Modal show={showLogout} maxWidth="sm" closeable={!logout.processing} onClose={() => setShowLogout(false)}>
                <div className="p-6">
                    <DialogTitle className="text-lg font-extrabold text-slate-900">Keluar dari akun?</DialogTitle>
                    <p className="mt-2 text-sm leading-6 text-slate-500">Apakah Anda yakin ingin keluar? Kamu dapat melanjutkan verifikasi setelah masuk kembali.</p>
                    <div className="mt-6 flex justify-end gap-3">
                        <button type="button" className="rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600" disabled={logout.processing} onClick={() => setShowLogout(false)}>Batal</button>
                        <button type="button" className="button-primary" disabled={logout.processing} onClick={() => logout.post(route('logout'))}>{logout.processing ? 'Keluar...' : 'Ya, keluar'}</button>
                    </div>
                </div>
            </Modal>
        </GuestLayout>
    );
}
