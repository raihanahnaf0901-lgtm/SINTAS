import InputError from '@/Components/InputError';
import { Field, Notice, requestError, useResendCooldown } from '@/Pages/Auth/Partials/AuthFields';
import { Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function AccountAuthForm({ registering = false, initialRole = 'siswa' }) {
    const form = useForm({ role: initialRole, name: '', nip: '', gelar: '', jenis_guru: 'guru_mapel', email: '', password: '', password_confirmation: '', code: '', remember: false });
    const [codeSent, setCodeSent] = useState(false);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const [expiresMinutes, setExpiresMinutes] = useState(null);
    const [seconds, startCooldown] = useResendCooldown();
    const inFlight = useRef(false);
    const processing = busy || form.processing;

    async function requestCode() {
        if (!registering || inFlight.current || processing || seconds > 0) return;
        inFlight.current = true;
        setBusy(true);
        form.clearErrors();
        setMessage('');
        try {
            const endpoint = `${form.data.role}-registration-code.store`;
            const response = await window.axios.post(route(endpoint), {
                name: form.data.name, email: form.data.email.trim().toLowerCase(), password: form.data.password,
                password_confirmation: form.data.password_confirmation,
                ...(registering && form.data.role === 'guru' ? { nip: form.data.nip.trim(), gelar: form.data.gelar.trim(), jenis_guru: form.data.jenis_guru } : {}),
            });
            form.setData('code', '');
            setCodeSent(true);
            setExpiresMinutes(response.data.expires_in_minutes);
            setMessage(response.data.message);
            startCooldown(response.data.resend_after_seconds ?? 15);
        } catch (error) {
            requestError(error, form);
        } finally {
            inFlight.current = false;
            setBusy(false);
        }
    }

    async function submit(event) {
        event.preventDefault();
        if (inFlight.current || processing) return;
        if (!registering) {
            form.post(route('login'), { onFinish: () => form.reset('password') });
            return;
        }
        if (!codeSent) {
            await requestCode();
            return;
        }
        inFlight.current = true;
        setBusy(true);
        form.clearErrors();
        try {
            const endpoint = `${form.data.role}-registration.verify`;
            const response = await window.axios.post(route(endpoint), {
                email: form.data.email.trim().toLowerCase(), code: form.data.code, remember: form.data.remember,
            });
            form.reset('password', 'password_confirmation', 'code');
            window.location.assign(response.data.needs_profile ? route('profile.edit') : response.data.redirect);
        } catch (error) {
            requestError(error, form);
        } finally {
            inFlight.current = false;
            setBusy(false);
        }
    }

    function editCredentials() {
        setCodeSent(false);
        form.setData('code', '');
        form.clearErrors();
        setMessage('');
    }

    return (
        <form onSubmit={submit} className="space-y-4" aria-busy={processing}>
            {(
                <fieldset disabled={processing || codeSent}>
                    <legend className="mb-2 text-sm font-semibold text-slate-700">{registering ? 'Daftar sebagai' : 'Masuk sebagai'}</legend>
                    <div className="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1">
                        {[['siswa', 'Siswa'], ['guru', 'Guru']].map(([value, label]) => (
                            <label key={value} className={`cursor-pointer rounded-lg px-3 py-2.5 text-center text-sm font-bold transition ${form.data.role === value ? 'bg-white text-teal-800 shadow-sm' : 'text-slate-500'}`}>
                                <input className="sr-only peer" type="radio" name="role" value={value} checked={form.data.role === value}
                                    onChange={() => { form.setData('role', value); form.clearErrors(); setMessage(''); }} />
                                <span className="peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-teal-600">{label}</span>
                            </label>
                        ))}
                    </div>
                </fieldset>
            )}
            {registering && <Field label="Nama lengkap" field="name" form={form} disabled={codeSent || processing} autoComplete="name" maxLength={255} />}
            {registering && form.data.role === 'guru' && <fieldset disabled={codeSent || processing} className="space-y-4">
                <legend className="sr-only">Data guru</legend>
                <Field label="NIP" field="nip" form={form} maxLength={30} hint="Nomor induk pegawai. NIP hanya dapat digunakan oleh satu akun guru." />
                <Field label="Gelar (opsional)" field="gelar" form={form} required={false} maxLength={50} placeholder="Contoh: S.Pd." />
                <label className="block"><span className="mb-1.5 block text-sm font-semibold text-slate-700">Jenis guru</span>
                    <select name="jenis_guru" className="input" required value={form.data.jenis_guru} onChange={(event) => form.setData('jenis_guru', event.target.value)}>
                        <option value="guru_mapel">Guru mata pelajaran</option><option value="guru_piket">Guru piket</option>
                    </select><InputError message={form.errors.jenis_guru} className="mt-1.5" />
                </label>
                <p className="text-xs leading-5 text-slate-500">Guru mata pelajaran dapat membuat kelas dan menilai. Guru piket memantau kelas yang memberi izin akses.</p>
            </fieldset>}
            <Field label="Email" field="email" type="email" form={form} disabled={codeSent || processing} autoComplete="username" maxLength={255} placeholder="nama@gmail.com" />
            {!codeSent && <Field label={registering ? 'Buat password SINTAS' : 'Password SINTAS'} field="password" type="password" form={form}
                key={`${registering}-${form.data.role}`}
                disabled={processing} minLength={registering ? 8 : undefined} autoComplete={registering ? 'new-password' : 'current-password'}
                hint={registering ? 'Minimal 8 karakter. Buat password khusus untuk akun SINTAS.' : undefined} />}
            {registering && !codeSent && <Field label="Ulangi password" field="password_confirmation" type="password" form={form} disabled={processing} minLength={8} autoComplete="new-password" />}
            {codeSent && (
                <div className="space-y-3 border-t border-slate-100 pt-4">
                    <p className="text-sm leading-6 text-slate-500">Buka kotak masuk atau folder spam pada emailmu. {expiresMinutes ? `Kode berlaku selama ${expiresMinutes} menit.` : ''}</p>
                    <Field label="Kode verifikasi (6 digit)" field="code" form={form} disabled={processing} inputMode="numeric" pattern="[0-9]{6}" maxLength={6} autoComplete="one-time-code" autoFocus />
                </div>
            )}
            {!registering && !codeSent && <label className="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" checked={form.data.remember} disabled={processing} onChange={(event) => form.setData('remember', event.target.checked)} className="rounded border-slate-300 text-teal-700 focus:ring-teal-600" />
                Ingat saya di perangkat ini
            </label>}
            {form.data.role === 'guru' && !registering && <p className="text-xs leading-5 text-slate-500">Gunakan akun guru yang sudah terverifikasi. Belum memiliki akun? Pilih daftar akun guru di bawah.</p>}
            <Notice>{message}</Notice>
            <Notice error>{form.errors.request}</Notice>
            <InputError message={form.errors.role} />
            <button className="button-primary w-full" disabled={processing || (registering && !codeSent && seconds > 0)} type="submit">
                {processing ? 'Memproses...' : !registering ? 'Masuk ke SINTAS' : codeSent ? 'Verifikasi dan masuk' : seconds > 0 ? `Kirim lagi dalam ${seconds} detik` : 'Kirim kode verifikasi'}
            </button>
            {codeSent && <div className="flex flex-wrap justify-between gap-3 text-sm font-semibold text-teal-700">
                <button type="button" disabled={processing || seconds > 0} className="disabled:cursor-not-allowed disabled:text-slate-400" onClick={requestCode}>
                    {seconds > 0 ? `Kirim ulang (${seconds} dtk)` : 'Kirim ulang kode'}
                </button>
                <button type="button" disabled={processing} onClick={editCredentials}>Ubah data</button>
            </div>}
            <div className="flex flex-wrap justify-between gap-3 border-t border-slate-100 pt-4 text-sm">
                <Link href={registering ? route('login') : route('register', { role: form.data.role })} className="font-semibold text-teal-700 hover:underline">
                    {registering ? 'Sudah punya akun? Masuk' : `Daftar akun ${form.data.role}`}
                </Link>
                {!registering && <Link className="text-slate-500 hover:text-teal-700 hover:underline" href={route('password.request')}>Lupa password?</Link>}
            </div>
        </form>
    );
}
