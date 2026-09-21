import { useState } from 'react';
import Icon from '@/Components/Icon';
import { GradeEditor, GradeSummary } from './GradesPanel';
import { Button, EmptyState, Field, LoadingState, Notice, Pager, formatDate, toDateTimeInput, useApiData, useApiForm } from '@/Components/AcademicUI';

const examNames = { UH: 'Ulangan harian', US: 'Ujian semester' };

export function ExamForm({ item, path, onSaved, onCancel }) {
    const form = useApiForm({ judul: item?.judul || '', jenis_ujian: item?.jenis_ujian || 'UH', tanggal: item?.tanggal ? toDateTimeInput(item.tanggal).slice(0, 10) : '', keterangan: item?.keterangan || '', google_form_url: item?.google_form_url || '' });

    async function save(event) {
        event.preventDefault();
        const response = await form.submit(item ? 'patch' : 'post', item ? `${path}/${item.id}` : path, { ...form.data, google_form_url: form.data.google_form_url.trim() || null });
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
                <Field label="Link Google Form (opsional)" error={form.errors.google_form_url}><input className="input" type="url" name="google_form_url" maxLength={2048} value={form.data.google_form_url} onChange={(event) => form.setData('google_form_url', event.target.value)} placeholder="https://forms.gle/..." aria-describedby="exam-google-form-help" /></Field>
                <p id="exam-google-form-help" className="text-xs leading-5 text-slate-500">Tempel link untuk responden dari Google Form (forms.gle atau docs.google.com/forms), bukan link edit. Pastikan siswa mendapat akses untuk mengisi soal.</p>
                <Field label="Keterangan (opsional)" error={form.errors.keterangan}><textarea className="input min-h-[130px]" rows={4} maxLength={20000} value={form.data.keterangan} onChange={(event) => form.setData('keterangan', event.target.value)} placeholder="Materi, persiapan, atau petunjuk pelaksanaan ujian." /></Field>
            </fieldset>
            <div className="flex flex-wrap gap-3"><Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan ujian'}</Button><Button type="button" variant="secondary" disabled={form.processing} onClick={onCancel}>Batal</Button></div>
        </form>
    );
}

export function ExamFormLink({ url }) {
    if (!url) return null;
    let formUrl;
    try { formUrl = new URL(url); } catch { return null; }
    const isResponderLink = formUrl.hostname === 'forms.gle' ? /^\/[A-Za-z0-9_-]+\/?$/.test(formUrl.pathname)
        : formUrl.hostname === 'docs.google.com' && /^\/forms\/(?:u\/\d+\/)?d\/(?:e\/)?[A-Za-z0-9_-]+\/viewform\/?$/.test(formUrl.pathname);
    if (formUrl.protocol !== 'https:' || formUrl.username || formUrl.password || formUrl.port || !isResponderLink) return null;

    return <a href={formUrl.href} target="_blank" rel="noopener noreferrer" className="inline-flex self-start items-center gap-2 rounded-xl bg-indigo-50 px-4 py-3 text-sm font-bold text-indigo-700 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Buka soal di Google Form<span className="sr-only"> (tab baru)</span></a>;
}

export function ExamStudentAssessment({ student, exam, base, canManage, editing, editorOpen, onEdit, onCancel, onSaved }) {
    return <div className="flex flex-col gap-3 rounded-xl border border-slate-200 p-4">
        <div><p className="break-words text-sm font-bold text-slate-900">{student.nama_lengkap}</p><p className="mt-1 text-xs text-slate-500">NIS: {student.nis || 'Belum diisi'}</p></div>
        {canManage && editing ? <GradeEditor
            base={base} grade={student.penilaian}
            target={{ kind: 'ujian', student: { id: student.id, label: student.nama_lengkap }, activity: { id: exam.id, label: exam.judul } }}
            onCancel={onCancel} onSaved={onSaved}
        /> : <>
            <GradeSummary grade={student.penilaian} componentLabel={(examNames[exam.jenis_ujian] || 'ujian').toLowerCase()} />
            {canManage && <Button className="self-start" disabled={editorOpen} onClick={onEdit}>{student.penilaian ? 'Edit nilai' : 'Beri nilai'}</Button>}
        </>}
    </div>;
}

function ExamGradeList({ exam, path }) {
    const [page, setPage] = useState(1);
    const [editingId, setEditingId] = useState(null);
    const [success, setSuccess] = useState('');
    const { data, loading, error, reload } = useApiData(`${path}/${exam.id}/penilaian?page=${page}`);
    const records = data?.data;
    const students = records?.data || [];

    return <div className="flex flex-col gap-4 border-t border-slate-100 pt-5">
        <div><h4 className="text-sm font-bold text-slate-900">Penilaian ujian siswa{records ? ` (${records.total})` : ''}</h4><p className="mt-2 text-xs leading-5 text-slate-500">Periksa respons di Google Form dan pastikan siswa sudah menyelesaikan ujian sebelum mengisi nilainya. Nilai dan status pengerjaan tidak diambil otomatis dari Google Form.</p></div>
        <Notice message={success} tone="success" />
        {loading ? <LoadingState /> : error ? <div className="flex flex-col items-start gap-3"><Notice message={error} /><Button variant="secondary" onClick={reload}>Coba lagi</Button></div> : !students.length ? <p className="rounded-xl bg-slate-50 p-5 text-sm text-slate-500">Belum ada siswa yang diterima di kelas ini.</p> : <>
            {students.map((student) => <ExamStudentAssessment key={student.id} student={student} exam={exam} base={path.replace(/\/ujian$/, '')} canManage editing={editingId === student.id} editorOpen={editingId !== null}
                onEdit={() => { setSuccess(''); setEditingId(student.id); }} onCancel={() => setEditingId(null)}
                onSaved={() => { setEditingId(null); setSuccess('Nilai ujian berhasil disimpan. Siswa dapat melihat nilai dan rekap kelas menggunakan nilai terbaru.'); reload(); }}
            />)}
            <Pager meta={records} onPage={(nextPage) => { setEditingId(null); setSuccess(''); setPage(nextPage); }} />
        </>}
    </div>;
}

export function ExamCard({ item, path, permissions, canManage, editorOpen, onEdit }) {
    const [expanded, setExpanded] = useState(false);
    const grade = item.penilaian?.[0];

    return <article className="surface flex flex-col gap-4 p-5 sm:p-6">
        <div className="flex items-start gap-4">
            <span className="rounded-xl bg-indigo-50 p-3 text-indigo-700"><Icon name="clipboard" /></span>
            <div className="flex min-w-0 flex-1 flex-col gap-2"><span className="text-xs font-bold uppercase tracking-wide text-indigo-700">{examNames[item.jenis_ujian] || item.jenis_ujian}</span><h3 className="break-words text-base font-bold text-slate-900">{item.judul}</h3><p className="text-sm text-slate-500">{formatDate(item.tanggal)}</p></div>
        </div>
        {item.keterangan && <p className="whitespace-pre-wrap break-words text-sm leading-6 text-slate-600">{item.keterangan}</p>}
        <ExamFormLink url={item.google_form_url} />
        {!permissions.isTeacher && <>
            {item.google_form_url && <p className="text-xs leading-5 text-slate-500">Kerjakan dan kirim jawaban di Google Form. Nilai dimasukkan guru setelah jawaban diperiksa.</p>}
            <GradeSummary grade={grade} componentLabel={(examNames[item.jenis_ujian] || 'ujian').toLowerCase()} />
        </>}
        {canManage && <div className="flex flex-wrap items-center gap-4">
            <button type="button" className="text-sm font-bold text-teal-700 hover:underline" aria-expanded={expanded} aria-controls={`exam-grades-${item.id}`} onClick={() => setExpanded((value) => !value)}>{expanded ? 'Tutup penilaian' : 'Penilaian ujian'}</button>
            {!editorOpen && <button type="button" className="text-sm font-semibold text-slate-500 hover:text-teal-700" onClick={() => onEdit(item)}>Edit ujian</button>}
        </div>}
        {canManage && expanded && <div id={`exam-grades-${item.id}`}><ExamGradeList exam={item} path={path} /></div>}
    </article>;
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
                    {items.map((item) => <ExamCard key={item.id} item={item} path={path} permissions={permissions} canManage={canManage} editorOpen={Boolean(editor)} onEdit={(exam) => { setSuccess(''); setEditor(exam); }} />)}
                    <Pager meta={records} onPage={setPage} />
                </div>
            )}
        </section>
    );
}
