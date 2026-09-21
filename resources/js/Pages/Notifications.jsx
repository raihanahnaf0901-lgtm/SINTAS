import { Button, EmptyState, LoadingState, Notice, Pager, formatDate, useApiData, useApiForm } from '@/Components/AcademicUI';
import Icon from '@/Components/Icon';
import StudentLayout from '@/Layouts/StudentLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export function notificationDestination(notification) {
    const kelasId = Number(notification.data?.kelas_mapel_id);
    if (!Number.isSafeInteger(kelasId) || kelasId < 1) return null;
    if (notification.tipe === 'keanggotaan_kelas' && notification.data.status !== 'diterima') return route('subjects.index');
    const sections = { tugas: 'tugas', ujian: 'ujian', jadwal: 'jadwal', penilaian: 'nilai', rekap: 'rekap', permintaan_kelas: 'anggota' };
    return route('subjects.section', { subject: kelasId, section: sections[notification.tipe] ?? 'tugas' });
}

function NotificationItem({ item, onRead }) {
    const form = useApiForm({});
    const destination = notificationDestination(item);

    async function markRead() {
        if (await form.submit('patch', `/api/v1/notifikasi/${item.id}/baca`)) onRead();
    }

    return <article className={`surface flex flex-col gap-4 p-5 sm:p-6 ${!item.read_at ? 'border-teal-200 bg-teal-50/40' : ''}`}>
        <div className="flex items-start gap-3">
            <span className="rounded-xl bg-teal-100 p-3 text-teal-800"><Icon name={item.tipe === 'tugas' ? 'clipboard' : 'bell'} /></span>
            <div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><h2 className="font-bold text-slate-900">{item.judul}</h2>{!item.read_at && <span className="rounded-full bg-teal-100 px-2 py-1 text-xs font-bold text-teal-800">Baru</span>}</div>
                <p className="mt-2 whitespace-pre-wrap break-words text-sm text-slate-600">{item.pesan}</p>
                <p className="mt-2 text-xs text-slate-500">{formatDate(item.created_at, true)}</p>
            </div>
        </div>
        <Notice message={form.errors._general} />
        <div className="flex flex-wrap items-center gap-3">
            {destination && <Link href={destination} className="button-primary">{item.tipe === 'tugas' ? 'Lihat tugas' : 'Buka kelas'}</Link>}
            {!item.read_at && <Button variant="secondary" disabled={form.processing} onClick={markRead}>{form.processing ? 'Menyimpan...' : 'Tandai dibaca'}</Button>}
        </div>
    </article>;
}

export default function Notifications() {
    const [page, setPage] = useState(1);
    const { data, loading, error, reload } = useApiData(`/api/v1/notifikasi?page=${page}`);
    const records = data?.data;

    useEffect(() => {
        const timer = window.setInterval(() => { if (!document.hidden) reload(); }, 30000);
        return () => window.clearInterval(timer);
    }, [reload]);

    function refreshReadStatus() {
        reload();
        router.reload({ only: ['unreadNotifications'] });
    }

    return <StudentLayout active="notifications" title="Notifikasi">
        <Head title="Notifikasi" />
        <div className="mb-6 flex flex-wrap items-start justify-between gap-4"><div><h1 className="text-3xl font-extrabold text-slate-900">Notifikasi</h1><p className="mt-2 text-sm text-slate-500">Informasi tugas terbaru dan kegiatan kelasmu.{data && ` ${data.belum_dibaca} belum dibaca.`}</p></div><Button variant="secondary" disabled={loading} onClick={reload}>Muat ulang</Button></div>
        {loading ? <LoadingState /> : error ? <Notice message={error} /> : !records?.data?.length ? <EmptyState title="Belum ada notifikasi" description="Notifikasi akan muncul ketika guru mengirim tugas di kelas yang sudah kamu ikuti." /> : <div className="flex flex-col gap-4">
            {records.data.map((item) => <NotificationItem key={item.id} item={item} onRead={refreshReadStatus} />)}
            <Pager meta={records} onPage={setPage} />
        </div>}
    </StudentLayout>;
}
