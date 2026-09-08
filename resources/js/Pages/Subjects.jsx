import Icon from '@/Components/Icon';
import StudentLayout from '@/Layouts/StudentLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const subjectColors = ['bg-violet-50 text-violet-600', 'bg-orange-50 text-orange-600', 'bg-blue-50 text-blue-600', 'bg-teal-50 text-teal-700', 'bg-amber-50 text-amber-700', 'bg-rose-50 text-rose-600'];

function SubjectCard({ subject, index }) {
    const progress = subject.total ? Math.min(100, Math.round(subject.completed / subject.total * 100)) : 0;

    return (
        <Link href={route('subjects.show', { subject: subject.slug })} className="surface group flex min-w-0 flex-col p-5 transition duration-200 hover:border-teal-200 hover:shadow-md">
            <div className="mb-5 flex items-center justify-between">
                <span className={`flex h-12 w-12 items-center justify-center rounded-2xl ${subjectColors[index % subjectColors.length]}`}><Icon name={subject.icon} className="h-6 w-6" /></span>
                <span className="rounded-lg bg-slate-50 px-2.5 py-1 text-[10px] font-semibold text-slate-500">Mata pelajaran</span>
            </div>
            <h2 className="text-base font-bold tracking-tight text-slate-900">{subject.name}</h2>
            <p className="mt-2 flex items-center gap-1.5 text-xs text-slate-500"><Icon name="user" className="h-3.5 w-3.5" />{subject.teacher}</p>
            <div className="mt-6">
                <div className="mb-2 flex items-center justify-between gap-2 text-[11px]"><span className="text-slate-500">{subject.total ? `${subject.completed}/${subject.total} aktivitas selesai` : 'Belum ada aktivitas'}</span><span className="font-bold text-teal-700">{progress}%</span></div>
                <div role="progressbar" aria-label={`Progres ${subject.name}`} aria-valuenow={progress} aria-valuemin={0} aria-valuemax={100} className="h-1.5 overflow-hidden rounded-full bg-slate-100"><div className="h-full rounded-full bg-teal-500" style={{ width: `${progress}%` }} /></div>
            </div>
            <div className="mt-5 border-t border-slate-100 pt-4">
                <span className="flex items-center justify-between text-xs font-bold text-teal-700 group-hover:text-teal-900">Lihat pelajaran<Icon name="arrow" className="h-4 w-4" /></span>
            </div>
        </Link>
    );
}

export default function Subjects({ subjects }) {
    const [query, setQuery] = useState('');
    const filteredSubjects = subjects.filter((subject) => `${subject.name} ${subject.teacher}`.toLowerCase().includes(query.trim().toLowerCase()));

    return (
        <StudentLayout active="subjects" title="Mata pelajaran">
            <Head title="Daftar Mata Pelajaran" />
            <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                <div><p className="eyebrow mb-2">Eksplorasi ruang belajarmu</p><h1 className="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Mata pelajaran</h1><p className="mt-2 text-sm leading-6 text-slate-500">Semua pelajaran, tugas, dan progresmu dalam satu tempat.</p></div>
            </div>
            <section className="hero-pattern mb-7 flex flex-wrap items-center justify-between gap-5 rounded-3xl bg-[#142E36] p-6 text-white sm:p-8">
                <div className="flex items-center gap-4"><span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-white/10 text-teal-200"><Icon name="book" className="h-7 w-7" /></span><div><h2 className="text-lg font-bold">Ilmu baru, peluang baru.</h2><p className="mt-1 text-xs leading-6 text-slate-300">Pilih mata pelajaran dan lanjutkan perjalanan belajarmu.</p></div></div>
                <div className="flex items-center gap-3"><span className="text-4xl font-extrabold text-teal-200">{subjects.length}</span><span className="text-xs leading-5 text-slate-300">Mata pelajaran<br />untuk dijelajahi</span></div>
            </section>
            <div className="mb-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <h2 className="text-sm font-bold text-slate-800">Daftar mata pelajaran <span className="ml-2 rounded-md bg-slate-200/60 px-2 py-1 text-[10px] text-slate-600">{filteredSubjects.length}</span></h2>
                <label className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 focus-within:border-teal-600 focus-within:ring-1 focus-within:ring-teal-600 sm:w-80"><Icon name="search" className="h-4 w-4 shrink-0 text-slate-400" /><span className="sr-only">Cari mata pelajaran atau guru</span><input type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Cari mata pelajaran atau guru..." className="w-full border-0 bg-transparent p-0 text-xs focus:ring-0" /></label>
            </div>
            <div aria-live="polite">
                {filteredSubjects.length > 0 ? <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">{filteredSubjects.map((subject) => <SubjectCard key={subject.slug} subject={subject} index={subjects.indexOf(subject)} />)}</div> : <div className="surface flex flex-col items-center gap-3 px-5 py-16 text-center"><Icon name="search" className="h-8 w-8 text-slate-400" /><h2 className="text-sm font-bold">Mata pelajaran tidak ditemukan</h2><p className="text-xs text-slate-500">Coba nama pelajaran atau guru yang lain.</p><button type="button" onClick={() => setQuery('')} className="mt-2 text-xs font-bold text-teal-700">Tampilkan semua pelajaran</button></div>}
            </div>
        </StudentLayout>
    );
}
