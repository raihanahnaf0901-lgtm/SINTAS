import { Button, EmptyState, Field, LoadingState, Notice, Pager, formatDate, useApiData, useApiForm } from '@/Components/AcademicUI';
import { useState } from 'react';

function RecordChoice({ label, path, selected, onChange, error, members = false }) {
    const [page, setPage] = useState(1);
    const result = useApiData(`${path}?page=${page}`);
    const records = (result.data?.data?.data ?? []).filter((record) => !members || record.status === 'diterima');
    const options = records.map((record) => members ? { id: record.siswa_id, label: `${record.siswa.nama_lengkap} · ${record.siswa.nis ?? 'NIS belum diisi'}` } : { id: record.id, label: record.judul });
    if (selected && !options.some((option) => String(option.id) === String(selected.id))) options.unshift(selected);

    return <div className="space-y-2">
        <Field label={label} error={error}>
            <select className="input" required value={selected?.id ?? ''} onChange={(event) => onChange(options.find((option) => String(option.id) === event.target.value) ?? null)} disabled={result.loading || Boolean(result.error)}>
                <option value="">{result.loading ? 'Memuat pilihan...' : `Pilih ${label.toLowerCase()}`}</option>
                {options.map((option) => <option key={option.id} value={option.id}>{option.label}</option>)}
            </select>
        </Field>
        {result.error && <div className="space-y-2"><Notice message={result.error} /><Button variant="secondary" onClick={result.reload}>Coba lagi</Button></div>}
        {!result.loading && !result.error && records.length === 0 && <p className="text-xs text-slate-500">{members ? 'Tidak ada siswa berstatus diterima pada halaman ini.' : 'Belum ada aktivitas pada halaman ini.'}</p>}
        <Pager meta={result.data?.data} onPage={setPage} />
    </div>;
}

function GradeEditor({ base, grade, onCancel, onSaved }) {
    const [kind, setKind] = useState(grade?.ujian_id ? 'ujian' : 'tugas');
    const [student, setStudent] = useState(grade ? { id: grade.siswa_id, label: grade.siswa?.nama_lengkap ?? 'Siswa' } : null);
    const [activity, setActivity] = useState(grade ? { id: grade.tugas_id ?? grade.ujian_id, label: grade.tugas?.judul ?? grade.ujian?.judul ?? 'Aktivitas' } : null);
    const form = useApiForm({ nilai: grade?.nilai ?? '', catatan: grade?.catatan ?? '' });

    async function save(event) {
        event.preventDefault();
        if (!student || !activity) return;
        const result = await form.submit('put', `${base}/penilaian`, {
            siswa_id: student.id, [`${kind}_id`]: activity.id,
            nilai: form.data.nilai, catatan: form.data.catatan,
        });
        if (result) onSaved();
    }

    return <form onSubmit={save} className="surface space-y-5 p-5 sm:p-6">
        <div><h3 className="text-base font-bold text-slate-900">{grade ? 'Edit nilai' : 'Tambahkan nilai'}</h3><p className="mt-1 text-sm text-slate-500">Nilai untuk satu tugas atau ujian. Siswa akan mendapat pemberitahuan setelah nilai disimpan.</p>{!grade && <p className="mt-2 text-xs leading-6 text-slate-500">Jika siswa sudah memiliki nilai untuk aktivitas yang dipilih, nilai tersebut akan diperbarui.</p>}</div>
        <Notice message={form.errors._general} />
        <fieldset disabled={form.processing} className="space-y-5 disabled:opacity-60">
            {grade ? <div className="rounded-xl bg-slate-50 p-4 text-sm"><p className="font-bold">{student.label}</p><p className="mt-1 text-slate-500">{kind === 'tugas' ? 'Tugas' : 'Ujian'} · {activity.label}</p></div> : <>
                <RecordChoice label="Siswa" path={`${base}/anggota`} members selected={student} onChange={setStudent} error={form.errors.siswa_id} />
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Jenis penilaian"><select className="input" value={kind} onChange={(event) => { setKind(event.target.value); setActivity(null); }}><option value="tugas">Tugas</option><option value="ujian">Ujian</option></select></Field>
                    <RecordChoice key={kind} label={kind === 'tugas' ? 'Tugas' : 'Ujian'} path={`${base}/${kind}`} selected={activity} onChange={setActivity} error={form.errors[`${kind}_id`]} />
                </div>
            </>}
            {grade && <Notice message={form.errors.siswa_id || form.errors.tugas_id || form.errors.ujian_id} />}
            <Field label="Nilai (0–100)" error={form.errors.nilai}><input className="input max-w-xs" type="number" min="0" max="100" step="0.01" required value={form.data.nilai} onChange={(event) => form.setData('nilai', event.target.value)} /></Field>
            <Field label="Catatan untuk siswa (opsional)" error={form.errors.catatan}><textarea className="input" rows="3" maxLength={10000} value={form.data.catatan} onChange={(event) => form.setData('catatan', event.target.value)} /></Field>
            <div className="flex flex-wrap gap-3"><Button type="submit" disabled={form.processing || !student || !activity}>{form.processing ? 'Menyimpan...' : 'Simpan nilai'}</Button><Button variant="secondary" onClick={onCancel}>Batal</Button></div>
        </fieldset>
    </form>;
}

export default function GradesPanel({ kelasMapel, permissions }) {
    const base = `/api/v1/kelas-mapel/${kelasMapel.id}`;
    const [page, setPage] = useState(1);
    const result = useApiData(`${base}/penilaian?page=${page}`);
    const [editor, setEditor] = useState(null);
    const [saved, setSaved] = useState('');
    const canManage = permissions.manageAcademic && kelasMapel.status === 'aktif';
    const grades = result.data?.data?.data ?? [];

    return <div className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-lg font-bold text-slate-900">{permissions.isTeacher ? 'Penilaian siswa' : 'Nilai saya'}</h2><p className="mt-1 text-sm text-slate-500">{permissions.isTeacher ? 'Nilai tugas dan ujian yang sudah diberikan kepada siswa.' : 'Lihat nilai dan masukan dari guru untuk aktivitas belajarmu.'}</p></div>{canManage && !editor && <Button onClick={() => { setEditor({ grade: null }); setSaved(''); }}>Tambah nilai</Button>}</div>
        <Notice tone="success" message={saved} />
        {canManage && editor && <GradeEditor key={editor.grade?.id ?? 'new'} base={base} grade={editor.grade} onCancel={() => setEditor(null)} onSaved={() => { setEditor(null); setSaved('Nilai berhasil disimpan.'); setPage(1); result.reload(); }} />}
        {result.loading ? <LoadingState /> : result.error ? <div className="surface space-y-3 p-5"><Notice message={result.error} /><Button variant="secondary" onClick={result.reload}>Coba lagi</Button></div> : grades.length === 0 ? <EmptyState title="Belum ada nilai" description={permissions.isTeacher ? 'Nilai akan tampil setelah guru menilai tugas atau ujian siswa.' : 'Nilaimu akan muncul di sini setelah guru memberikan penilaian.'} /> : <div className="surface overflow-hidden">
            <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-slate-50 text-xs text-slate-500"><tr>{permissions.isTeacher && <th className="px-5 py-4">Siswa</th>}<th className="px-5 py-4">Aktivitas</th><th className="px-5 py-4">Nilai</th><th className="px-5 py-4">Catatan</th><th className="px-5 py-4">Dinilai</th>{canManage && <th className="px-5 py-4"><span className="sr-only">Tindakan</span></th>}</tr></thead><tbody className="divide-y divide-slate-100">{grades.map((grade) => <tr key={grade.id}>
                {permissions.isTeacher && <td className="px-5 py-4 font-semibold">{grade.siswa?.nama_lengkap ?? 'Siswa'}</td>}
                <td className="px-5 py-4"><p className="font-semibold">{grade.tugas?.judul ?? grade.ujian?.judul ?? 'Aktivitas'}</p><p className="mt-1 text-xs text-slate-500">{grade.tugas_id ? 'Tugas' : 'Ujian'}</p></td>
                <td className="px-5 py-4 text-lg font-bold text-teal-700">{grade.nilai === null ? '—' : Number(grade.nilai).toLocaleString('id-ID', { maximumFractionDigits: 2 })}</td>
                <td className="min-w-48 max-w-sm whitespace-pre-wrap break-words px-5 py-4 text-slate-500">{grade.catatan || '—'}</td><td className="whitespace-nowrap px-5 py-4 text-xs text-slate-500">{formatDate(grade.dinilai_at, true)}</td>
                {canManage && <td className="px-5 py-4"><Button variant="secondary" disabled={Boolean(editor)} onClick={() => { setEditor({ grade }); setSaved(''); }}>Edit</Button></td>}
            </tr>)}</tbody></table></div>
        </div>}
        <Pager meta={result.data?.data} onPage={setPage} />
    </div>;
}
