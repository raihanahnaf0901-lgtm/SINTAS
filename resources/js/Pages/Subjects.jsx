import { Head, Link } from '@inertiajs/react';

const subjects = [
    { id: 1, name: 'Matematika', teacher: 'Pak Budi Santoso', completed: 12, total: 15, icon: 'calculator', color: 'from-purple-500 to-purple-600', href: route('subjects.show') },
    { id: 2, name: 'Bahasa Indonesia', teacher: 'Pak Budi Santoso', completed: 12, total: 15, icon: 'book', color: 'from-orange-400 to-orange-600' },
    { id: 3, name: 'Fisika', teacher: 'Pak Budi Santoso', completed: 12, total: 15, icon: 'atom', color: 'from-sky-400 to-sky-600' },
    { id: 4, name: 'Biologi', teacher: 'Pak Budi Santoso', completed: 12, total: 15, icon: 'dna', color: 'from-green-400 to-green-600' },
    { id: 5, name: 'Sejarah', teacher: 'Pak Budi Santoso', completed: 12, total: 15, icon: 'landmark', color: 'from-yellow-400 to-yellow-500' },
    { id: 6, name: 'Ekonomi', teacher: 'Pak Budi Santoso', completed: 12, total: 15, icon: 'coins', color: 'from-red-400 to-red-600' },
    { id: 7, name: 'Kimia', teacher: 'Pak Budi Santoso', completed: 12, total: 15, icon: 'flask', color: 'from-purple-400 to-purple-600' },
    { id: 8, name: 'Geografi', teacher: 'Pak Budi Santoso', completed: 12, total: 15, icon: 'globe', color: 'from-sky-400 to-sky-500' },
    { id: 9, name: 'Ekonomi', teacher: 'Pak Budi Santoso', completed: 12, total: 10, icon: 'dollar', color: 'from-emerald-400 to-emerald-600' },
    { id: 10, name: 'Seni Budaya', teacher: 'Pak Budi Santoso', completed: 12, total: 15, icon: 'palette', color: 'from-rose-400 to-rose-600' },
    { id: 11, name: 'Penjasorkes', teacher: 'Pak Budi Santoso', completed: 12, total: 10, icon: 'running', color: 'from-orange-400 to-orange-500' },
    { id: 12, name: 'Feografi', teacher: 'Pak Budi Santoso', completed: 12, total: 15, icon: 'pen', color: 'from-blue-400 to-blue-600' },
];

const navigation = [
    { label: 'Beranda', icon: 'home', href: route('home') },
    { label: 'Jadwal', icon: 'calendar' },
    { label: 'Mapel', icon: 'book', href: route('subjects.index'), active: true },
    { label: 'Akun', icon: 'user', href: route('profile.edit') },
];

function Icon({ name, className = 'h-5 w-5' }) {
    const paths = {
        arrowLeft: <path d="m15 18-6-6 6-6M9 12h10" />,
        atom: (
            <>
                <circle cx="12" cy="12" r="1" />
                <path d="M20.2 20.2c2-2-.3-7.6-5.2-12.5S4.6.5 2.5 2.5c-2 2 .3 7.6 5.2 12.5s10.5 7.2 12.5 5.2ZM15 15c4.9-4.9 7.2-10.5 5.2-12.5C18.2.5 12.6 2.8 7.7 7.7S.5 18.2 2.5 20.2c2 2 7.6-.3 12.5-5.2Z" />
            </>
        ),
        book: (
            <>
                <path d="M3 4h6a3 3 0 0 1 3 3v14a3 3 0 0 0-3-3H3ZM21 4h-6a3 3 0 0 0-3 3v14a3 3 0 0 1 3-3h6Z" />
            </>
        ),
        calculator: (
            <>
                <rect x="4" y="2" width="16" height="20" rx="2" />
                <path d="M8 6h8M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01" />
            </>
        ),
        calendar: (
            <>
                <path d="M8 2v4M16 2v4M3 10h18" />
                <rect width="18" height="18" x="3" y="4" rx="2" />
            </>
        ),
        coins: (
            <>
                <ellipse cx="9" cy="7" rx="6" ry="3" />
                <path d="M3 7v4c0 1.7 2.7 3 6 3s6-1.3 6-3V7M6 14v3c0 1.7 2.7 3 6 3s6-1.3 6-3v-4M15 10c3.3 0 6 1.3 6 3s-2.7 3-6 3" />
            </>
        ),
        dna: (
            <>
                <path d="M2 15c6.7 0 13.3-6 20-6M2 9c6.7 0 13.3 6 20 6" />
                <path d="M6 8v8M10 9.5v5M14 9.5v5M18 8v8" />
            </>
        ),
        dollar: (
            <>
                <path d="M12 2v20M17 6.5A4 4 0 0 0 13.5 5h-3a3.5 3.5 0 0 0 0 7h3a3.5 3.5 0 0 1 0 7h-3A4 4 0 0 1 7 17.5" />
            </>
        ),
        flask: <path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.8 3h10.4A2 2 0 0 0 19 18l-5-9V3M8 14h8" />,
        globe: (
            <>
                <circle cx="12" cy="12" r="9" />
                <path d="M3 12h18M12 3c2.5 2.5 4 5.5 4 9s-1.5 6.5-4 9c-2.5-2.5-4-5.5-4-9s1.5-6.5 4-9Z" />
            </>
        ),
        home: (
            <>
                <path d="m3 11 9-9 9 9" />
                <path d="M5 10v11h14V10M9 21v-6h6v6" />
            </>
        ),
        landmark: <path d="m3 10 9-6 9 6M5 10h14M6 10v8M10 10v8M14 10v8M18 10v8M4 18h16M3 22h18" />,
        palette: (
            <>
                <path d="M12 3a9 9 0 1 0 0 18h1.5a1.5 1.5 0 0 0 0-3H12a2 2 0 0 1 0-4h2a7 7 0 0 0 0-14Z" />
                <path d="M7.5 10h.01M9 6.5h.01M14 6h.01M17 9h.01" />
            </>
        ),
        pen: <path d="m4 20 4.5-1 10-10a2.1 2.1 0 0 0-3-3l-10 10L4 20ZM13.5 8l3 3" />,
        running: <path d="M13 5a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM8 22l3-6 2 2v4M6 12l3-4 4 2 3 4 4 1M9 8l-2 7-4 3" />,
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

function SubjectCard({ subject }) {
    const progress = Math.min(100, Math.round((subject.completed / subject.total) * 100));
    const className = 'flex min-h-[108px] flex-col justify-between rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-[0_4px_12px_rgba(15,23,42,0.04)]';
    const content = (
        <>
            <div className="flex items-start gap-2">
                <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-gradient-to-br text-white ${subject.color}`}>
                    <Icon name={subject.icon} className="h-[18px] w-[18px]" />
                </span>
                <span className="min-w-0">
                    <h2 className="text-[13px] font-bold leading-tight text-slate-900">{subject.name}</h2>
                    <span className="mt-0.5 block text-[10px] font-medium leading-tight text-slate-600">
                        {subject.teacher}
                    </span>
                </span>
            </div>

            <div className="mt-2.5">
                <p className="mb-1 text-[11px] font-semibold text-slate-700">
                    {subject.completed}/{subject.total} Tugas Selesai
                </p>
                <div className="h-1.5 overflow-hidden rounded-full bg-slate-200">
                    <div
                        className="h-full rounded-full bg-[#4FA8E0]"
                        style={{ width: `${progress}%` }}
                    />
                </div>
            </div>
        </>
    );

    if (subject.href) {
        return (
            <Link
                href={subject.href}
                viewTransition
                className={`${className} transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2`}
            >
                {content}
            </Link>
        );
    }

    return (
        <article className={className}>
            {content}
        </article>
    );
}

export default function Subjects() {
    return (
        <>
            <Head title="Daftar Mata Pelajaran" />

            <main className="flex min-h-screen justify-center bg-[#E5EDF5] sm:items-center sm:px-5 sm:py-6">
                <section className="relative flex h-screen min-h-0 w-full max-w-[414px] flex-col overflow-hidden bg-gradient-to-b from-[#70C2FF] via-[#B2DEFF] to-[#F4F8FC] shadow-[0_12px_35px_rgba(0,0,0,0.15)] sm:h-[896px] sm:rounded-[36px]">
                    <header className="flex items-center gap-4 px-5 pb-[18px] pt-6">
                        <Link
                            href={route('home')}
                            aria-label="Kembali"
                            viewTransition
                            className="flex h-9 w-9 items-center justify-center rounded-full text-slate-900 transition hover:bg-white/40 focus:outline-none focus:ring-2 focus:ring-blue-700"
                        >
                            <Icon name="arrowLeft" className="h-5 w-5" />
                        </Link>
                        <h1 className="text-xl font-bold text-slate-900">Daftar Mata Pelajaran</h1>
                    </header>

                    <div className="flex-1 overflow-y-auto px-3.5 pb-24">
                        <section className="min-h-full rounded-[24px] bg-white px-3 py-4 shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                            <div className="grid grid-cols-2 gap-3">
                                {subjects.map((subject) => (
                                    <SubjectCard key={subject.id} subject={subject} />
                                ))}
                            </div>
                        </section>
                    </div>

                    <nav className="absolute inset-x-0 bottom-0 z-10 flex h-[76px] items-center justify-around gap-1 border-t border-slate-200 bg-white px-3 sm:rounded-b-[36px]" aria-label="Navigasi utama">
                        {navigation.map((item) => {
                            const className = `flex min-w-[64px] flex-col items-center gap-1 rounded-xl px-2 py-2 text-[11px] font-semibold transition focus:outline-none focus:ring-2 focus:ring-blue-600 ${
                                item.active ? 'text-sky-600' : 'text-slate-400 hover:bg-slate-50 hover:text-slate-700'
                            }`;

                            if (!item.href) {
                                return (
                                    <button key={item.label} type="button" className={className}>
                                        <Icon name={item.icon} className="h-5 w-5" />
                                        <span>{item.label}</span>
                                    </button>
                                );
                            }

                            return (
                                <Link key={item.label} href={item.href} className={className} viewTransition>
                                    <Icon name={item.icon} className="h-5 w-5" />
                                    <span>{item.label}</span>
                                </Link>
                            );
                        })}
                    </nav>
                </section>
            </main>
        </>
    );
}
