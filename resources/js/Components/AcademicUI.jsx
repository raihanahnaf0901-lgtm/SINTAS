import Icon from '@/Components/Icon';
import { useCallback, useEffect, useRef, useState } from 'react';

export async function api(method, path, payload) {
    const response = await window.axios.request({ method, url: path, data: payload, headers: { Accept: 'application/json' } });
    return response.data;
}

export function errorMessage(error) {
    const status = error.response?.status;
    if (status === 401) return 'Sesi berakhir. Silakan masuk kembali.';
    if (status === 419) return 'Sesi kedaluwarsa. Muat ulang halaman sebelum mencoba lagi.';
    if (status === 403) return 'Akses tidak tersedia. Izin atau status kelas mungkin sudah berubah.';
    if (status === 404) return 'Data tidak ditemukan atau sudah tidak tersedia.';
    if (status === 429) return 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.';
    if (status === 413) return 'Ukuran berkas terlalu besar. Pilih berkas yang lebih kecil.';
    return status === 422 ? (error.response.data.message || 'Periksa kembali isian formulir.') : 'Data belum berhasil diproses. Periksa koneksi dan coba lagi.';
}

export function useApiData(path) {
    const [state, setState] = useState({ data: null, loading: Boolean(path), error: '' });
    const [revision, setRevision] = useState(0);
    const reload = useCallback(() => setRevision((value) => value + 1), []);
    useEffect(() => {
        if (!path) { setState({ data: null, loading: false, error: '' }); return; }
        let active = true;
        setState({ data: null, loading: true, error: '' });
        api('get', path).then((data) => {
            if (active) setState({ data, loading: false, error: '' });
        }).catch((error) => {
            if (active) setState({ data: null, loading: false, error: errorMessage(error) });
        });
        return () => { active = false; };
    }, [path, revision]);
    return { ...state, reload };
}

export function useApiForm(initial) {
    const [data, updateData] = useState(initial);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [message, setMessage] = useState('');
    const busy = useRef(false);
    function setData(key, value) {
        updateData((previous) => typeof key === 'function' ? key(previous) : typeof key === 'object' ? key : { ...previous, [key]: value });
        setErrors({});
        setMessage('');
    }
    async function submit(method, path, payload = data) {
        if (busy.current) return null;
        busy.current = true;
        setProcessing(true); setErrors({}); setMessage('');
        try {
            const result = await api(method, path, payload);
            setMessage(result.message || 'Perubahan berhasil disimpan.');
            return result;
        } catch (error) {
            const validation = error.response?.data?.errors ?? {};
            setErrors({ ...Object.fromEntries(Object.entries(validation).map(([key, value]) => [key, Array.isArray(value) ? value[0] : value])), _general: errorMessage(error) });
            return null;
        } finally { busy.current = false; setProcessing(false); }
    }
    return { data, setData, errors, processing, message, submit };
}

export function Button({ children, variant = 'primary', className = '', type = 'button', ...props }) {
    const styles = { primary: 'button-primary', secondary: 'button-secondary', danger: 'inline-flex items-center justify-center gap-2 rounded-xl bg-red-50 px-4 py-3 text-sm font-bold text-red-700 hover:bg-red-100 disabled:opacity-50' };
    return <button type={type} className={`${styles[variant] || styles.primary} ${className}`} {...props}>{children}</button>;
}

export function Field({ label, error, children }) {
    return <label className="block min-w-0"><span className="mb-2 block text-sm font-semibold text-slate-700">{label}</span>{children}{error && <span role="alert" className="mt-1.5 block text-xs text-red-600">{error}</span>}</label>;
}

export function Notice({ message, tone = 'error' }) {
    if (!message) return null;
    return <div role={tone === 'error' ? 'alert' : 'status'} className={`rounded-xl border px-4 py-3 text-sm leading-6 ${tone === 'error' ? 'border-red-100 bg-red-50 text-red-700' : 'border-teal-100 bg-teal-50 text-teal-800'}`}>{message}</div>;
}

export function EmptyState({ title = 'Belum ada data', description, children }) {
    return <div className="surface flex flex-col items-center gap-3 px-6 py-12 text-center"><span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-50 text-teal-700"><Icon name="book" /></span><h3 className="text-base font-bold text-slate-800">{title}</h3>{description && <p className="max-w-md text-sm leading-6 text-slate-500">{description}</p>}{children}</div>;
}

export function LoadingState() {
    return <div role="status" className="surface animate-pulse p-7"><span className="sr-only">Memuat data...</span><div className="h-4 w-1/3 rounded bg-slate-100" /><div className="mt-5 h-20 rounded-xl bg-slate-100" /></div>;
}

export function Pager({ meta, onPage }) {
    if (!meta || meta.last_page <= 1) return null;
    return <nav aria-label="Halaman data" className="flex flex-wrap items-center justify-between gap-3 pt-4"><p className="text-xs text-slate-500">Halaman {meta.current_page} dari {meta.last_page} · {meta.total} data</p><div className="flex gap-2"><Button variant="secondary" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>Sebelumnya</Button><Button variant="secondary" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>Berikutnya</Button></div></nav>;
}

export function formatDate(value, withTime = false) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', ...(withTime ? { timeStyle: 'short' } : {}) }).format(date);
}

export function toDateTimeInput(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (part) => String(part).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function StatusBadge({ status }) {
    const styles = ['aktif', 'diterima', 'dikumpulkan'].includes(status) ? 'bg-teal-50 text-teal-700' : ['ditolak', 'terlambat', 'nonaktif'].includes(status) ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800';
    const labels = { pending: 'Menunggu persetujuan', diterima: 'Diterima', ditolak: 'Ditolak', aktif: 'Aktif', nonaktif: 'Nonaktif', arsip: 'Diarsipkan' };
    return <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${styles}`}>{labels[status] ?? status}</span>;
}
