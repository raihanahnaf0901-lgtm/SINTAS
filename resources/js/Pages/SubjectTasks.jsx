import { Notice, StatusBadge } from '@/Components/AcademicUI';
import StudentLayout from '@/Layouts/StudentLayout';
import { Head, Link } from '@inertiajs/react';
import TasksPanel from './Classroom/TasksPanel';
import SchedulePanel from './Classroom/SchedulePanel';
import ExamsPanel from './Classroom/ExamsPanel';
import GradesPanel from './Classroom/GradesPanel';
import RecapPanel from './Classroom/RecapPanel';
import MembersPanel from './Classroom/MembersPanel';
import SettingsPanel from './Classroom/SettingsPanel';

export default function SubjectTasks({ kelasMapel, permissions, section = 'tugas' }) {
    const tabs = [['tugas', 'Tugas'], ['jadwal', 'Jadwal'], ['ujian', 'Ujian'], ...(permissions.isTeacher ? [['anggota', 'Anggota']] : []), ['nilai', 'Penilaian'], ...(permissions.viewRekap ? [['rekap', 'Rekap nilai']] : []), ...(permissions.update ? [['pengaturan', 'Pengaturan kelas']] : [])];
    const panels = { tugas: TasksPanel, jadwal: SchedulePanel, ujian: ExamsPanel, anggota: MembersPanel, nilai: GradesPanel, rekap: RecapPanel, pengaturan: SettingsPanel };
    const Panel = panels[section];
    return <StudentLayout active="subjects" title={kelasMapel.nama_kelas_mapel}><Head title={kelasMapel.nama_kelas_mapel} />
        <Link href={route('subjects.index')} className="mb-5 inline-block text-sm font-semibold text-teal-700">← Semua mata pelajaran</Link>
        <section className="hero-pattern mb-6 rounded-3xl bg-[#142E36] p-6 text-white sm:p-8"><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-widest text-teal-200">{kelasMapel.mapel?.nama_mapel}</p><h1 className="mt-3 break-words text-2xl font-extrabold sm:text-3xl">{kelasMapel.nama_kelas_mapel}</h1><p className="mt-3 text-sm text-slate-300">{kelasMapel.pembuat?.nama_lengkap} {kelasMapel.pembuat?.gelar}</p></div><StatusBadge status={kelasMapel.status} /></div>{kelasMapel.deskripsi && <p className="mt-5 whitespace-pre-wrap break-words text-sm leading-6 text-slate-300">{kelasMapel.deskripsi}</p>}</section>
        {kelasMapel.status === 'arsip' && <div className="mb-5"><Notice tone="info" message="Kelas diarsipkan. Data dapat dilihat; aktifkan kembali melalui pengaturan untuk melanjutkan kegiatan." /></div>}
        {permissions.isTeacher && !permissions.manageAcademic && kelasMapel.status === 'aktif' && <div className="mb-5"><Notice tone="info" message="Akses guru piket: Anda dapat melihat kegiatan dan memantau pengumpulan siswa." /></div>}
        <nav aria-label="Menu kelas" className="mb-6 flex gap-2 overflow-x-auto border-b border-slate-200 pb-3">{tabs.map(([key, label]) => <Link key={key} href={route('subjects.section', { subject: kelasMapel.id, section: key })} aria-current={key === section ? 'page' : undefined} className={`shrink-0 rounded-xl px-4 py-3 text-sm font-bold ${key === section ? 'bg-teal-700 text-white' : 'bg-white text-slate-600 hover:bg-teal-50'}`}>{label}</Link>)}</nav>
        {Panel && <Panel key={`${kelasMapel.id}-${section}`} kelasMapel={kelasMapel} permissions={permissions} />}
    </StudentLayout>;
}
