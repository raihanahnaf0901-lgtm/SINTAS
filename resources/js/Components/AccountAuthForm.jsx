import InputError from '@/Components/InputError';
import { Field, Notice, requestError, useResendCooldown } from '@/Pages/Auth/Partials/AuthFields';
import { Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function AccountAuthForm({ registering = false }) {
    const form = useForm({ role: 'siswa', name: '', email: '', password: '', password_confirmation: '', code: '', remember: false });
    const [codeSent, setCodeSent] = useState(false);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const [expiresMinutes, setExpiresMinutes] = useState(null);
    const [seconds, startCooldown] = useResendCooldown();
    const inFlight = useRef(false);
    const processing = busy || form.processing;

    async function requestCode() {
        if (inFlight.current || processing || seconds > 0) return;
        inFlight.current = true;
        setBusy(true);
        form.clearErrors();
        setMessage('');
        try {
            const response = await window.axios.post(route(registering ? 'siswa-registration-code.store' : 'siswa-login-code.store'), {
                name: form.data.name, email: form.data.email.trim().toLowerCase(), password: form.data.password,
                password_confirmation: form.data.password_confirmation,
            });
            form.setData('code', '');
            setCodeSent(true);
            setExpiresMinutes(response.data.expires_in_minutes);
            setMessage(response.data.message);
            startCooldown();
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
        if (!registering && form.data.role === 'guru') {
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
            const response = await window.axios.post(route(registering ? 'siswa-registration.verify' : 'siswa-login.verify'), {
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
            {!registering && (
                <fieldset disabled={processing || codeSent}>
                    <legend className="mb-2 text-sm font-semibold text-slate-700">Masuk sebagai</legend>
                    <div className="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1">
                        {[['siswa', 'Siswa'], ['guru', 'Guru']].map(([value, label]) => (
                            <label key={value} className={`cursor-pointer rounded-lg px-3 py-2.5 text-center text-sm font-bold transition ${form.data.role === value ? 'bg-white text-teal-800 shadow-sm' : 'text-slate-500'}`}>
                                <input className="sr-only peer" type="radio" name="role" value={value} checked={form.data.role === value}
                                    onChange={() => { form.setData('role', value); form.clearErrors(); }} />
                                <span className="peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-teal-600">{label}</span>
                            </label>
                        ))}
                    </div>
                </fieldset>
            )}
            {registering && <Field label="Nama lengkap" field="name" form={form} disabled={codeSent || processing} autoComplete="name" maxLength={255} />}
            <Field label="Email" field="email" type="email" form={form} disabled={codeSent || processing} autoComplete="username" maxLength={255} placeholder="nama@gmail.com" />
            {!codeSent && <Field label={registering ? 'Buat password SINTAS' : 'Password SINTAS'} field="password" type="password" form={form}
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
            {form.data.role === 'guru' && !registering && <p className="text-xs leading-5 text-slate-500">Gunakan akun guru yang sudah didaftarkan oleh pengelola sekolah.</p>}
            <Notice>{message}</Notice>
            <Notice error>{form.errors.request}</Notice>
            <InputError message={form.errors.role} />
            <button className="button-primary w-full" disabled={processing || (!codeSent && form.data.role === 'siswa' && seconds > 0)} type="submit">
                {processing ? 'Memproses...' : codeSent ? 'Verifikasi dan masuk' : form.data.role === 'guru' && !registering ? 'Masuk ke ruang guru' : seconds > 0 ? `Kirim lagi dalam ${seconds} detik` : 'Kirim kode verifikasi'}
            </button>
            {codeSent && <div className="flex flex-wrap justify-between gap-3 text-sm font-semibold text-teal-700">
                <button type="button" disabled={processing || seconds > 0} className="disabled:cursor-not-allowed disabled:text-slate-400" onClick={requestCode}>
                    {seconds > 0 ? `Kirim ulang (${seconds} dtk)` : 'Kirim ulang kode'}
                </button>
                <button type="button" disabled={processing} onClick={editCredentials}>Ubah data</button>
            </div>}
            <div className="flex flex-wrap justify-between gap-3 border-t border-slate-100 pt-4 text-sm">
                <Link href={route(registering ? 'login' : 'register')} className="font-semibold text-teal-700 hover:underline">
                    {registering ? 'Sudah punya akun? Masuk' : 'Daftar akun siswa'}
                </Link>
                {!registering && <Link className="text-slate-500 hover:text-teal-700 hover:underline" href={route('password.request')}>Lupa password?</Link>}
            </div>
        </form>
    );
}
