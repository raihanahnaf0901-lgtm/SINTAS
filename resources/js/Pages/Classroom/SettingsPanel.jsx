import { Button, Field, LoadingState, Notice, useApiData, useApiForm } from '@/Components/AcademicUI';
import Modal from '@/Components/Modal';
import { DialogTitle } from '@headlessui/react';
import { router } from '@inertiajs/react';
import { useState } from 'react';

function SettingsForm({ room }) {
    const form = useApiForm({ nama_kelas_mapel: room.nama_kelas_mapel, kode_kelas: room.kode_kelas, deskripsi: room.deskripsi ?? '', status: room.status });
    const [action, setAction] = useState(null);
    const [copyMessage, setCopyMessage] = useState('');
    async function save(regenerate = false) {
        const result = await form.submit('patch', `/api/v1/kelas-mapel/${room.id}`, { ...form.data, regenerate_invite: regenerate });
        if (result) { setAction(null); router.reload(); }
    }
    function submit(event) { event.preventDefault(); if (form.data.status !== room.status) setAction('status'); else save(); }
    async function copy(value) {
        try { await navigator.clipboard.writeText(value); setCopyMessage('Berhasil disalin.'); }
        catch { setCopyMessage('Pilih teks kode/link di bawah lalu salin secara manual.'); }
    }
    return <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_320px]"><form onSubmit={submit} className="surface flex flex-col gap-5 p-6"><h2 className="text-xl font-bold">Pengaturan kelas</h2><Field label="Nama kelas mata pelajaran" error={form.errors.nama_kelas_mapel}><input className="input" required maxLength={255} value={form.data.nama_kelas_mapel} onChange={(e) => form.setData('nama_kelas_mapel', e.target.value)} /></Field><Field label="Kode kelas" error={form.errors.kode_kelas}><input className="input uppercase" required minLength={6} maxLength={32} pattern="[A-Za-z0-9-]+" value={form.data.kode_kelas} onChange={(e) => form.setData('kode_kelas', e.target.value)} /></Field><p className="text-xs leading-5 text-slate-500">Mengubah kode tidak menghapus anggota. Siswa baru perlu menggunakan kode terbaru.</p><Field label="Deskripsi" error={form.errors.deskripsi}><textarea className="input" rows={4} maxLength={10000} value={form.data.deskripsi} onChange={(e) => form.setData('deskripsi', e.target.value)} /></Field><Field label="Status kelas" error={form.errors.status}><select className="input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}><option value="aktif">Aktif</option><option value="arsip">Diarsipkan</option></select></Field><Notice message={form.errors._general} /><Notice tone="success" message={form.message} /><Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan perubahan'}</Button></form>
        <section className="surface flex flex-col gap-4 p-6"><h2 className="font-bold">Undang siswa</h2><p className="text-sm leading-6 text-slate-500">Bagikan kode atau link berikut. Siswa tetap memerlukan persetujuan Anda.</p><Field label="Kode yang aktif"><input className="input font-mono tracking-widest" readOnly value={room.kode_kelas} onFocus={(e) => e.target.select()} /></Field><Button variant="secondary" onClick={() => copy(room.kode_kelas)}>Salin kode</Button><Field label="Link undangan"><textarea className="input text-xs" readOnly rows={3} value={room.link_undangan} onFocus={(e) => e.target.select()} /></Field><Button variant="secondary" onClick={() => copy(room.link_undangan)}>Salin link</Button><Notice tone="info" message={copyMessage} /><Button variant="danger" disabled={form.processing} onClick={() => setAction('link')}>Ganti link undangan</Button></section>
        <Modal show={Boolean(action)} closeable={!form.processing} onClose={() => setAction(null)} maxWidth="md"><div className="flex flex-col gap-4 p-6"><DialogTitle className="text-lg font-bold">{action === 'link' ? 'Ganti link undangan?' : 'Ubah status kelas?'}</DialogTitle><p className="text-sm leading-6 text-slate-600">{action === 'link' ? 'Link lama tidak akan bisa digunakan. Perubahan formulir juga akan disimpan.' : form.data.status === 'arsip' ? 'Siswa tidak dapat membuka kelas ini selama diarsipkan. Kegiatan dan penilaian tetap tersimpan.' : 'Kelas dapat diakses kembali oleh siswa yang sudah diterima.'}</p><Notice message={form.errors._general} /><div className="flex justify-end gap-3"><Button variant="secondary" disabled={form.processing} onClick={() => setAction(null)}>Batal</Button><Button disabled={form.processing} onClick={() => save(action === 'link')}>{form.processing ? 'Menyimpan...' : 'Ya, simpan'}</Button></div></div></Modal>
    </div>;
}

export default function SettingsPanel({ kelasMapel }) {
    const room = useApiData(`/api/v1/kelas-mapel/${kelasMapel.id}`);
    if (room.loading) return <LoadingState />;
    if (room.error) return <div className="flex flex-col gap-3"><Notice message={room.error} /><Button variant="secondary" onClick={room.reload}>Coba lagi</Button></div>;
    return room.data?.data ? <SettingsForm key={room.data.data.updated_at} room={room.data.data} /> : null;
}
