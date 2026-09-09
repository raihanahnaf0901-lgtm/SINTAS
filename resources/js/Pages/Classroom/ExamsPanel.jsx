import { useState } from 'react';
import Icon from '@/Components/Icon';
import { Button, EmptyState, Field, LoadingState, Notice, Pager, formatDate, toDateTimeInput, useApiData, useApiForm } from '@/Components/AcademicUI';

const examNames = { UH: 'Ulangan harian', US: 'Ujian semester' };

function ExamForm({ item, path, onSaved, onCancel }) {
    const form = useApiForm({ judul: item?.judul || '', jenis_ujian: item?.jenis_ujian || 'UH', tanggal: item?.tanggal ? toDateTimeInput(item.tanggal).slice(0, 10) : '', keterangan: item?.keterangan || '' });

    async function save(event) {
        event.preventDefault();
        const response = await form.submit(item ? 'patch' : 'post', item ? `${path}/${item.id}` : path);
        if (response) onSaved();
    }

    return (
        <form onSubmit={save} className="surface flex flex-col gap-5 p-5 sm:p-6">
            <h3 className="font-bold text-slate-900">{item ? 'Edit ujian' : 'Buat ujian'}</h3>
            <Notice message={form.errors._general} />
            <fieldset disabled={form.processing} className="flex flex-col gap-4">
                <Field label="Judul ujian" error={form.errors.judul}><input className="input" value={form.data.judul} onChange={(event) => form.setData('judul', event.target.value)} required maxLength={255} placeholder="Contoh: Ulangan harian bab 1" /></Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Jenis ujian" error={form.errors.jenis_ujian}><select className="input" value={form.data.jenis_ujian} onChange={(event) => form.setData('jenis_ujian', event.target.value)}>{Object.entries(examNames).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></Field>
                    <Field label="Tanggal ujian" error={form.errors.tanggal}><input className="input" type="date" required value={form.data.tanggal} onChange={(event) => form.setData('tanggal', event.target.value)} /></Field>
                </div>
                <Field label="Keterangan (opsional)" error={form.errors.keterangan}><textarea className="input min-h-[130px]" rows={4} maxLength={20000} value={form.data.keterangan} onChange={(event) => form.setData('keterangan', event.target.value)} placeholder="Materi, persiapan, atau petunjuk pelaksanaan ujian." /></Field>
            </fieldset>
            <div className="flex flex-wrap gap-3"><Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan ujian'}</Button><Button type="button" variant="secondary" disabled={form.processing} onClick={onCancel}>Batal</Button></div>
        </form>
    );
}

export default function ExamsPanel({ kelasMapel, permissions }) {
    const [page, setPage] = useState(1);
    const path = `/api/v1/kelas-mapel/${kelasMapel.id}/ujian`;
    const { data, loading, error, reload } = useApiData(`${path}?page=${page}`);
    const [editor, setEditor] = useState(null);
    const [success, setSuccess] = useState('');
    const records = data?.data;
    const items = records?.data || [];
    const canManage = permissions.manageAcademic && kelasMapel.status === 'aktif';

    function saved() {
        setEditor(null);
        setSuccess('Ujian berhasil disimpan.');
        reload();
    }

    return (
        <section className="flex flex-col gap-5" aria-label="Ujian">
            <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-lg font-bold text-slate-900">Ujian</h2><p className="mt-1 text-sm text-slate-500">Jadwal, petunjuk, dan hasil ulangan harian atau ujian semester.</p></div>{canManage && !editor && <Button type="button" onClick={() => { setSuccess(''); setEditor({}); }}>Buat ujian</Button>}</div>
            <Notice message={success} tone="success" />
            {canManage && editor && <ExamForm key={editor.id || 'new'} item={editor.id ? editor : null} path={path} onSaved={saved} onCancel={() => setEditor(null)} />}
            {loading ? <LoadingState /> : error ? <div className="surface flex flex-col items-start gap-3 p-5"><Notice message={error} /><Button type="button" variant="secondary" onClick={reload}>Coba lagi</Button></div> : !items.length ? <EmptyState title="Belum ada ujian" description="Ujian yang dijadwalkan guru akan tampil di sini." /> : (
                <div className="flex flex-col gap-4">
                    {items.map((item) => {
                        const grade = item.penilaian?.[0];
                        return (
                            <article key={item.id} className="surface flex flex-col gap-4 p-5 sm:p-6">
                                <div className="flex items-start gap-4">
                                    <span className="rounded-xl bg-indigo-50 p-3 text-indigo-700"><Icon name="clipboard" /></span>
                                    <div className="flex min-w-0 flex-1 flex-col gap-2"><span className="text-xs font-bold uppercase tracking-wide text-indigo-700">{examNames[item.jenis_ujian] || item.jenis_ujian}</span><h3 className="break-words text-base font-bold text-slate-900">{item.judul}</h3><p className="text-sm text-slate-500">{formatDate(item.tanggal)}</p></div>
                                </div>
                                {item.keterangan && <p className="whitespace-pre-wrap break-words text-sm leading-6 text-slate-600">{item.keterangan}</p>}
                                {!permissions.isTeacher && <div className="rounded-xl bg-slate-50 p-4"><p className="text-sm text-slate-600">Nilai kamu: <span className="font-bold text-teal-800">{grade ? Number(grade.nilai).toLocaleString('id-ID', { maximumFractionDigits: 2 }) : 'Belum dinilai'}</span></p>{grade?.catatan && <p className="mt-2 whitespace-pre-wrap break-words text-sm text-slate-500">Catatan guru: {grade.catatan}</p>}</div>}
                                {canManage && !editor && <button type="button" className="self-start text-sm font-bold text-teal-700 hover:underline" onClick={() => { setSuccess(''); setEditor(item); }}>Edit ujian</button>}
                            </article>
                        );
                    })}
                    <Pager meta={records} onPage={setPage} />
                </div>
            )}
        </section>
    );
}
