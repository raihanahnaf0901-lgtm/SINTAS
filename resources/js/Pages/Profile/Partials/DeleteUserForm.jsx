import Modal from '@/Components/Modal';
import { Field } from '@/Pages/Auth/Partials/AuthFields';
import { DialogTitle } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function DeleteUserForm({ className = '' }) {
    const [show, setShow] = useState(false);
    const form = useForm({ password: '' });

    function close() {
        if (form.processing) return;
        setShow(false);
        form.reset();
        form.clearErrors();
    }

    function submit(event) {
        event.preventDefault();
        form.delete(route('profile.destroy'), { preserveScroll: true, onSuccess: () => setShow(false), onFinish: () => form.reset() });
    }

    return (
        <section className={className}>
            <h2 className="text-base font-extrabold text-slate-900">Hapus akun</h2>
            <p className="mt-2 text-sm leading-6 text-slate-500">Menghapus akun juga menghapus data yang terhubung dengan akunmu secara permanen. Simpan informasi yang masih diperlukan terlebih dahulu.</p>
            <button type="button" onClick={() => setShow(true)} className="mt-4 rounded-xl border border-red-200 px-4 py-2.5 text-sm font-bold text-red-600 hover:bg-red-50">Hapus akun saya</button>
            <Modal show={show} maxWidth="md" closeable={!form.processing} onClose={close}>
                <form onSubmit={submit} className="space-y-5 p-6">
                    <div><DialogTitle className="text-lg font-extrabold text-slate-900">Hapus akun secara permanen?</DialogTitle><p className="mt-2 text-sm leading-6 text-slate-500">Tindakan ini tidak dapat dibatalkan. Masukkan password akun SINTAS untuk mengonfirmasi.</p></div>
                    <Field label="Password SINTAS" field="password" type="password" form={form} disabled={form.processing} autoComplete="current-password" autoFocus />
                    <div className="flex flex-wrap justify-end gap-3">
                        <button type="button" disabled={form.processing} onClick={close} className="rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600">Batal</button>
                        <button type="submit" disabled={form.processing} className="rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50">{form.processing ? 'Menghapus...' : 'Ya, hapus akun'}</button>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
