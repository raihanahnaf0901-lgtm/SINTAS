import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const summaries = [
    { label: 'Tugas', completed: 15, total: 20 },
    { label: 'Ulangan Harian', completed: 8, total: 10 },
    { label: 'Ulangan Semester', completed: 2, total: 4 },
];

const activities = {
    deadline: [
        { subject: 'Matematika', title: 'Tugas Persamaan Kuadrat', due: '25 Okt' },
        { subject: 'Bahasa Indonesia', title: 'Resensi Buku', due: '27 Okt' },
        { subject: 'Fisika', title: 'Laporan Praktikum', due: '30 Okt' },
    ],
    susulan: [
        { subject: 'Kimia', title: 'Laporan Praktikum', due: '15 Okt' },
        { subject: 'Sejarah', title: 'Ulangan Harian', due: '18 Okt' },
        { subject: 'Biologi', title: 'Proyek Ekosistem', due: '20 Okt' },
    ],
};

const subjects = [
    {
        name: 'Matematika',
        tasks: 3,
        icon: 'calculator',
        color: 'bg-gradient-to-br from-purple-500 to-purple-600',
    },
    {
        name: 'Bahasa Indonesia',
        tasks: 1,
        icon: 'book-open',
        color: 'bg-gradient-to-br from-orange-400 to-orange-600',
    },
    {
        name: 'Fisika',
        tasks: 2,
        icon: 'atom',
        color: 'bg-gradient-to-br from-sky-400 to-sky-600',
    },
    {
        name: 'Biologi',
        tasks: 2,
        icon: 'dna',
        color: 'bg-gradient-to-br from-green-400 to-green-600',
    },
    {
        name: 'Sejarah',
        tasks: 1,
        icon: 'landmark',
        color: 'bg-gradient-to-br from-yellow-400 to-yellow-500',
    },
];

const tabs = [
    { id: 'deadline', label: 'Deadline' },
    { id: 'susulan', label: 'Susulan' },
    { id: 'mapel', label: 'Mapel' },
];

const navigation = [
    { label: 'Beranda', icon: 'home', active: true },
    { label: 'Jadwal', icon: 'calendar' },
    { label: 'Mapel', icon: 'book-marked' },
];

function Icon({ name, className = 'h-5 w-5' }) {
    const paths = {
        atom: (
            <>
                <circle cx="12" cy="12" r="1" />
                <path d="M20.2 20.2c2.04-2.03-.27-7.64-5.15-12.51S4.57.5 2.53 2.53c-2.03 2.04.27 7.64 5.15 12.52s10.49 7.18 12.52 5.15Z" />
                <path d="M15.05 15.05c4.88-4.88 7.18-10.48 5.15-12.52C18.16.5 12.56 2.8 7.68 7.68S.5 18.16 2.53 20.2c2.04 2.03 7.64-.27 12.52-5.15Z" />
            </>
        ),
        bell: (
            <>
                <path d="M10.27 21a2 2 0 0 0 3.46 0" />
                <path d="M3.26 15.33A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.67C19.41 13.93 18 12.45 18 8A6 6 0 0 0 6 8c0 4.45-1.41 5.93-2.74 7.33Z" />
            </>
        ),
        'book-marked': (
            <>
                <path d="M10 2v8l3-3 3 3V2" />
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z" />
            </>
        ),
        'book-open': (
            <>
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2Z" />
                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7Z" />
            </>
        ),
        calendar: (
            <>
                <path d="M8 2v4M16 2v4M3 10h18" />
                <rect width="18" height="18" x="3" y="4" rx="2" />
            </>
        ),
        calculator: (
            <>
                <rect x="4" y="2" width="16" height="20" rx="2" />
                <path d="M8 6h8M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01" />
            </>
        ),
        chevron: <path d="m9 18 6-6-6-6" />,
        dna: (
            <>
                <path d="M2 15c6.7 0 13.3-6 20-6M2 9c6.7 0 13.3 6 20 6" />
                <path d="M6 8v8M10 9.5v5M14 9.5v5M18 8v8" />
            </>
        ),
        home: (
            <>
                <path d="m3 11 9-9 9 9" />
                <path d="M5 10v11h14V10M9 21v-6h6v6" />
            </>
        ),
        landmark: (
            <>
                <path d="m3 10 9-6 9 6M5 10h14M6 10v8M10 10v8M14 10v8M18 10v8M4 18h16M3 22h18" />
            </>
        ),
        percent: (
            <>
                <line x1="19" x2="5" y1="5" y2="19" />
                <circle cx="6.5" cy="6.5" r="2.5" />
                <circle cx="17.5" cy="17.5" r="2.5" />
            </>
        ),
        search: (
            <>
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.3-4.3" />
            </>
        ),
        user: (
            <>
                <circle cx="12" cy="8" r="4" />
                <path d="M4 21a8 8 0 0 1 16 0" />
            </>
        ),
    };

    return (
        <svg
            aria-hidden="true"
            className={className}
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="2"
        >
            {paths[name]}
        </svg>
    );
}

function SummaryCard({ label, completed, total }) {
    const percentage = total === 0 ? 0 : Math.round((completed / total) * 100);

    return (
        <article className="flex h-[88px] min-w-0 flex-col justify-between rounded-2xl bg-white px-3 py-3 shadow-[0_4px_14px_rgba(15,23,42,0.05)]">
            <h2 className="text-[10px] font-bold leading-tight text-slate-800 sm:text-[11px]">
                {label}
            </h2>
            <div>
                <p className="mb-1 text-right text-[11px] font-semibold text-slate-600">
                    {completed}/{total}
                </p>
                <div className="h-1.5 overflow-hidden rounded-full bg-slate-200">
                    <div
                        className="h-full rounded-full bg-sky-400 transition-[width] duration-500"
                        style={{ width: `${percentage}%` }}
                    />
                </div>
            </div>
        </article>
    );
}

function TaskCard({ task, isLate }) {
    return (
        <article
            className={
                isLate
                    ? 'relative rounded-2xl border-l-4 border-red-500 bg-red-50 p-3.5 shadow-[0_2px_8px_rgba(222,54,54,0.10)]'
                    : 'rounded-2xl border border-slate-200 bg-white p-3.5 shadow-[0_2px_8px_rgba(15,23,42,0.04)]'
            }
        >
            {isLate && (
                <span className="absolute right-3 top-3 rounded-md bg-red-100 px-2 py-1 text-[10px] font-bold text-red-700">
                    Terlambat
                </span>
            )}
            <p className="text-xs font-semibold text-slate-600">{task.subject} -</p>
            <h3 className="mb-2 mt-0.5 pr-16 text-sm font-extrabold text-slate-900">
                {task.title}
            </h3>
            <p className="text-xs font-medium text-slate-700">
                Tenggat: {task.due}
                {isLate && (
                    <span className="ml-1.5 inline-block rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-bold text-white">
                        Terlambat
                    </span>
                )}
            </p>
        </article>
    );
}

function SubjectCard({ subject }) {
    return (
        <button
            type="button"
            className="flex min-h-24 flex-col justify-between rounded-2xl border border-slate-200 bg-white px-3 py-3 text-left shadow-[0_4px_12px_rgba(15,23,42,0.04)] transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2"
        >
            <span className="flex items-center gap-2">
                <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] text-white ${subject.color}`}>
                    <Icon name={subject.icon} className="h-[18px] w-[18px]" />
                </span>
                <span className="text-[13px] font-bold leading-tight text-slate-900">
                    {subject.name}
                </span>
            </span>
            <span className="mt-2 block text-[11px] font-semibold leading-4 text-slate-700">
                {subject.tasks} Tugas Belum Selesai
            </span>
        </button>
    );
}

export default function Dashboard() {
    const user = usePage().props.auth?.user;
    const [activeTab, setActiveTab] = useState('deadline');
    const [query, setQuery] = useState('');
    const userName = user?.name ?? 'Student Name';
    const initials = userName
        .split(' ')
        .slice(0, 2)
        .map((name) => name[0])
        .join('')
        .toUpperCase();

    const filteredItems = useMemo(() => {
        const normalizedQuery = query.trim().toLowerCase();

        if (activeTab === 'mapel') {
            return subjects.filter((subject) =>
                subject.name.toLowerCase().includes(normalizedQuery),
            );
        }

        return activities[activeTab].filter((task) =>
            `${task.subject} ${task.title} ${task.due}`
                .toLowerCase()
                .includes(normalizedQuery),
        );
    }, [activeTab, query]);

    return (
        <>
            <Head title="Beranda Siswa" />

            <main className="flex min-h-screen justify-center bg-[#DCEAF7] sm:items-center sm:px-5 sm:py-6">
                <section className="relative flex h-screen min-h-0 w-full max-w-[412px] flex-col overflow-hidden bg-[#F4F9FD] shadow-[0_20px_40px_rgba(15,23,42,0.15)] sm:h-[890px] sm:rounded-[36px]">
                    <div className="flex-1 overflow-y-auto pb-24">
                        <header className="bg-gradient-to-b from-[#74C1FF] via-[#A9DCFF] to-[#F4F9FD] px-5 pb-3 pt-6">
                            <div className="mb-5 flex items-center justify-between gap-4">
                                <div className="flex min-w-0 items-center gap-3">
                                    <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 border-white bg-gradient-to-br from-blue-600 to-sky-400 text-sm font-extrabold text-white shadow-sm">
                                        {initials}
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-blue-900/60">
                                            Selamat datang
                                        </p>
                                        <p className="truncate text-base font-extrabold text-slate-900">
                                            {userName}
                                        </p>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    aria-label="Lihat notifikasi"
                                    className="relative rounded-full p-2 text-blue-700 transition hover:bg-white/40 focus:outline-none focus:ring-2 focus:ring-blue-700"
                                >
                                    <Icon name="bell" className="h-6 w-6" />
                                    <span className="absolute right-2 top-2 h-2.5 w-2.5 rounded-full border-2 border-white bg-red-500" />
                                </button>
                            </div>

                            <h1 className="mb-3 text-lg font-extrabold text-slate-800">Ringkasan</h1>
                            <div className="grid grid-cols-3 gap-2.5">
                                {summaries.map((summary) => (
                                    <SummaryCard key={summary.label} {...summary} />
                                ))}
                            </div>
                        </header>

                        <div className="px-5">
                            <label className="block rounded-2xl bg-[#60B7FD] p-2 shadow-sm">
                                <span className="sr-only">Cari tugas, ujian, atau mata pelajaran</span>
                                <span className="flex items-center gap-2.5 rounded-xl bg-white px-3.5 py-2.5">
                                    <Icon name="search" className="h-[18px] w-[18px] shrink-0 text-slate-400" />
                                    <input
                                        type="search"
                                        value={query}
                                        onChange={(event) => setQuery(event.target.value)}
                                        placeholder="Cari tugas, ujian, atau mapel..."
                                        className="w-full border-0 bg-transparent p-0 text-[13px] text-slate-700 placeholder:text-slate-400 focus:border-0 focus:ring-0"
                                    />
                                </span>
                            </label>
                        </div>

                        <section className="mx-5 mt-4 min-h-[390px] rounded-[20px] bg-white px-4 py-[18px] shadow-[0_8px_24px_rgba(15,23,42,0.05)]">
                            <div className="mb-[18px] flex border-b border-slate-200" role="tablist" aria-label="Aktivitas siswa">
                                {tabs.map((tab) => {
                                    const isActive = activeTab === tab.id;

                                    return (
                                        <button
                                            key={tab.id}
                                            type="button"
                                            role="tab"
                                            aria-selected={isActive}
                                            onClick={() => setActiveTab(tab.id)}
                                            className={`relative flex-1 pb-2.5 text-sm font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 ${
                                                isActive ? 'text-[#1D58A7]' : 'text-slate-500 hover:text-slate-700'
                                            }`}
                                        >
                                            {tab.label}
                                            {isActive && (
                                                <span className="absolute -bottom-px left-[10%] right-[10%] h-[3px] rounded-t bg-[#1D58A7]" />
                                            )}
                                        </button>
                                    );
                                })}
                            </div>

                            {filteredItems.length === 0 ? (
                                <div className="flex min-h-56 flex-col items-center justify-center gap-2 text-center">
                                    <span className="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                        <Icon name="search" className="h-5 w-5" />
                                    </span>
                                    <p className="text-sm font-bold text-slate-700">Tidak ada hasil</p>
                                    <p className="text-xs text-slate-500">Coba gunakan kata kunci lain.</p>
                                </div>
                            ) : activeTab === 'mapel' ? (
                                <div className="grid grid-cols-2 gap-3">
                                    {filteredItems.map((subject) => (
                                        <SubjectCard key={subject.name} subject={subject} />
                                    ))}
                                    {!query.trim() && (
                                        <Link
                                            href={route('subjects.index')}
                                            viewTransition
                                            className="flex min-h-24 flex-col items-start justify-between rounded-2xl border border-slate-200 bg-white px-3 py-3 text-left shadow-[0_4px_12px_rgba(15,23,42,0.04)] transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2"
                                        >
                                            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-sky-100 text-violet-500">
                                                <Icon name="chevron" className="h-5 w-5" />
                                            </span>
                                            <span className="mt-2 text-[11px] font-bold leading-4 text-slate-900">
                                                Lihat Semua Mapel
                                            </span>
                                        </Link>
                                    )}
                                </div>
                            ) : (
                                <div className="flex flex-col gap-3.5">
                                    {filteredItems.map((task) => (
                                        <TaskCard
                                            key={`${task.subject}-${task.title}`}
                                            task={task}
                                            isLate={activeTab === 'susulan'}
                                        />
                                    ))}
                                </div>
                            )}
                        </section>
                    </div>

                    <nav className="absolute inset-x-0 bottom-0 z-10 flex h-[76px] items-center justify-around gap-1 rounded-b-[36px] border-t border-slate-200 bg-white px-3" aria-label="Navigasi utama">
                        {navigation.map((item) => (
                            <button
                                key={item.label}
                                type="button"
                                onClick={() => item.label === 'Mapel' && setActiveTab('mapel')}
                                className={`flex min-w-[64px] flex-col items-center gap-1 rounded-xl px-2 py-2 text-[11px] font-semibold transition focus:outline-none focus:ring-2 focus:ring-blue-600 ${
                                    item.active ? 'text-[#1D58A7]' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700'
                                }`}
                            >
                                <Icon name={item.icon} className="h-5 w-5" />
                                <span>{item.label}</span>
                            </button>
                        ))}
                        <Link
                            href={route('profile.edit')}
                            className="flex min-w-[64px] flex-col items-center gap-1 rounded-xl px-2 py-2 text-[11px] font-semibold text-slate-500 transition hover:bg-slate-50 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-600"
                        >
                            <Icon name="user" className="h-5 w-5" />
                            <span>Profil</span>
                        </Link>
                    </nav>
                </section>
            </main>
        </>
    );
}
