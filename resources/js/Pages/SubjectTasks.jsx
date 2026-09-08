import Icon from '@/Components/Icon';
import StudentLayout from '@/Layouts/StudentLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function SubjectTasks({ subject, tasks }) {
    const [activeType, setActiveType] = useState('all');
    const filteredTasks = tasks.filter((task) => activeType === 'all' || task.type === activeType);
    const completed = tasks.filter((task) => task.completed).length;
    const progress = tasks.length ? Math.round(completed / tasks.length * 100) : 0;

    return (
        <StudentLayout active="subjects" title={subject.name}>
            <Head title={`${subject.name} - Tugas`} />
            <Link href={route('subjects.index')} className="mb-6 inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-teal-700"><Icon name="arrowLeft" className="h-4 w-4" />Kembali ke mata pelajaran</Link>
            <section className="hero-pattern mb-8 rounded-3xl bg-[#142E36] p-6 text-white sm:p-8">
                <div className="flex flex-wrap items-center justify-between gap-6">
                    <div><span className="mb-5 inline-flex rounded-2xl border border-white/15 bg-white/10 p-3 text-teal-200"><Icon name={subject.icon} className="h-7 w-7" /></span><p className="text-[10px] font-bold uppercase tracking-[0.2em] text-teal-200">Ruang mata pelajaran</p><h1 className="mt-2 text-3xl font-extrabold tracking-tight">{subject.name}</h1><p className="mt-3 flex items-center gap-2 text-sm text-slate-300"><Icon name="user" className="h-4 w-4" />{subject.teacher}</p></div>
                    <div className="w-full rounded-2xl border border-white/10 bg-white/[0.06] p-5 sm:w-60"><p className="text-xs text-slate-300">Progres belajarmu</p><p className="mt-3 text-4xl font-extrabold text-teal-200">{progress}<span className="text-xl">%</span></p><div role="progressbar" aria-label="Progres tugas" aria-valuenow={progress} aria-valuemin={0} aria-valuemax={100} className="my-4 h-1.5 overflow-hidden rounded-full bg-white/10"><div className="h-full rounded-full bg-teal-300" style={{ width: `${progress}%` }} /></div><p className="text-xs text-slate-300">{completed} dari {tasks.length} aktivitas selesai</p></div>
                </div>
            </section>
            <div className="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_280px]">
                <section>
                    <div className="mb-5 flex items-center justify-between"><h2 className="text-base font-bold text-slate-900">Tugas &amp; ulangan</h2><span className="text-xs text-slate-500">{tasks.length} aktivitas</span></div>
                    <div className="mb-5 grid grid-cols-2 gap-2 rounded-2xl bg-slate-100 p-2 sm:grid-cols-4" aria-label="Jenis aktivitas">
                        {[['all', 'Semua'], ['tugas', 'Tugas'], ['harian', 'Ulangan harian'], ['semester', 'Ujian semester']].map(([type, label]) => (
                            <button key={type} type="button" aria-pressed={activeType === type} onClick={() => setActiveType(type)} className={`rounded-xl px-3 py-3 text-xs font-bold transition ${activeType === type ? 'bg-white text-teal-800 shadow-sm' : 'text-slate-500 hover:bg-white/60 hover:text-slate-800'}`}>{label}</button>
                        ))}
                    </div>
                    <div className="flex flex-col gap-4" aria-live="polite">
                        {filteredTasks.map((task, index) => (
                            <article key={task.id} className="surface flex items-start gap-4 p-5 sm:p-6">
                                <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${task.completed ? 'bg-teal-50 text-teal-700' : 'bg-amber-50 text-amber-700'}`}><Icon name={task.completed ? 'check' : 'clock'} /></span>
                                <div className="min-w-0 flex-1"><div className="mb-2 flex flex-wrap items-center justify-between gap-2"><p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Aktivitas {String(index + 1).padStart(2, '0')}</p><span className={`rounded-md px-2 py-1 text-[10px] font-bold ${task.completed ? 'bg-teal-50 text-teal-700' : 'bg-amber-50 text-amber-800'}`}>{task.completed ? 'Selesai' : 'Belum selesai'}</span></div><h3 className="text-sm font-bold leading-6 text-slate-900 sm:text-base">{task.title}</h3><div className="mt-3 flex flex-col gap-1.5">{task.details.map((detail) => <p key={detail} className="text-xs leading-5 text-slate-500">{detail}</p>)}</div></div>
                            </article>
                        ))}
                        {tasks.length === 0 && <div className="surface py-12 text-center"><Icon name="clipboard" className="mx-auto mb-3 h-8 w-8 text-slate-400" /><h3 className="text-sm font-bold">Belum ada tugas</h3><p className="mt-2 text-xs text-slate-500">Tugas dan ulangan akan muncul di sini.</p></div>}
                        {tasks.length > 0 && filteredTasks.length === 0 && <div className="surface px-5 py-12 text-center"><Icon name="clipboard" className="mx-auto mb-3 h-8 w-8 text-slate-400" /><h3 className="text-sm font-bold">Belum ada aktivitas untuk kategori ini</h3><button type="button" onClick={() => setActiveType('all')} className="mt-4 text-xs font-bold text-teal-700">Tampilkan semua aktivitas</button></div>}
                    </div>
                </section>
                <aside className="surface p-6"><span className="inline-flex rounded-xl bg-teal-50 p-3 text-teal-700"><Icon name="sparkles" /></span><h2 className="mt-4 text-sm font-bold text-slate-900">Jaga ritme belajarmu</h2><p className="mt-3 text-xs leading-6 text-slate-500">Periksa tenggat setiap tugas dan siapkan waktu untuk mengerjakannya. Mulai lebih awal agar belajar terasa lebih ringan.</p><div className="mt-5 flex justify-between border-t border-slate-100 pt-4 text-xs"><span className="text-slate-500">Belum selesai</span><span className="font-bold text-amber-700">{tasks.length - completed} aktivitas</span></div></aside>
            </div>
        </StudentLayout>
    );
}
