import Icon from '@/Components/Icon';
import Modal from '@/Components/Modal';
import StudentLayout from '@/Layouts/StudentLayout';
import { DialogTitle } from '@headlessui/react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AcademicProfileForm from './Partials/AcademicProfileForm';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ siswa, status }) {
    const user = usePage().props.auth.user;
    const student = siswa ?? user.siswa;
    const teacher = user.guru;
    const logout = useForm({});
    const [showLogout, setShowLogout] = useState(false);
    const initials = user.name.trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    const roleLabel = user.role === 'guru' ? teacher?.jenis_guru === 'guru_piket' ? 'Guru piket' : 'Guru mata pelajaran' : 'Siswa';

    return (
        <>
            <Head title="Profil saya" />
            <StudentLayout active="profile" title="Profil saya">
                <div className="mb-7"><p className="eyebrow mb-2">Ruang personalmu</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Profil saya</h1><p className="mt-2 text-sm text-slate-500">Kelola identitas, informasi akun, dan keamananmu.</p></div>
                <div className="grid items-start gap-6 xl:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="space-y-5">
                        <section className="hero-pattern rounded-3xl bg-[#142E36] p-8 text-center text-white">
                            <div className="mx-auto flex h-24 w-24 items-center justify-center rounded-3xl border border-teal-200/20 bg-teal-200/10 text-2xl font-extrabold text-teal-200">{initials}</div>
                            <h2 className="mt-5 break-words text-xl font-bold">{user.name}</h2>
                            <span className="mt-3 inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-teal-200">{roleLabel}</span>
                            <p className="mt-6 break-all border-t border-white/10 pt-5 text-xs leading-6 text-slate-300">{user.email}</p>
                            <p className="mt-2 text-xs text-teal-200">{user.email_verified_at ? 'Email terverifikasi' : 'Email belum terverifikasi'}</p>
                        </section>
                        {(student || teacher) && <section className="surface p-5">
                            <h2 className="text-sm font-extrabold text-slate-800">Data sekolah</h2>
                            <dl className="mt-4 space-y-3 text-sm">
                                {teacher ? <>
                                    <div><dt className="text-xs text-slate-500">NIP</dt><dd className="mt-1 break-all font-semibold">{teacher.nip || 'Belum diisi'}</dd></div>
                                    <div><dt className="text-xs text-slate-500">Gelar</dt><dd className="mt-1 font-semibold">{teacher.gelar || 'Belum diisi'}</dd></div>
                                </> : <>
                                    <div><dt className="text-xs text-slate-500">NIS</dt><dd className="mt-1 break-all font-semibold">{student.nis || 'Belum diisi'}</dd></div>
                                    <div><dt className="text-xs text-slate-500">Kelas sekolah</dt><dd className="mt-1 font-semibold">{student.kelas?.nama_kelas || 'Belum dipilih'}</dd></div>
                                </>}
                            </dl>
                            {teacher && <p className="mt-4 text-xs leading-5 text-slate-500">Hubungi pengelola sekolah untuk memperbarui NIP, gelar, atau jenis guru.</p>}
                        </section>}
                    </aside>
                    <div className="min-w-0 space-y-5">
                        <UpdateProfileInformationForm status={status} className="surface p-6 sm:p-8" />
                        {user.role === 'siswa' && <section className="surface p-6 sm:p-8"><AcademicProfileForm siswa={student} /></section>}
                        <UpdatePasswordForm className="surface p-6 sm:p-8" />
                        <section className="surface p-6 sm:p-8">
                            <h2 className="text-base font-extrabold text-slate-900">Keluar dari akun</h2>
                            <p className="mt-2 text-sm leading-6 text-slate-500">Selesai menggunakan SINTAS? Keluar untuk menjaga akunmu, terutama pada perangkat bersama.</p>
                            <button type="button" onClick={() => setShowLogout(true)} className="mt-4 inline-flex items-center gap-2 rounded-xl bg-red-50 px-4 py-3 text-sm font-bold text-red-600 hover:bg-red-100"><Icon name="logout" className="h-4 w-4" />Keluar dari akun</button>
                        </section>
                        {user.role === 'siswa' && <DeleteUserForm className="surface p-6 sm:p-8" />}
                    </div>
                </div>
            </StudentLayout>
            <Modal show={showLogout} maxWidth="sm" closeable={!logout.processing} onClose={() => setShowLogout(false)}>
                <div className="p-6">
                    <span className="mb-4 inline-flex rounded-xl bg-red-50 p-3 text-red-600"><Icon name="logout" className="h-6 w-6" /></span>
                    <DialogTitle className="text-lg font-extrabold text-slate-900">Keluar dari akun?</DialogTitle>
                    <p className="mt-2 text-sm leading-6 text-slate-500">Apakah Anda yakin ingin keluar? Kamu perlu masuk kembali untuk mengakses SINTAS.</p>
                    <div className="mt-6 flex justify-end gap-3">
                        <button type="button" disabled={logout.processing} onClick={() => setShowLogout(false)} className="rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600">Batal</button>
                        <button type="button" disabled={logout.processing} onClick={() => logout.post(route('logout'))} className="rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50">{logout.processing ? 'Keluar...' : 'Ya, keluar'}</button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
