import { Button, EmptyState, Field, LoadingState, Notice, Pager, useApiData, useApiForm } from '@/Components/AcademicUI';
import { useState } from 'react';

const componentNames = { tugas: 'Tugas', UH: 'Ulangan harian', US: 'Ujian semester' };
const score = (value) => value === null || value === undefined ? 'Belum ada' : Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });

function ConfigEditor({ base, config, onCancel, onSaved }) {
    const form = useApiForm({
        nama_konfigurasi: config?.nama_konfigurasi ?? '',
        status: config?.status ?? 'draft',
        komponen: config?.komponen.map(({ jenis, bobot, metode }) => ({ jenis, bobot, metode })) ?? [
            { jenis: 'tugas', bobot: 40, metode: 'rata_rata' },
            { jenis: 'UH', bobot: 30, metode: 'rata_rata' },
            { jenis: 'US', bobot: 30, metode: 'rata_rata' },
        ],
    });
    const total = form.data.komponen.reduce((sum, part) => sum + Math.round(Number(part.bobot || 0) * 100), 0);
    const validTotal = total === 10000;

    function changeComponent(jenis, field, value) {
        form.setData('komponen', form.data.komponen.map((part) => part.jenis === jenis ? { ...part, [field]: value } : part));
    }

    function toggleComponent(jenis, enabled) {
        form.setData('komponen', enabled ? [...form.data.komponen, { jenis, bobot: 0, metode: 'rata_rata' }] : form.data.komponen.filter((part) => part.jenis !== jenis));
    }

    async function save(event) {
        event.preventDefault();
        if (!validTotal) return;
        const response = await form.submit(config ? 'put' : 'post', config ? `${base}/${config.id}` : base);
        if (response) onSaved(response.data);
    }

    return <form className="surface space-y-5 p-5 sm:p-6" onSubmit={save}>
        <div><h3 className="text-base font-bold text-slate-900">{config ? 'Edit konfigurasi rekap' : 'Buat konfigurasi rekap'}</h3><p className="mt-1 text-sm leading-6 text-slate-500">Atur komponen nilai dan bobotnya. Hanya rekap aktif yang terlihat oleh siswa.</p></div>
        <Notice message={form.errors._general} />
        <fieldset disabled={form.processing} className="space-y-5 disabled:opacity-60">
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Nama konfigurasi" error={form.errors.nama_konfigurasi}><input className="input" required maxLength={255} placeholder="Contoh: Semester 1 2026/2027" value={form.data.nama_konfigurasi} onChange={(event) => form.setData('nama_konfigurasi', event.target.value)} /></Field>
                <Field label="Status" error={form.errors.status}><select className="input" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}><option value="draft">Draft — hanya guru</option><option value="aktif">Aktif — tampil ke siswa</option></select></Field>
            </div>
            {form.data.status === 'aktif' && <p className="rounded-xl bg-teal-50 p-3 text-xs leading-6 text-teal-800">Mengaktifkan konfigurasi ini akan mengubah konfigurasi aktif sebelumnya menjadi draft.</p>}
            <div className="space-y-3">
                {Object.entries(componentNames).map(([jenis, label]) => {
                    const index = form.data.komponen.findIndex((part) => part.jenis === jenis);
                    const part = form.data.komponen[index];
                    return <div key={jenis} className="rounded-xl border border-slate-200 p-4">
                        <label className="flex items-center gap-3 text-sm font-bold"><input type="checkbox" className="rounded border-slate-300 text-teal-700 focus:ring-teal-600" checked={Boolean(part)} onChange={(event) => toggleComponent(jenis, event.target.checked)} />{label}</label>
                        {part && <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <Field label="Bobot (%)" error={form.errors[`komponen.${index}.bobot`]}><input className="input" type="number" min="0" max="100" step="0.01" required value={part.bobot} onChange={(event) => changeComponent(jenis, 'bobot', event.target.value)} /></Field>
                            <Field label="Metode" error={form.errors[`komponen.${index}.metode`]}><select className="input" value={part.metode} onChange={(event) => changeComponent(jenis, 'metode', event.target.value)}><option value="rata_rata">Rata-rata nilai aktivitas</option><option value="manual">Input manual per siswa</option></select></Field>
                            <Notice message={form.errors[`komponen.${index}.jenis`]} />
                        </div>}
                    </div>;
                })}
            </div>
            <div className={`rounded-xl px-4 py-3 text-sm font-bold ${validTotal ? 'bg-teal-50 text-teal-800' : 'bg-amber-50 text-amber-800'}`} role="status">Total bobot: {(total / 100).toLocaleString('id-ID', { maximumFractionDigits: 2 })}% / 100%</div>
            <Notice message={form.errors.komponen} />
            <p className="text-xs leading-6 text-slate-500">Nilai akhir tersedia setelah seluruh komponen berbobot terisi lengkap. Komponen dengan nilai manual tersimpan dapat diberi bobot 0% jika tidak dipakai.</p>
            <div className="flex flex-wrap gap-3"><Button type="submit" disabled={form.processing || !validTotal}>{form.processing ? 'Menyimpan...' : 'Simpan konfigurasi'}</Button><Button variant="secondary" onClick={onCancel}>Batal</Button></div>
        </fieldset>
    </form>;
}

function ManualEditor({ base, student, component, onCancel, onSaved }) {
    const form = useApiForm({ siswa_id: student.siswa_id, nilai: component.nilai ?? '', catatan: component.catatan ?? '' });
    async function save(event) {
        event.preventDefault();
        if (await form.submit('put', `${base}/komponen/${component.id}/manual`)) onSaved();
    }
    return <form onSubmit={save} className="surface space-y-4 border-teal-200 p-5">
        <div><h3 className="font-bold text-slate-900">Nilai manual · {componentNames[component.jenis]}</h3><p className="mt-1 text-sm text-slate-500">{student.nama_lengkap} · {student.nis ?? 'NIS belum diisi'}</p></div>
        <Notice message={form.errors._general || form.errors.komponen || form.errors.siswa_id} />
        <fieldset disabled={form.processing} className="space-y-4 disabled:opacity-60">
            <Field label="Nilai (0–100)" error={form.errors.nilai}><input autoFocus type="number" className="input max-w-xs" min="0" max="100" step="0.01" required value={form.data.nilai} onChange={(event) => form.setData('nilai', event.target.value)} /></Field>
            <Field label="Catatan (opsional)" error={form.errors.catatan}><textarea className="input" rows="3" maxLength={10000} value={form.data.catatan} onChange={(event) => form.setData('catatan', event.target.value)} /></Field>
            <div className="flex flex-wrap gap-3"><Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan nilai manual'}</Button><Button variant="secondary" onClick={onCancel}>Batal</Button></div>
        </fieldset>
    </form>;
}

function RecapResults({ base, config, canManage, isTeacher }) {
    const [page, setPage] = useState(1);
    const result = useApiData(`${base}/${config.id}?page=${page}`);
    const [manual, setManual] = useState(null);
    const [saved, setSaved] = useState('');
    const students = result.data?.data?.data ?? [];

    return <div className="space-y-5">
        <div className="grid gap-3 sm:grid-cols-3">{config.komponen.map((part) => <div key={part.id} className="surface p-4"><p className="text-xs font-semibold text-slate-500">{componentNames[part.jenis]}</p><p className="mt-2 text-xl font-extrabold text-teal-700">{Number(part.bobot).toLocaleString('id-ID')}%</p><p className="mt-1 text-xs text-slate-500">{part.metode === 'manual' ? 'Nilai manual' : 'Rata-rata aktivitas'}</p></div>)}</div>
        <Notice tone="success" message={saved} />
        {manual && canManage && <ManualEditor key={`${manual.student.siswa_id}-${manual.component.id}`} base={`${base}/${config.id}`} student={manual.student} component={manual.component} onCancel={() => setManual(null)} onSaved={() => { setManual(null); setSaved('Nilai manual berhasil disimpan.'); result.reload(); }} />}
        {result.loading ? <LoadingState /> : result.error ? <div className="surface space-y-3 p-5"><Notice message={result.error} /><Button variant="secondary" onClick={result.reload}>Coba lagi</Button></div> : students.length === 0 ? <EmptyState title="Belum ada data rekap" description="Rekap akan tersedia untuk siswa yang sudah diterima di kelas ini." /> : <div className="surface overflow-hidden">
            <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-slate-50 text-xs text-slate-500"><tr><th className="px-5 py-4">{isTeacher ? 'Siswa' : 'Nama'}</th>{config.komponen.map((part) => <th key={part.id} className="px-5 py-4">{componentNames[part.jenis]}<span className="mt-1 block font-normal">Bobot {Number(part.bobot).toLocaleString('id-ID')}%</span></th>)}<th className="px-5 py-4">Nilai akhir</th></tr></thead>
                <tbody className="divide-y divide-slate-100">{students.map((student) => <tr key={student.siswa_id}>
                    <td className="px-5 py-4"><p className="font-bold">{student.nama_lengkap}</p><p className="mt-1 text-xs text-slate-500">{student.nis ?? 'NIS belum diisi'}</p></td>
                    {config.komponen.map((configPart) => {
                        const part = student.komponen.find((item) => item.id === configPart.id);
                        return <td key={configPart.id} className="min-w-40 px-5 py-4">
                            <p className="font-bold text-slate-800">{score(part?.nilai)}</p>
                            {part && !part.lengkap && part.nilai !== null && <p className="mt-1 text-xs text-amber-700">Sebagian aktivitas belum dinilai</p>}
                            {part?.catatan && <p className="mt-1 max-w-xs whitespace-pre-wrap break-words text-xs text-slate-500">{part.catatan}</p>}
                            {canManage && part?.metode === 'manual' && <Button className="mt-2 !px-3 !py-2 !text-xs" variant="secondary" disabled={Boolean(manual)} onClick={() => { setSaved(''); setManual({ student, component: part }); }}>{part.nilai === null ? 'Isi nilai' : 'Edit nilai'}</Button>}
                        </td>;
                    })}
                    <td className="min-w-40 px-5 py-4"><p className="text-lg font-bold text-teal-700">{student.nilai_akhir === null ? '—' : score(student.nilai_akhir)}</p><span className={`mt-1 inline-block text-xs ${student.lengkap ? 'text-teal-700' : 'text-amber-700'}`}>{student.lengkap ? 'Lengkap' : 'Belum lengkap'}</span></td>
                </tr>)}</tbody>
            </table></div>
            <p className="border-t border-slate-100 px-5 py-4 text-xs leading-6 text-slate-500">Nilai yang belum diisi tidak dianggap 0. Nilai akhir menunggu seluruh komponen berbobot lengkap.</p>
        </div>}
        <Pager meta={result.data?.data} onPage={(nextPage) => { setManual(null); setPage(nextPage); }} />
    </div>;
}

export default function RecapPanel({ kelasMapel, permissions }) {
    const base = `/api/v1/kelas-mapel/${kelasMapel.id}/rekap`;
    const result = useApiData(permissions.viewRekap ? base : null);
    const [selectedId, setSelectedId] = useState(null);
    const [editor, setEditor] = useState(null);
    const [saved, setSaved] = useState('');
    const [revision, setRevision] = useState(0);
    const configs = result.data?.data ?? [];
    const selected = configs.find((config) => String(config.id) === String(selectedId)) ?? configs.find((config) => config.status === 'aktif') ?? configs[0];
    const canManage = permissions.manageAcademic && kelasMapel.status === 'aktif';

    if (!permissions.viewRekap) return <EmptyState title="Rekap tidak tersedia" description="Pengaturan dan hasil rekap tersedia bagi guru mata pelajaran dan siswa kelas." />;

    return <div className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-lg font-bold text-slate-900">Rekap nilai</h2><p className="mt-1 text-sm text-slate-500">{permissions.isTeacher ? 'Gabungkan nilai tugas, ulangan harian, dan ujian semester.' : 'Pantau hasil belajarmu berdasarkan konfigurasi yang diterbitkan guru.'}</p></div>{canManage && !editor && <Button onClick={() => { setEditor({ config: null }); setSaved(''); }}>Buat konfigurasi</Button>}</div>
        <Notice tone="success" message={saved} />
        {canManage && editor && <ConfigEditor key={editor.config?.id ?? 'new'} base={base} config={editor.config} onCancel={() => setEditor(null)} onSaved={(config) => { setEditor(null); setSelectedId(config.id); setSaved('Konfigurasi rekap berhasil disimpan.'); setRevision((value) => value + 1); result.reload(); }} />}
        {result.loading ? <LoadingState /> : result.error ? <div className="surface space-y-3 p-5"><Notice message={result.error} /><Button variant="secondary" onClick={result.reload}>Coba lagi</Button></div> : !selected ? <EmptyState title="Belum ada konfigurasi rekap" description={permissions.isTeacher ? 'Buat konfigurasi untuk menentukan bobot dan metode perhitungan nilai.' : 'Guru belum menerbitkan rekap nilai untuk kelas ini.'} /> : <>
            <div className="surface flex flex-wrap items-end gap-4 p-5">
                <div className="min-w-0 flex-1"><Field label="Konfigurasi rekap"><select className="input" value={selected.id} disabled={Boolean(editor)} onChange={(event) => { setSelectedId(event.target.value); setSaved(''); }}>{configs.map((config) => <option key={config.id} value={config.id}>{config.nama_konfigurasi} · {config.status === 'aktif' ? 'Aktif' : 'Draft'}</option>)}</select></Field></div>
                {canManage && <Button variant="secondary" disabled={Boolean(editor)} onClick={() => { setEditor({ config: selected }); setSaved(''); }}>Edit konfigurasi</Button>}
            </div>
            <RecapResults key={`${selected.id}-${revision}`} base={base} config={selected} canManage={canManage && !editor} isTeacher={permissions.isTeacher} />
        </>}
    </div>;
}
