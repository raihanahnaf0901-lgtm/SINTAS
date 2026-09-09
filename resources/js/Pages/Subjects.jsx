import { Button, EmptyState, Field, LoadingState, Notice, Pager, StatusBadge, useApiData, useApiForm } from '@/Components/AcademicUI';
import Icon from '@/Components/Icon';
import StudentLayout from '@/Layouts/StudentLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export function JoinClassForm({ invitation, onJoined }) {
    const master = useApiData('/api/v1/master-data');
    const form = useApiForm({ mapel_id: invitation?.mapel_id ?? '', kode_kelas: '', link: '' });
    const [method, setMethod] = useState(invitation ? 'link' : 'kode');
    const [linkError, setLinkError] = useState('');
    async function submit(event) {
        event.preventDefault();
        setLinkError('');
        const payload = { mapel_id: form.data.mapel_id };
        if (method === 'link') {
            try {
                const url = invitation ? null : new URL(form.data.link);
                const match = url?.pathname.match(/^\/kelas-mapel\/undangan\/([A-Za-z0-9]+)\/?$/);
                if (!invitation && !match) throw new Error('invalid');
                payload.invite_token = invitation?.token ?? match[1];
            } catch { setLinkError('Masukkan link undangan kelas SINTAS yang lengkap.'); return; }
        } else { payload.kode_kelas = form.data.kode_kelas.trim().toUpperCase(); }
        const result = await form.submit('post', '/api/v1/kelas-mapel/gabung', payload);
        if (result) onJoined?.();
    }
    return <form onSubmit={submit} className="surface flex flex-col gap-5 p-6">
        <div><h2 className="text-lg font-bold">Bergabung ke kelas</h2><p className="mt-1 text-sm leading-6 text-slate-500">Pilih mata pelajaran dan gunakan kode atau link dari guru. Akses dibuka setelah guru menerima permintaanmu.</p></div>
        {!invitation && <div className="flex gap-2">{[['kode', 'Kode kelas'], ['link', 'Link undangan']].map(([value, label]) => <Button key={value} variant={method === value ? 'primary' : 'secondary'} disabled={form.processing} onClick={() => { setMethod(value); setLinkError(''); }}>{label}</Button>)}</div>}
        {master.loading ? <LoadingState /> : master.error ? <><Notice message={master.error} /><Button variant="secondary" onClick={master.reload}>Coba lagi</Button></> : <Field label="Mata pelajaran" error={form.errors.mapel_id}><select className="input" required disabled={Boolean(invitation) || form.processing} value={form.data.mapel_id} onChange={(e) => form.setData('mapel_id', e.target.value)}><option value="">Pilih mata pelajaran</option>{master.data?.mapel?.map((item) => <option key={item.id} value={item.id}>{item.nama_mapel}</option>)}</select></Field>}
        {!invitation && (method === 'kode' ? <Field label="Kode kelas" error={form.errors.kode_kelas}><input className="input uppercase" required maxLength={32} value={form.data.kode_kelas} onChange={(e) => form.setData('kode_kelas', e.target.value)} placeholder="Contoh: MTK-10A" /></Field> : <Field label="Link undangan" error={linkError || form.errors.invite_token || form.errors.kode_kelas}><input className="input" type="url" required value={form.data.link} onChange={(e) => form.setData('link', e.target.value)} placeholder="Tempel link dari guru" /></Field>)}
        <Notice message={form.errors._general || form.errors.profil || (invitation && (form.errors.kode_kelas || form.errors.invite_token))} /><Notice tone="success" message={form.message} />
        <Button type="submit" disabled={form.processing || master.loading || Boolean(master.error)}>{form.processing ? 'Mengirim...' : 'Ajukan bergabung'}</Button>
    </form>;
}

function CreateClassForm() {
    const master = useApiData('/api/v1/master-data');
    const form = useApiForm({ mapel_id: '', nama_kelas_mapel: '', kode_kelas: '', deskripsi: '' });
    async function submit(event) {
        event.preventDefault();
        const result = await form.submit('post', '/api/v1/kelas-mapel', { ...form.data, kode_kelas: form.data.kode_kelas.trim() || null });
        if (result) router.visit(route('subjects.section', { subject: result.data.id, section: 'pengaturan' }));
    }
    return <form onSubmit={submit} className="surface flex flex-col gap-5 p-6"><div><h2 className="text-lg font-bold">Buat kelas mata pelajaran</h2><p className="mt-1 text-sm text-slate-500">Siapkan ruang belajar dan undang siswa ke kelas Anda.</p></div>
        <Field label="Nama kelas mata pelajaran" error={form.errors.nama_kelas_mapel}><input className="input" required maxLength={255} value={form.data.nama_kelas_mapel} onChange={(e) => form.setData('nama_kelas_mapel', e.target.value)} placeholder="Matematika · X IPA 1" /></Field>
        {master.loading ? <LoadingState /> : master.error ? <><Notice message={master.error} /><Button variant="secondary" onClick={master.reload}>Coba lagi</Button></> : <Field label="Mata pelajaran" error={form.errors.mapel_id}><select className="input" required value={form.data.mapel_id} onChange={(e) => form.setData('mapel_id', e.target.value)}><option value="">Pilih mata pelajaran</option>{master.data?.mapel?.map((item) => <option key={item.id} value={item.id}>{item.nama_mapel}</option>)}</select></Field>}
        <Link className="text-xs font-semibold text-teal-700 underline" href={route('master-data.index')}>Mata pelajaran belum tersedia? Tambahkan di data sekolah.</Link>
        <Field label="Kode khas (opsional)" error={form.errors.kode_kelas}><input className="input uppercase" minLength={6} maxLength={32} pattern="[A-Za-z0-9-]+" value={form.data.kode_kelas} onChange={(e) => form.setData('kode_kelas', e.target.value)} placeholder="6–32 huruf, angka, atau tanda -" /></Field><p className="text-xs text-slate-500">Kosongkan untuk membuat kode otomatis. Kode bisa diubah setelah kelas dibuat.</p>
        <Field label="Deskripsi (opsional)" error={form.errors.deskripsi}><textarea className="input" rows={3} maxLength={10000} value={form.data.deskripsi} onChange={(e) => form.setData('deskripsi', e.target.value)} /></Field><Notice message={form.errors._general} /><Button type="submit" disabled={form.processing || master.loading || Boolean(master.error)}>{form.processing ? 'Membuat...' : 'Buat kelas'}</Button></form>;
}

export default function Subjects() {
    const user = usePage().props.auth.user;
    const [page, setPage] = useState(1);
    const [formOpen, setFormOpen] = useState(false);
    const [query, setQuery] = useState('');
    const rooms = useApiData(`/api/v1/kelas-mapel?page=${page}`);
    const canCreate = user.role === 'guru' && user.guru?.jenis_guru === 'guru_mapel';
    const isStudent = user.role === 'siswa';
    const list = rooms.data?.data?.data ?? [];
    const filtered = list.filter((item) => `${item.nama_kelas_mapel} ${item.mapel?.nama_mapel}`.toLowerCase().includes(query.toLowerCase()));
    return <StudentLayout active="subjects" title="Mata pelajaran"><Head title="Mata Pelajaran" />
        <div className="mb-7 flex flex-wrap items-end justify-between gap-4"><div><p className="eyebrow mb-2">Ruang belajar bersama</p><h1 className="text-3xl font-extrabold tracking-tight">Mata pelajaran</h1><p className="mt-2 text-sm text-slate-500">{isStudent ? 'Kelas yang sudah menerima kamu tampil di sini.' : 'Kelola kelas dan dampingi kegiatan belajar siswa.'}</p></div>{(canCreate || isStudent) && <Button onClick={() => setFormOpen(!formOpen)}>{formOpen ? 'Tutup formulir' : isStudent ? 'Gabung kelas' : '+ Buat kelas'}</Button>}</div>
        {formOpen && <div className="mb-7">{isStudent ? <JoinClassForm onJoined={() => { rooms.reload(); }} /> : <CreateClassForm />}</div>}
        {rooms.loading ? <LoadingState /> : rooms.error ? <div className="flex flex-col gap-3"><Notice message={rooms.error} /><Button variant="secondary" onClick={rooms.reload}>Coba lagi</Button></div> : <>
            {Boolean(rooms.data?.permintaan?.length) && <section className="surface mb-7 p-6"><h2 className="mb-4 font-bold">Status pengajuanmu</h2><div className="flex flex-col gap-3">{rooms.data.permintaan.map((entry) => <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-4" key={entry.id}><div><p className="text-sm font-semibold">{entry.kelas_mapel?.nama_kelas_mapel}</p><p className="mt-1 text-xs text-slate-500">{entry.status === 'pending' ? 'Menunggu keputusan guru pemilik kelas.' : 'Kamu bisa mengajukan kembali dengan kode kelas.'}</p></div><StatusBadge status={entry.status} /></div>)}</div><Button className="mt-4" variant="secondary" onClick={rooms.reload}>Perbarui status</Button></section>}
            {list.length > 0 && <label className="mb-5 block"><span className="sr-only">Cari kelas pada halaman ini</span><input className="input" type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Cari kelas pada halaman ini..." /></label>}
            {filtered.length ? <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">{filtered.map((item) => <Link href={route('subjects.show', { subject: item.id })} key={item.id} className="surface group flex flex-col gap-4 p-6 transition hover:border-teal-300"><div className="flex items-start justify-between gap-3"><span className="rounded-2xl bg-teal-50 p-3 text-teal-700"><Icon name="book" /></span><StatusBadge status={item.status} /></div><div><p className="text-xs font-semibold text-teal-700">{item.mapel?.nama_mapel}</p><h2 className="mt-2 break-words text-lg font-bold">{item.nama_kelas_mapel}</h2><p className="mt-2 text-sm text-slate-500">{item.pembuat?.nama_lengkap} {item.pembuat?.gelar}</p></div><div className="mt-auto flex items-center justify-between border-t border-slate-100 pt-4 text-xs"><span className="text-slate-500">{item.anggota_count} siswa</span><span className="flex items-center gap-2 font-bold text-teal-700">Buka kelas <Icon name="arrow" className="h-4 w-4" /></span></div></Link>)}</div> : <EmptyState title={query ? 'Kelas tidak ditemukan' : 'Belum ada kelas mata pelajaran'} description={query ? 'Coba kata kunci lain atau periksa halaman berikutnya.' : isStudent ? 'Masukkan kode dari guru untuk mengajukan bergabung. Tugas dan nilai akan muncul setelah permintaanmu diterima.' : 'Kelas yang Anda buat atau yang memberi Anda akses akan tampil di sini.'} />}
            <Pager meta={rooms.data?.data} onPage={(next) => { setPage(next); setQuery(''); }} />
        </>}
    </StudentLayout>;
}
