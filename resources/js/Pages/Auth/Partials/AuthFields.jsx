import InputError from '@/Components/InputError';
import { useEffect, useId, useState } from 'react';

export function Field({ label, field, form, type = 'text', hint, required = true, ...props }) {
    const id = useId();
    const error = form.errors[field];
    return (
        <div>
            <label htmlFor={id} className="mb-1.5 block text-sm font-semibold text-slate-700">{label}</label>
            <input {...props} id={id} type={type} name={field} className="input" required={required}
                aria-invalid={Boolean(error)} aria-describedby={error ? `${id}-error` : hint ? `${id}-hint` : undefined}
                value={form.data[field] ?? ''} onChange={(event) => {
                    form.setData(field, event.target.value);
                    form.clearErrors(field);
                }} />
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
    return [seconds, () => setSeconds(60)];
}
