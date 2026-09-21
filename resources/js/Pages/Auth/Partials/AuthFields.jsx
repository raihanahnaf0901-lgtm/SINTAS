import InputError from '@/Components/InputError';
import Icon from '@/Components/Icon';
import { useEffect, useId, useState } from 'react';

export function Field({ label, field, form, type = 'text', hint, required = true, revealable = true, ...props }) {
    const id = useId();
    const [passwordVisible, setPasswordVisible] = useState(false);
    const canReveal = revealable && type === 'password';
    const error = form.errors[field];
    return (
        <div>
            <label htmlFor={id} className="mb-1.5 block text-sm font-semibold text-slate-700">{label}</label>
            <div className="relative">
            <input {...props} id={id} type={canReveal && passwordVisible ? 'text' : type} name={field} className={`input${canReveal ? ' pr-14' : ''}`} required={required}
                aria-invalid={Boolean(error)} aria-describedby={error ? `${id}-error` : hint ? `${id}-hint` : undefined}
                value={form.data[field] ?? ''} onChange={(event) => {
                    form.setData(field, event.target.value);
                    form.clearErrors(field);
                }} />
            {canReveal && <button type="button" disabled={props.disabled} aria-controls={id}
                aria-label="Tampilkan password" aria-pressed={passwordVisible}
                title={passwordVisible ? 'Sembunyikan password' : 'Tampilkan password'}
                onClick={() => setPasswordVisible((visible) => !visible)}
                className="absolute inset-y-1 right-1 rounded-lg px-3 text-sm font-semibold text-teal-700 hover:bg-teal-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-teal-600 disabled:cursor-not-allowed disabled:opacity-50">
                <Icon name={passwordVisible ? 'eyeOff' : 'eye'} />
            </button>}
            </div>
            {hint && <p id={`${id}-hint`} className="mt-1.5 text-xs leading-5 text-slate-500">{hint}</p>}
            <InputError id={`${id}-error`} message={error} className="mt-1.5" />
        </div>
    );
}

export function Notice({ children, error = false }) {
    if (!children) return null;
    return <div role={error ? 'alert' : 'status'} className={`rounded-xl px-4 py-3 text-sm leading-6 ${error ? 'bg-red-50 text-red-700' : 'bg-teal-50 text-teal-800'}`}>{children}</div>;
}

export function requestError(error, form) {
    const errors = error.response?.data?.errors;
    if (errors) {
        form.setError(Object.fromEntries(Object.entries(errors).map(([field, value]) => [field, Array.isArray(value) ? value[0] : value])));
        return;
    }
    form.setError('request', error.response?.status === 429
        ? 'Terlalu banyak percobaan. Tunggu beberapa menit lalu coba lagi.'
        : error.response?.status === 419 || error.response?.status === 401
            ? 'Sesi berakhir. Muat ulang halaman untuk melanjutkan.'
            : error.response?.status === 403
                ? 'Akunmu belum dapat mengakses fitur ini. Pastikan email sudah terverifikasi.'
                : 'Permintaan belum berhasil. Periksa koneksi dan coba lagi.');
}

export function useResendCooldown() {
    const [seconds, setSeconds] = useState(0);
    useEffect(() => {
        if (seconds <= 0) return;
        const timer = window.setTimeout(() => setSeconds((value) => Math.max(0, value - 1)), 1000);
        return () => window.clearTimeout(timer);
    }, [seconds]);
    return [seconds, (delay) => setSeconds(typeof delay === 'number' && Number.isFinite(delay) ? Math.max(1, delay) : 60)];
}
