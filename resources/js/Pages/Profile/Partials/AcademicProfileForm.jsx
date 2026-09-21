import Icon from '@/Components/Icon';
import { Field, Notice, requestError } from '@/Pages/Auth/Partials/AuthFields';
import { router, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function AcademicProfileForm({ siswa }) {
    const user = usePage().props.auth.user;
    const form = useForm({ nis: siswa?.nis ?? '', nisn: siswa?.nisn ?? '', kelas_siswa: siswa?.kelas_siswa ?? '', username: user.username ?? '' });
    const [busy, setBusy] = useState(false);
    const [saved, setSaved] = useState(false);
    const inFlight = useRef(false);
    const isVerified = Boolean(user.email_verified_at);

    async function submit(event) {
        event.preventDefault();
        if (inFlight.current) return;
        inFlight.current = true;
        setBusy(true);
        setSaved(false);
        form.clearErrors();
        const payload = { nama_lengkap: user.name, nis: form.data.nis, nisn: form.data.nisn || null, kelas_siswa: form.data.kelas_siswa.trim() || null };
        if (form.data.username.trim()) payload.username = form.data.username.trim();
        try {
            await window.axios.patch('/api/v1/profil/siswa', payload);
            setSaved(true);
            router.reload({ only: ['auth', 'siswa'] });
        } catch (error) {
            requestError(error, form);
        } finally {
            inFlight.current = false;
            setBusy(false);
        }
    }

    return (
        <section>
            <div className="flex items-center gap-3">
                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-teal-700"><Icon name="book" /></span>
                <div><h2 className="text-base font-extrabold text-slate-900">Identitas siswa</h2><p className="mt-1 text-xs text-slate-500">Lengkapi data sekolah sebelum bergabung ke kelas mapel.</p></div>
            </div>
            {!siswa?.nis && <p className="mt-4 rounded-xl bg-teal-50 p-4 text-sm leading-6 text-teal-800">Selamat datang! Isi NIS dan ketik kelasmu. Setelah itu, gunakan kode kelas dari guru untuk bergabung ke mata pelajaran.</p>}
            {!isVerified && <p className="mt-4 text-sm text-amber-800">Verifikasi email di bagian informasi akun terlebih dahulu untuk mengubah identitas siswa.</p>}
            <form onSubmit={submit} className="mt-5 space-y-4" aria-busy={busy}>
                <fieldset disabled={busy || !isVerified} className="space-y-4">
                    <Field label="NIS" field="nis" form={form} maxLength={20} hint="Gunakan nomor induk siswa yang diberikan sekolah." />
                    <Field label="NISN (opsional)" field="nisn" form={form} required={false} inputMode="numeric" pattern="[0-9]{10}" maxLength={10} hint="Nomor induk siswa nasional terdiri dari 10 digit." />
                    <Field label="Username (opsional)" field="username" form={form} required={false} minLength={3} maxLength={100} pattern="[A-Za-z0-9_-]+" autoComplete="nickname" hint="Huruf, angka, garis bawah, atau tanda hubung; minimal 3 karakter." />
                    <Field label="Kelas siswa" field="kelas_siswa" form={form} required={false} maxLength={255} placeholder="Contoh: X PPLG 1" hint="Ketik nama kelasmu sesuai data sekolah." />
                </fieldset>
                <p className="text-xs leading-5 text-slate-500">Kelas siswa adalah data profil. Untuk masuk ruang mata pelajaran, tetap diperlukan kode kelas dan persetujuan guru.</p>
                <Notice error>{form.errors.request || form.errors.nama_lengkap}</Notice>
                <button type="submit" disabled={busy || !isVerified} className="button-primary">{busy ? 'Menyimpan...' : 'Simpan identitas siswa'}</button>
                <Notice>{saved && 'Identitas siswa berhasil disimpan.'}</Notice>
            </form>
        </section>
    );
}
