import Icon from '@/Components/Icon';
import GuestLayout from '@/Layouts/GuestLayout';
import { Field, Notice, requestError, useResendCooldown } from './Partials/AuthFields';
import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function ForgotPassword() {
    const form = useForm({ email: '', code: '', password: '', password_confirmation: '' });
    const [step, setStep] = useState('email');
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const [seconds, startCooldown] = useResendCooldown();
    const inFlight = useRef(false);

    async function sendCode() {
        if (inFlight.current || seconds > 0) return;
        inFlight.current = true;
        setBusy(true);
        form.clearErrors();
        setMessage('');
        try {
            const response = await window.axios.post(route('password.otp.request'), { email: form.data.email.trim().toLowerCase() });
            form.setData('code', '');
            setMessage(response.data.message);
            setStep('code');
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
        if (inFlight.current) return;
        if (step === 'email') {
            await sendCode();
            return;
        }
        inFlight.current = true;
        setBusy(true);
        form.clearErrors();
        setMessage('');
        try {
            const response = await window.axios.post(route('password.otp.reset'), { ...form.data, email: form.data.email.trim().toLowerCase() });
            form.reset('code', 'password', 'password_confirmation');
            setMessage(response.data.message);
            setStep('complete');
        } catch (error) {
            requestError(error, form);
        } finally {
            inFlight.current = false;
            setBusy(false);
        }
    }

    return (
        <GuestLayout>
            <Head title="Lupa password" />
            <span className="mb-5 inline-flex rounded-xl bg-teal-50 p-3 text-teal-700"><Icon name={step === 'complete' ? 'check' : 'lock'} className="h-6 w-6" /></span>
            <h1 className="text-2xl font-extrabold text-slate-900">{step === 'complete' ? 'Password berhasil diubah' : 'Pulihkan akunmu'}</h1>
            <p className="mb-6 mt-2 text-sm leading-6 text-slate-500">{step === 'complete'
                ? 'Gunakan password baru saat masuk kembali ke SINTAS.'
                : 'Masukkan email akun SINTAS. Kami akan mengirim kode untuk membuat password baru.'}</p>
            {step === 'complete' ? <div className="space-y-5"><Notice>{message}</Notice><Link href={route('login')} className="button-primary w-full">Kembali ke halaman masuk</Link></div> : (
                <form onSubmit={submit} className="space-y-4" aria-busy={busy}>
                    <Field label="Email akun" field="email" type="email" form={form} disabled={busy || step === 'code'} autoComplete="username" maxLength={255} autoFocus />
                    {step === 'code' && <>
                        <Field label="Kode verifikasi (6 digit)" field="code" form={form} disabled={busy} inputMode="numeric" pattern="[0-9]{6}" maxLength={6} autoComplete="one-time-code" autoFocus />
                        <Field label="Password baru" field="password" type="password" form={form} disabled={busy} minLength={8} autoComplete="new-password" hint="Minimal 8 karakter, khusus untuk akun SINTAS." />
                        <Field label="Ulangi password baru" field="password_confirmation" type="password" form={form} disabled={busy} minLength={8} autoComplete="new-password" />
                    </>}
                    <Notice>{message}</Notice>
                    <Notice error>{form.errors.request}</Notice>
                    <button type="submit" className="button-primary w-full" disabled={busy || (step === 'email' && seconds > 0)}>
                        {busy ? 'Memproses...' : step === 'code' ? 'Simpan password baru' : seconds > 0 ? `Kirim lagi dalam ${seconds} detik` : 'Kirim kode pemulihan'}
                    </button>
                    {step === 'code' && <div className="flex flex-wrap justify-between gap-3 text-sm font-semibold text-teal-700">
                        <button type="button" disabled={busy || seconds > 0} className="disabled:text-slate-400" onClick={sendCode}>{seconds > 0 ? `Kirim ulang (${seconds} dtk)` : 'Kirim ulang kode'}</button>
                        <button type="button" disabled={busy} onClick={() => { setStep('email'); setMessage(''); form.clearErrors(); form.reset('code', 'password', 'password_confirmation'); }}>Ubah email</button>
                    </div>}
                    <Link href={route('login')} className="block text-center text-sm font-semibold text-slate-500 hover:text-teal-700">Kembali ke halaman masuk</Link>
                </form>
            )}
        </GuestLayout>
    );
}
