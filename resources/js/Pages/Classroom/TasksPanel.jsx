import { useState } from 'react';
import Icon from '@/Components/Icon';
import { Button, EmptyState, Field, LoadingState, Notice, Pager, formatDate, toDateTimeInput, useApiData, useApiForm } from '@/Components/AcademicUI';

const taskNames = { tugas: 'Tugas', pr: 'Pekerjaan rumah', proyek: 'Proyek' };
const submissionNames = { belum: 'Belum dikumpulkan', dikumpulkan: 'Dikumpulkan', terlambat: 'Dikumpulkan terlambat' };

function DownloadLink({ submission }) {
    if (!submission?.has_file) return null;
    return <a href={`/api/v1/pengumpulan/${submission.id}/file`} className="inline-flex self-start items-center gap-2 text-sm font-bold text-teal-700 hover:underline" download><Icon name="clipboard" className="h-4 w-4" />Unduh berkas</a>;
}

function TaskForm({ item, path, onSaved, onCancel }) {
    const form = useApiForm({ judul: item?.judul || '', jenis: item?.jenis || 'tugas', deskripsi: item?.deskripsi || '', deadline: toDateTimeInput(item?.deadline) });

    async function save(event) {
        event.preventDefault();
        const payload = { ...form.data, deadline: new Date(form.data.deadline).toISOString() };
        const result = await form.submit(item ? 'patch' : 'post', item ? `${path}/${item.id}` : path, payload);
        if (result) onSaved();
    }

    return (
        <form onSubmit={save} className="surface flex flex-col gap-5 p-5 sm:p-6">
            <h3 className="font-bold text-slate-900">{item ? 'Edit tugas' : 'Buat tugas'}</h3>
            <Notice message={form.errors._general} />
            <fieldset disabled={form.processing} className="flex flex-col gap-4">
                <Field label="Judul tugas" error={form.errors.judul}><input className="input" required maxLength={255} value={form.data.judul} onChange={(event) => form.setData('judul', event.target.value)} placeholder="Contoh: Latihan persamaan linear" /></Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Jenis tugas" error={form.errors.jenis}><select className="input" value={form.data.jenis} onChange={(event) => form.setData('jenis', event.target.value)}>{Object.entries(taskNames).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></Field>
                    <Field label="Batas pengumpulan (waktu lokal)" error={form.errors.deadline}><input className="input" type="datetime-local" required value={form.data.deadline} onChange={(event) => form.setData('deadline', event.target.value)} /></Field>
                </div>
                <Field label="Deskripsi dan petunjuk (opsional)" error={form.errors.deskripsi}><textarea className="input min-h-[130px]" rows={4} maxLength={20000} value={form.data.deskripsi} onChange={(event) => form.setData('deskripsi', event.target.value)} placeholder="Jelaskan pekerjaan yang harus diselesaikan siswa." /></Field>
            </fieldset>
            <div className="flex flex-wrap gap-3"><Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan tugas'}</Button><Button type="button" variant="secondary" disabled={form.processing} onClick={onCancel}>Batal</Button></div>
        </form>
    );
}

function SubmissionForm({ task, path, submission, onSaved }) {
    const form = useApiForm({ catatan_siswa: submission?.catatan_siswa || '', file: null });
    const [localError, setLocalError] = useState('');

    async function send(event) {
        event.preventDefault();
        setLocalError('');
        if (!form.data.file && !form.data.catatan_siswa.trim()) {
            setLocalError('Tambahkan berkas atau isi jawaban/catatan sebelum mengumpulkan.');
            return;
        }
        if (form.data.file?.size > 10 * 1024 * 1024) {
            setLocalError('Ukuran berkas maksimal 10 MB.');
            return;
        }
        const payload = new FormData();
        if (form.data.file) payload.append('file', form.data.file);
        payload.append('catatan_siswa', form.data.catatan_siswa);
        const result = await form.submit('post', `${path}/${task.id}/pengumpulan`, payload);
        if (result) onSaved();
    }

    return (
        <form onSubmit={send} className="flex flex-col gap-4 rounded-xl border border-teal-100 bg-teal-50/40 p-4 sm:p-5">
            <div><h4 className="text-sm font-bold text-slate-900">{submission ? 'Perbarui pengumpulan' : 'Kumpulkan tugas'}</h4><p className="mt-1 text-xs leading-5 text-slate-500">Kirim berkas, jawaban tertulis, atau keduanya. Pengumpulan setelah tenggat akan ditandai terlambat.</p></div>
            <Notice message={localError || form.errors._general} />
            <fieldset disabled={form.processing} className="flex flex-col gap-4">
                <Field label="Berkas tugas" error={form.errors.file}><input className="input bg-white file:mr-3 file:rounded-lg file:border-0 file:bg-teal-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-teal-800" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip" onChange={(event) => { setLocalError(''); form.setData('file', event.target.files?.[0] || null); }} /></Field>
                <p className="text-xs leading-5 text-slate-500">PDF, Word, JPG, PNG, atau ZIP. Maksimal 10 MB.{submission?.has_file ? ' Berkas sebelumnya tetap tersimpan jika tidak diganti.' : ''}</p>
                <Field label="Jawaban atau catatan" error={form.errors.catatan_siswa}><textarea className="input bg-white" rows={4} maxLength={10000} value={form.data.catatan_siswa} onChange={(event) => { setLocalError(''); form.setData('catatan_siswa', event.target.value); }} placeholder="Tulis jawaban atau catatan untuk guru." /></Field>
            </fieldset>
            <Button type="submit" className="self-start" disabled={form.processing}>{form.processing ? 'Mengirim...' : submission ? 'Kirim ulang tugas' : 'Kumpulkan tugas'}</Button>
        </form>
    );
}

function SubmissionList({ path, task }) {
    const [page, setPage] = useState(1);
    const { data, loading, error, reload } = useApiData(`${path}/${task.id}/pengumpulan?page=${page}`);
    const records = data?.data;
    const items = records?.data || [];

    return (
        <div className="flex flex-col gap-4 border-t border-slate-100 pt-5">
            <h4 className="text-sm font-bold text-slate-900">Pengumpulan siswa{records ? ` (${records.total})` : ''}</h4>
            {loading ? <LoadingState /> : error ? <div className="flex flex-col items-start gap-3"><Notice message={error} /><Button type="button" variant="secondary" onClick={reload}>Coba lagi</Button></div> : !items.length ? <p className="rounded-xl bg-slate-50 p-5 text-sm text-slate-500">Belum ada siswa yang mengumpulkan tugas ini.</p> : (
                <>
                    <div className="flex flex-col gap-3">
                        {items.map((item) => (
                            <div key={item.id} className="flex flex-col gap-3 rounded-xl border border-slate-200 p-4">
                                <div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-sm font-bold text-slate-900">{item.siswa?.nama_lengkap || 'Siswa'}</p><p className="mt-1 text-xs text-slate-500">NIS/NISN: {item.siswa?.nis || '-'}</p></div><span className={`rounded-full px-3 py-1 text-xs font-semibold ${item.status === 'terlambat' ? 'bg-amber-50 text-amber-800' : 'bg-teal-50 text-teal-700'}`}>{submissionNames[item.status] || item.status}</span></div>
                                <p className="text-xs text-slate-500">Dikirim: {formatDate(item.submitted_at, true)}</p>
                                {item.catatan_siswa && <p className="whitespace-pre-wrap break-words text-sm leading-6 text-slate-600">{item.catatan_siswa}</p>}
                                <DownloadLink submission={item} />
                            </div>
                        ))}
                    </div>
                    <Pager meta={records} onPage={setPage} />
                </>
            )}
        </div>
    );
}

function TaskCard({ task, path, permissions, canManage, canSubmit, editorOpen, onEdit, onSubmitted }) {
    const [expanded, setExpanded] = useState(false);
    const submission = task.pengumpulan?.[0];
    const grade = task.penilaian?.[0];
    const late = new Date(task.deadline).getTime() < Date.now();
    const studentStatus = grade ? 'Sudah dinilai' : submission ? (submissionNames[submission.status] || submission.status) : late ? 'Lewat tenggat' : 'Belum dikumpulkan';

    return (
        <article className="surface flex flex-col gap-4 p-5 sm:p-6">
            <div className="flex items-start gap-4">
                <span className={`rounded-xl p-3 ${grade ? 'bg-teal-50 text-teal-700' : 'bg-amber-50 text-amber-700'}`}><Icon name={grade ? 'check' : 'clipboard'} /></span>
                <div className="flex min-w-0 flex-1 flex-col gap-2">
                    <div className="flex flex-wrap items-center justify-between gap-2"><span className="text-xs font-bold uppercase tracking-wide text-slate-500">{taskNames[task.jenis] || task.jenis}</span>{!permissions.isTeacher && <span className={`rounded-full px-3 py-1 text-xs font-semibold ${grade || submission?.status === 'dikumpulkan' ? 'bg-teal-50 text-teal-700' : 'bg-amber-50 text-amber-800'}`}>{studentStatus}</span>}</div>
                    <h3 className="break-words text-base font-bold text-slate-900">{task.judul}</h3>
                    <p className="text-sm text-slate-500">Tenggat: {formatDate(task.deadline, true)}</p>
                </div>
            </div>
            {task.deskripsi && <p className="whitespace-pre-wrap break-words text-sm leading-6 text-slate-600">{task.deskripsi}</p>}
            {grade && !permissions.isTeacher && <div className="rounded-xl bg-teal-50 p-4"><p className="text-sm text-teal-800">Nilai kamu: <span className="text-lg font-bold">{Number(grade.nilai).toLocaleString('id-ID', { maximumFractionDigits: 2 })}</span></p>{grade.catatan && <p className="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-teal-900">{grade.catatan}</p>}</div>}
            <div className="flex flex-wrap items-center gap-4">
                <button type="button" className="text-sm font-bold text-teal-700 hover:underline" aria-expanded={expanded} aria-controls={`submission-${task.id}`} onClick={() => setExpanded((value) => !value)}>{expanded ? 'Tutup pengumpulan' : permissions.isTeacher ? 'Lihat pengumpulan siswa' : submission || grade ? 'Lihat pengumpulan' : canSubmit ? 'Kumpulkan tugas' : 'Lihat detail pengumpulan'}</button>
                {canManage && !editorOpen && <button type="button" className="text-sm font-semibold text-slate-500 hover:text-teal-700" onClick={() => onEdit(task)}>Edit tugas</button>}
            </div>
            {expanded && <div id={`submission-${task.id}`} className="flex flex-col gap-4">
                {permissions.isTeacher ? <SubmissionList path={path} task={task} /> : (
                    <>
                        {submission && <div className="flex flex-col gap-3 rounded-xl bg-slate-50 p-4"><p className="text-sm font-bold text-slate-900">Pengumpulan terakhir</p><p className="text-xs text-slate-500">Dikirim: {formatDate(submission.submitted_at, true)}</p>{submission.catatan_siswa && <p className="whitespace-pre-wrap break-words text-sm leading-6 text-slate-600">{submission.catatan_siswa}</p>}<DownloadLink submission={submission} /></div>}
                        {grade ? <p className="text-sm text-slate-500">Tugas yang sudah dinilai tidak dapat dikumpulkan ulang.</p> : canSubmit ? <SubmissionForm key={`${task.id}-${submission?.updated_at || 'new'}`} task={task} path={path} submission={submission} onSaved={onSubmitted} /> : <p className="text-sm text-slate-500">Pengumpulan tidak tersedia untuk kelas ini.</p>}
                    </>
                )}
            </div>}
        </article>
    );
}

export default function TasksPanel({ kelasMapel, permissions }) {
    const [page, setPage] = useState(1);
    const path = `/api/v1/kelas-mapel/${kelasMapel.id}/tugas`;
    const { data, loading, error, reload } = useApiData(`${path}?page=${page}`);
    const [editor, setEditor] = useState(null);
    const [success, setSuccess] = useState('');
    const records = data?.data;
    const items = records?.data || [];
    const canManage = permissions.manageAcademic && kelasMapel.status === 'aktif';
    const canSubmit = permissions.submit && kelasMapel.status === 'aktif';

    function saved(message) {
        setEditor(null);
        setSuccess(message);
        reload();
    }

    return (
        <section className="flex flex-col gap-5" aria-label="Tugas">
            <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-lg font-bold text-slate-900">Tugas</h2><p className="mt-1 text-sm text-slate-500">{permissions.isTeacher ? 'Kelola tugas dan pantau hasil pekerjaan siswa.' : 'Lihat petunjuk, kumpulkan pekerjaan, dan periksa nilaimu.'}</p></div>{canManage && !editor && <Button type="button" onClick={() => { setSuccess(''); setEditor({}); }}>Buat tugas</Button>}</div>
            <Notice message={success} tone="success" />
            {canManage && editor && <TaskForm key={editor.id || 'new'} item={editor.id ? editor : null} path={path} onSaved={() => saved('Tugas berhasil disimpan.')} onCancel={() => setEditor(null)} />}
            {loading ? <LoadingState /> : error ? <div className="surface flex flex-col items-start gap-3 p-5"><Notice message={error} /><Button type="button" variant="secondary" onClick={reload}>Coba lagi</Button></div> : !items.length ? <EmptyState title="Belum ada tugas" description="Tugas, PR, dan proyek akan muncul setelah ditambahkan guru." /> : (
                <div className="flex flex-col gap-4">
                    {items.map((task) => <TaskCard key={task.id} task={task} path={path} permissions={permissions} canManage={canManage} canSubmit={canSubmit} editorOpen={Boolean(editor)} onEdit={(item) => { setSuccess(''); setEditor(item); }} onSubmitted={() => saved('Tugas berhasil dikumpulkan.')} />)}
                    <Pager meta={records} onPage={setPage} />
                </div>
            )}
        </section>
    );
}
