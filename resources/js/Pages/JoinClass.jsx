import { Notice } from '@/Components/AcademicUI';
import StudentLayout from '@/Layouts/StudentLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { JoinClassForm } from './Subjects';

export default function JoinClass({ invitation }) {
    const user = usePage().props.auth?.user;
    const [sent, setSent] = useState(false);
    return <StudentLayout active="subjects" title="Undangan kelas"><Head title="Undangan Kelas" /><div className="mx-auto flex max-w-2xl flex-col gap-6"><section className="hero-pattern rounded-3xl bg-[#142E36] p-8 text-white"><p className="text-xs font-bold uppercase tracking-widest text-teal-200">Undangan belajar</p><h1 className="mt-4 text-3xl font-extrabold">{invitation.nama_kelas_mapel}</h1><p className="mt-3 text-slate-300">{invitation.mapel} · {invitation.guru}</p>{invitation.deskripsi && <p className="mt-4 whitespace-pre-wrap break-words text-sm leading-6 text-slate-300">{invitation.deskripsi}</p>}</section>
        {!user ? <section className="surface flex flex-col gap-4 p-6"><h2 className="text-lg font-bold">Masuk untuk bergabung</h2><p className="text-sm leading-6 text-slate-500">Gunakan akun siswa. Undangan ini disimpan agar bisa dilanjutkan setelah masuk dan melengkapi profil.</p><Link className="button-primary" href={route('login')}>Masuk ke akun siswa</Link><Link className="button-secondary" href={route('register')}>Buat akun siswa</Link></section> : user.role !== 'siswa' ? <Notice tone="info" message="Undangan bergabung ini khusus akun siswa. Guru mendapat akses melalui daftar guru yang diizinkan oleh pemilik kelas." /> : !user.siswa?.nis ? <section className="surface flex flex-col gap-4 p-6"><p>Lengkapi nama dan NIS sebelum mengajukan bergabung.</p><Link className="button-primary" href={route('profile.edit')}>Lengkapi profil</Link></section> : sent ? <section className="surface flex flex-col gap-4 p-6"><Notice tone="success" message="Permintaan terkirim. Tunggu persetujuan guru untuk membuka isi kelas." /><Link className="button-primary" href={route('subjects.index')}>Lihat status pengajuan</Link></section> : <JoinClassForm invitation={invitation} onJoined={() => setSent(true)} />}
    </div></StudentLayout>;
}
