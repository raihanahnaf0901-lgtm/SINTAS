import Icon from '@/Components/Icon';
import StudentLayout from '@/Layouts/StudentLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

const summaries = [
    { label: 'Tugas', completed: 15, total: 20, icon: 'clipboard', color: 'bg-teal-50 text-teal-700', bar: 'bg-teal-600' },
    { label: 'Ulangan Harian', completed: 8, total: 10, icon: 'book', color: 'bg-blue-50 text-blue-700', bar: 'bg-blue-500' },
    { label: 'Ulangan Semester', completed: 2, total: 4, icon: 'chart', color: 'bg-orange-50 text-orange-700', bar: 'bg-orange-400' },
];

const activities = {
    deadline: [
        { subject: 'Matematika', title: 'Tugas Persamaan Kuadrat', due: '25 Okt', icon: 'calculator', color: 'bg-violet-50 text-violet-600' },
        { subject: 'Bahasa Indonesia', title: 'Resensi Buku', due: '27 Okt', icon: 'book', color: 'bg-orange-50 text-orange-600' },
        { subject: 'Fisika', title: 'Laporan Praktikum', due: '30 Okt', icon: 'atom', color: 'bg-blue-50 text-blue-600' },
    ],
    susulan: [
        { subject: 'Kimia', title: 'Laporan Praktikum', due: '15 Okt', icon: 'flask', color: 'bg-violet-50 text-violet-600' },
        { subject: 'Sejarah', title: 'Ulangan Harian', due: '18 Okt', icon: 'landmark', color: 'bg-orange-50 text-orange-600' },
        { subject: 'Biologi', title: 'Proyek Ekosistem', due: '20 Okt', icon: 'leaf', color: 'bg-teal-50 text-teal-600' },
    ],
};

const subjects = [
    { name: 'Matematika', tasks: 3, icon: 'calculator', color: 'bg-violet-50 text-violet-600' },
    { name: 'Bahasa Indonesia', tasks: 1, icon: 'book', color: 'bg-orange-50 text-orange-600' },
    { name: 'Fisika', tasks: 2, icon: 'atom', color: 'bg-blue-50 text-blue-600' },
    { name: 'Biologi', tasks: 2, icon: 'leaf', color: 'bg-teal-50 text-teal-600' },
    { name: 'Sejarah', tasks: 1, icon: 'landmark', color: 'bg-amber-50 text-amber-700' },
];

export default function Dashboard() {
    const user = usePage().props.auth?.user;
    const [activeTab, setActiveTab] = useState('deadline');
    const [query, setQuery] = useState('');
    const normalizedQuery = query.trim().toLowerCase();
    const filteredItems = activeTab === 'mapel'
        ? subjects.filter((subject) => subject.name.toLowerCase().includes(normalizedQuery))
        : activities[activeTab].filter((task) => `${task.subject} ${task.title} ${task.due}`.toLowerCase().includes(normalizedQuery));
    const completed = summaries.reduce((total, item) => total + item.completed, 0);
    const total = summaries.reduce((total, item) => total + item.total, 0);
    const progress = Math.round(completed / total * 100);

    return (
        <StudentLayout title="Beranda">
            <Head title="Beranda Siswa" />
            <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="eyebrow mb-2">Setiap langkah berarti</p>
                    <h1 className="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Halo, {user?.name?.split(' ')[0] ?? 'teman belajar'} <span className="text-teal-600">!</span></h1>
                    <p className="mt-2 text-sm leading-6 text-slate-500">Yuk, buat hari ini lebih produktif. Mulai dari satu tugas dulu.</p>
                </div>
                <span className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-500">Pratinjau · Data contoh</span>
            </div>

            <section className="hero-pattern relative mb-7 overflow-hidden rounded-3xl bg-[#142E36] p-6 text-white sm:p-8">
                <div aria-hidden="true" className="absolute -right-16 -top-24 h-80 w-80 rounded-full border border-white/10" />
                <div aria-hidden="true" className="absolute -bottom-44 right-10 h-80 w-80 rounded-full border border-white/10" />
                <div className="relative flex items-center justify-between gap-6">
                    <div className="max-w-lg">
                        <span className="inline-flex items-center gap-2 rounded-full border border-teal-300/20 bg-teal-300/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-teal-200"><Icon name="sparkles" className="h-3.5 w-3.5" />Ruang untuk bertumbuh</span>
                        <h2 className="mt-5 text-2xl font-bold leading-snug tracking-tight sm:text-3xl">Belajar lebih terarah.<br /><span className="text-teal-200">Raih lebih banyak.</span></h2>
                        <p className="mt-3 max-w-sm text-sm leading-6 text-slate-300">Pantau tugas, cek progres, dan siapkan langkah berikutnya. Semua dalam satu tempat.</p>
                        <Link href={route('subjects.index')} className="mt-6 inline-flex items-center gap-3 rounded-xl bg-white px-4 py-3 text-xs font-bold text-slate-900 transition hover:bg-teal-50">Jelajahi mata pelajaran<Icon name="arrow" className="h-4 w-4" /></Link>
                    </div>
                    <div className="relative hidden w-60 shrink-0 rotate-[-4deg] rounded-2xl border border-white/15 bg-white/[0.06] p-5 md:block" aria-hidden="true">
                        <div className="mb-6 flex items-center gap-3"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-300 text-teal-950"><Icon name="clipboard" /></span><div><p className="text-xs font-semibold">Langkah kecilmu</p><p className="mt-1 text-[10px] text-slate-300">Membawa perubahan besar</p></div></div>
                        {['Tentukan prioritas', 'Fokus satu per satu', 'Rayakan progresmu'].map((text, index) => (
                            <div key={text} className="mt-3 flex items-center gap-3 rounded-lg bg-white/[0.06] p-3"><span className={`flex h-5 w-5 items-center justify-center rounded-md ${index < 2 ? 'bg-teal-300 text-teal-950' : 'border border-white/30'}`}>{index < 2 && <Icon name="check" className="h-3 w-3" />}</span><span className="text-[11px] text-slate-200">{text}</span></div>
                        ))}
                        <span className="absolute -bottom-4 -right-3 flex h-12 w-12 rotate-12 items-center justify-center rounded-2xl bg-[#F4C77D] text-amber-950 shadow-lg"><Icon name="sparkles" className="h-6 w-6" /></span>
                    </div>
                </div>
            </section>

            <div className="mb-4 flex items-center justify-between gap-3"><h2 className="text-base font-bold text-slate-900">Ringkasan belajarmu</h2><span className="text-right text-xs text-slate-500">Progres penyelesaian</span></div>
            <div className="mb-8 grid grid-cols-3 gap-2 sm:gap-4">
                {summaries.map((summary) => (
                    <article key={summary.label} className="surface p-3 sm:p-5">
                        <div className="flex items-center justify-between gap-2"><h3 className="min-h-8 text-[10px] font-semibold text-slate-500 sm:min-h-0 sm:text-xs">{summary.label}</h3><span className={`hidden h-9 w-9 shrink-0 items-center justify-center rounded-xl sm:flex ${summary.color}`}><Icon name={summary.icon} className="h-[18px] w-[18px]" /></span></div>
                        <p className="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{summary.completed}<span className="ml-1 text-[10px] font-medium text-slate-400 sm:text-sm">/ {summary.total}</span></p>
                        <div className="mt-3 flex items-center justify-between text-[10px] sm:mt-4 sm:text-[11px]"><span className="hidden text-slate-500 sm:inline">Sudah selesai</span><span className="font-bold text-slate-700">{Math.round(summary.completed / summary.total * 100)}%</span></div>
                        <div role="progressbar" aria-label={summary.label} aria-valuenow={summary.completed} aria-valuemin={0} aria-valuemax={summary.total} className="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div className={`h-full rounded-full ${summary.bar}`} style={{ width: `${summary.completed / summary.total * 100}%` }} /></div>
                    </article>
                ))}
            </div>

            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
                <section className="surface min-w-0 p-5 sm:p-6" id="aktivitas">
                    <div className="mb-5 flex items-center justify-between gap-3"><div><h2 className="text-base font-bold text-slate-900">Aktivitas belajar</h2><p className="mt-1 text-xs text-slate-500">Tetap teratur, tetap selangkah lebih siap.</p></div><span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-100 text-slate-400"><Icon name="calendar" className="h-[18px] w-[18px]" /></span></div>
                    <label className="mb-5 flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/80 px-3.5 py-3 focus-within:border-teal-600 focus-within:ring-1 focus-within:ring-teal-600"><Icon name="search" className="h-4 w-4 shrink-0 text-slate-400" /><span className="sr-only">Cari tugas, ujian, atau mata pelajaran</span><input type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Cari tugas, ujian, atau mapel..." className="w-full border-0 bg-transparent p-0 text-xs placeholder:text-slate-400 focus:ring-0" /></label>
                    <div className="mb-5 flex gap-1 rounded-xl bg-slate-100/80 p-1" aria-label="Filter aktivitas">
                        {[['deadline', 'Deadline'], ['susulan', 'Susulan'], ['mapel', 'Mapel']].map(([id, label]) => (
                            <button key={id} type="button" aria-pressed={activeTab === id} onClick={() => setActiveTab(id)} className={`flex-1 rounded-lg px-2 py-2.5 text-xs font-bold transition ${activeTab === id ? 'bg-white text-teal-800 shadow-sm' : 'text-slate-500 hover:text-slate-800'}`}>{label}</button>
                        ))}
                    </div>
                    <div aria-live="polite">
                        {filteredItems.length === 0 ? (
                            <div className="flex min-h-56 flex-col items-center justify-center gap-3 text-center"><span className="rounded-2xl bg-slate-50 p-4 text-slate-400"><Icon name="search" className="h-7 w-7" /></span><p className="text-sm font-bold">Tidak ada hasil</p><p className="text-xs text-slate-500">Coba gunakan kata kunci lain.</p></div>
                        ) : activeTab === 'mapel' ? (
                            <div className="grid gap-3 sm:grid-cols-2">{filteredItems.map((subject) => (
                                <article key={subject.name} className="rounded-xl border border-slate-200 p-4"><span className={`mb-3 inline-flex rounded-xl p-2.5 ${subject.color}`}><Icon name={subject.icon} /></span><h3 className="text-sm font-bold">{subject.name}</h3><p className="mt-1 text-xs text-slate-500">{subject.tasks} tugas belum selesai</p>{subject.name === 'Matematika' && <Link href={route('subjects.show')} className="mt-3 inline-flex items-center gap-2 text-xs font-bold text-teal-700">Lihat tugas<Icon name="arrow" className="h-3 w-3" /></Link>}</article>
                            ))}</div>
                        ) : (
                            <div className="flex flex-col gap-3">{filteredItems.map((task) => (
                                <article key={`${task.subject}-${task.title}`} className={`flex items-start gap-3 rounded-xl border p-4 transition hover:shadow-sm ${activeTab === 'susulan' ? 'border-rose-100 bg-rose-50/40' : 'border-slate-200/80 bg-white'}`}>
                                    <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${task.color}`}><Icon name={task.icon} /></span>
                                    <div className="min-w-0 flex-1"><p className="text-[10px] font-semibold text-slate-500">{task.subject}</p><h3 className="mt-1 text-sm font-bold leading-6 text-slate-800">{task.title}</h3><div className="mt-2 flex flex-wrap items-center gap-2"><span className="inline-flex items-center gap-1.5 text-[11px] text-slate-500"><Icon name="clock" className="h-3.5 w-3.5" />Tenggat: {task.due}</span><span className={`rounded-md px-2 py-1 text-[9px] font-bold ${activeTab === 'susulan' ? 'bg-rose-100 text-rose-700' : 'bg-amber-50 text-amber-800'}`}>{activeTab === 'susulan' ? 'Terlambat' : 'Belum selesai'}</span></div></div>
                                </article>
                            ))}</div>
                        )}
                    </div>
                    <Link href={route('subjects.index')} className="mt-5 flex items-center justify-center gap-2 border-t border-slate-100 pt-5 text-xs font-bold text-teal-700 hover:text-teal-900">Lihat semua mata pelajaran<Icon name="arrow" className="h-4 w-4" /></Link>
                </section>

                <aside className="flex flex-col gap-5">
                    <section className="surface p-6">
                        <h2 className="text-sm font-bold text-slate-900">Progres keseluruhan</h2><p className="mt-1 text-xs text-slate-500">Setiap usaha patut diapresiasi.</p>
                        <div className="relative mx-auto my-6 flex h-36 w-36 items-center justify-center"><svg aria-hidden="true" viewBox="0 0 120 120" className="absolute inset-0 -rotate-90"><circle cx="60" cy="60" r="52" fill="none" stroke="#F0F4F5" strokeWidth="9" /><circle cx="60" cy="60" r="52" fill="none" stroke="#0D9488" strokeWidth="9" strokeLinecap="round" pathLength="100" strokeDasharray={`${progress} 100`} /></svg><div className="text-center"><p className="text-3xl font-extrabold tracking-tight text-slate-900">{progress}%</p><p className="mt-1 text-[10px] text-slate-500">terselesaikan</p></div></div>
                        <div className="flex justify-between rounded-xl bg-slate-50 p-3 text-xs"><span className="text-slate-500">Aktivitas selesai</span><span className="font-bold text-slate-800">{completed} dari {total}</span></div>
                    </section>
                    <section className="rounded-2xl border border-amber-200/60 bg-[#FFF8EB] p-5"><div className="flex items-center gap-2 text-amber-800"><Icon name="sparkles" className="h-4 w-4" /><h2 className="text-xs font-bold">Tips belajar</h2></div><p className="mt-3 text-sm font-bold leading-6 text-slate-800">Fokus 25 menit.<br />Istirahat 5 menit.</p><p className="mt-2 text-xs leading-6 text-slate-600">Beri ruang untuk jeda agar pikiran kembali segar sebelum tugas berikutnya.</p></section>
                </aside>
            </div>
        </StudentLayout>
    );
}
