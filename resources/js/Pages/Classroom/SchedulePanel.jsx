import { useState } from 'react';
import Icon from '@/Components/Icon';
import { Button, EmptyState, Field, LoadingState, Notice, useApiData, useApiForm } from '@/Components/AcademicUI';

const days = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];
const dayLabel = (day) => day.charAt(0).toUpperCase() + day.slice(1);
const shortTime = (value) => value?.slice(0, 5) || '-';

function ScheduleForm({ item, path, onSaved, onCancel }) {
    const form = useApiForm({ hari: item?.hari || 'senin', jam_mulai: item?.jam_mulai?.slice(0, 5) || '', jam_selesai: item?.jam_selesai?.slice(0, 5) || '', ruangan: item?.ruangan || '' });

    async function save(event) {
        event.preventDefault();
        const response = await form.submit(item ? 'patch' : 'post', item ? `${path}/${item.id}` : path);
        if (response) onSaved();
    }

    return (
        <form onSubmit={save} className="surface flex flex-col gap-5 p-5 sm:p-6">
            <h3 className="font-bold text-slate-900">{item ? 'Edit jadwal' : 'Buat jadwal pelajaran'}</h3>
            <Notice message={form.errors._general} />
            <fieldset disabled={form.processing} className="grid gap-4 sm:grid-cols-2">
                <Field label="Hari" error={form.errors.hari}>
                    <select className="input" value={form.data.hari} onChange={(event) => form.setData('hari', event.target.value)} required>
                        {days.map((day) => <option key={day} value={day}>{dayLabel(day)}</option>)}
                    </select>
                </Field>
                <Field label="Ruangan (opsional)" error={form.errors.ruangan}>
                    <input className="input" maxLength={50} value={form.data.ruangan} onChange={(event) => form.setData('ruangan', event.target.value)} placeholder="Contoh: Laboratorium IPA" />
                </Field>
                <Field label="Jam mulai" error={form.errors.jam_mulai}>
                    <input className="input" type="time" required value={form.data.jam_mulai} onChange={(event) => form.setData('jam_mulai', event.target.value)} />
                </Field>
                <Field label="Jam selesai" error={form.errors.jam_selesai}>
                    <input className="input" type="time" required min={form.data.jam_mulai || undefined} value={form.data.jam_selesai} onChange={(event) => form.setData('jam_selesai', event.target.value)} />
                </Field>
            </fieldset>
            <div className="flex flex-wrap gap-3">
                <Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan jadwal'}</Button>
                <Button type="button" variant="secondary" onClick={onCancel} disabled={form.processing}>Batal</Button>
            </div>
        </form>
    );
}

export default function SchedulePanel({ kelasMapel, permissions }) {
    const path = `/api/v1/kelas-mapel/${kelasMapel.id}/jadwal`;
    const { data, loading, error, reload } = useApiData(path);
    const [editor, setEditor] = useState(null);
    const [success, setSuccess] = useState('');
    const items = Array.isArray(data?.data) ? [...data.data].sort((a, b) => days.indexOf(a.hari) - days.indexOf(b.hari) || a.jam_mulai.localeCompare(b.jam_mulai)) : [];
    const canManage = permissions.manageAcademic && kelasMapel.status === 'aktif';

    function saved() {
        setEditor(null);
        setSuccess('Jadwal pelajaran berhasil disimpan.');
        reload();
    }

    return (
        <section className="flex flex-col gap-5" aria-label="Jadwal pelajaran">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div><h2 className="text-lg font-bold text-slate-900">Jadwal pelajaran</h2><p className="mt-1 text-sm text-slate-500">Waktu belajar rutin untuk ruang mata pelajaran ini.</p></div>
                {canManage && !editor && <Button type="button" onClick={() => { setSuccess(''); setEditor({}); }}>Buat jadwal</Button>}
            </div>
            <Notice message={success} tone="success" />
            {canManage && editor && <ScheduleForm key={editor.id || 'new'} item={editor.id ? editor : null} path={path} onSaved={saved} onCancel={() => setEditor(null)} />}
            {loading ? <LoadingState /> : error ? <div className="surface flex flex-col items-start gap-3 p-5"><Notice message={error} /><Button type="button" variant="secondary" onClick={reload}>Coba lagi</Button></div> : !items.length ? <EmptyState title="Belum ada jadwal" description="Jadwal akan muncul setelah guru menambahkannya." /> : (
                <div className="grid gap-4 md:grid-cols-2">
                    {items.map((item) => (
                        <article key={item.id} className="surface flex items-start gap-4 p-5">
                            <span className="rounded-xl bg-teal-50 p-3 text-teal-700"><Icon name="calendar" /></span>
                            <div className="flex min-w-0 flex-1 flex-col gap-2">
                                <p className="text-xs font-bold uppercase tracking-wider text-teal-700">{dayLabel(item.hari)}</p>
                                <h3 className="text-lg font-bold text-slate-900">{shortTime(item.jam_mulai)} - {shortTime(item.jam_selesai)}</h3>
                                <p className="break-words text-sm text-slate-500">{item.ruangan || 'Ruangan belum ditentukan'}</p>
                                {canManage && !editor && <button type="button" className="self-start text-sm font-bold text-teal-700 hover:underline" onClick={() => { setSuccess(''); setEditor(item); }}>Edit jadwal {dayLabel(item.hari)}</button>}
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}
