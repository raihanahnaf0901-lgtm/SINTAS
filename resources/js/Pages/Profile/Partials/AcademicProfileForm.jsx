import Icon from '@/Components/Icon';
import InputError from '@/Components/InputError';
import { Field, Notice, requestError } from '@/Pages/Auth/Partials/AuthFields';
import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export default function AcademicProfileForm({ siswa }) {
    const user = usePage().props.auth.user;
    const form = useForm({ nis: siswa?.nis ?? '', nisn: siswa?.nisn ?? '', kelas_id: String(siswa?.kelas_id ?? ''), username: user.username ?? '' });
    const [classes, setClasses] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState('');
    const [loadAttempt, setLoadAttempt] = useState(0);
    const [busy, setBusy] = useState(false);
    const [saved, setSaved] = useState(false);
    const inFlight = useRef(false);
    const isVerified = Boolean(user.email_verified_at);

    useEffect(() => {
        if (!isVerified) {
            setLoading(false);
            return;
        }
        const controller = new AbortController();
        setLoading(true);
        setLoadError('');
        window.axios.get('/api/v1/master-data', { signal: controller.signal }).then(({ data }) => {
            setClasses(data.kelas ?? []);
        }).catch((error) => {
            if (!controller.signal.aborted) setLoadError(error.response?.status === 401 || error.response?.status === 419
                ? 'Sesi berakhir. Muat ulang halaman untuk melanjutkan.'
                : 'Daftar kelas belum dapat dimuat. Coba lagi.');
        }).finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [loadAttempt, isVerified]);

    async function submit(event) {
        event.preventDefault();
        if (inFlight.current) return;
        inFlight.current = true;
        setBusy(true);
        setSaved(false);
        form.clearErrors();
        const payload = { nama_lengkap: user.name, nis: form.data.nis, nisn: form.data.nisn || null };
        if (form.data.username.trim()) payload.username = form.data.username.trim();
        if (String(siswa?.kelas_id ?? '') !== form.data.kelas_id) payload.kelas_id = form.data.kelas_id ? Number(form.data.kelas_id) : null;
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

    const currentClassMissing = siswa?.kelas_id && !classes.some((item) => item.id === siswa.kelas_id);
    return (
        <section>
            <div className="flex items-center gap-3">
                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-teal-700"><Icon name="book" /></span>
                <div><h2 className="text-base font-extrabold text-slate-900">Identitas siswa</h2><p className="mt-1 text-xs text-slate-500">Lengkapi data sekolah sebelum bergabung ke kelas mapel.</p></div>
            </div>
            {!siswa?.nis && <p className="mt-4 rounded-xl bg-teal-50 p-4 text-sm leading-6 text-teal-800">Selamat datang! Isi NIS dan kelas sekolahmu. Setelah itu, gunakan kode kelas dari guru untuk bergabung ke mata pelajaran.</p>}
            {!isVerified && <p className="mt-4 text-sm text-amber-800">Verifikasi email di bagian informasi akun terlebih dahulu untuk mengubah identitas siswa.</p>}
            <form onSubmit={submit} className="mt-5 space-y-4" aria-busy={busy}>
                <fieldset disabled={busy || !isVerified} className="space-y-4">
                    <Field label="NIS" field="nis" form={form} maxLength={20} hint="Gunakan nomor induk siswa yang diberikan sekolah." />
                    <Field label="NISN (opsional)" field="nisn" form={form} required={false} inputMode="numeric" pattern="[0-9]{10}" maxLength={10} hint="Nomor induk siswa nasional terdiri dari 10 digit." />
                    <Field label="Username (opsional)" field="username" form={form} required={false} minLength={3} maxLength={100} pattern="[A-Za-z0-9_-]+" autoComplete="nickname" hint="Huruf, angka, garis bawah, atau tanda hubung; minimal 3 karakter." />
                    <label className="block">
                        <span className="mb-1.5 block text-sm font-semibold text-slate-700">Kelas sekolah</span>
                        <select className="input" name="kelas_id" value={form.data.kelas_id} disabled={loading || Boolean(loadError)}
                            aria-invalid={Boolean(form.errors.kelas_id)} onChange={(event) => form.setData('kelas_id', event.target.value)}>
                            <option value="">{loading ? 'Memuat daftar kelas...' : 'Belum memilih kelas'}</option>
                            {currentClassMissing && <option value={siswa.kelas_id}>{siswa.kelas?.nama_kelas ?? 'Kelas saat ini'} (nonaktif)</option>}
                            {classes.map((item) => <option value={item.id} key={item.id}>{item.nama_kelas}{item.tahun_ajaran ? ` - ${item.tahun_ajaran}` : ''}</option>)}
                        </select>
                        <InputError message={form.errors.kelas_id} className="mt-1.5" />
                    </label>
                </fieldset>
                {loadError && <div role="alert" className="rounded-xl bg-amber-50 p-3 text-sm text-amber-800">{loadError} <button type="button" className="font-bold underline" onClick={() => setLoadAttempt((value) => value + 1)}>Coba lagi</button></div>}
                {!loading && !loadError && isVerified && classes.length === 0 && <p className="text-xs leading-5 text-slate-500">Belum ada kelas sekolah yang tersedia. Kamu tetap dapat menyimpan NIS dan melengkapi kelas setelah dibuat oleh guru.</p>}
                <p className="text-xs leading-5 text-slate-500">Pilihan kelas sekolah adalah data profil. Untuk masuk ruang mata pelajaran, tetap diperlukan kode kelas dan persetujuan guru.</p>
                <Notice error>{form.errors.request || form.errors.nama_lengkap}</Notice>
                <button type="submit" disabled={busy || !isVerified} className="button-primary">{busy ? 'Menyimpan...' : 'Simpan identitas siswa'}</button>
                <Notice>{saved && 'Identitas siswa berhasil disimpan.'}</Notice>
            </form>
        </section>
    );
}
