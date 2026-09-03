import { Head, Link, usePage } from '@inertiajs/react';

const navigation = [
    { label: 'Beranda', icon: 'home', href: route('home') },
    { label: 'Jadwal', icon: 'calendar' },
    { label: 'Mapel', icon: 'book', href: route('subjects.index'), active: true },
    { label: 'Akun', icon: 'user', href: route('profile.edit') },
];

function Icon({ name, className = 'h-5 w-5' }) {
    const paths = {
        arrowLeft: <path d="m15 18-6-6 6-6M9 12h10" />,
        bell: (
            <>
                <path d="M10.3 21a2 2 0 0 0 3.4 0" />
                <path d="M3.3 15.3A1 1 0 0 0 4 17h16a1 1 0 0 0 .7-1.7C19.4 13.9 18 12.5 18 8A6 6 0 0 0 6 8c0 4.5-1.4 5.9-2.7 7.3Z" />
            </>
        ),
        book: <path d="M3 4h6a3 3 0 0 1 3 3v14a3 3 0 0 0-3-3H3ZM21 4h-6a3 3 0 0 0-3 3v14a3 3 0 0 1 3-3h6Z" />,
        calendar: (
            <>
                <path d="M8 2v4M16 2v4M3 10h18" />
                <rect width="18" height="18" x="3" y="4" rx="2" />
            </>
        ),
        check: (
            <>
                <circle cx="12" cy="12" r="9" />
                <path d="m8 12 2.5 2.5L16 9" />
            </>
        ),
        home: (
            <>
                <path d="m3 11 9-9 9 9" />
                <path d="M5 10v11h14V10M9 21v-6h6v6" />
            </>
        ),
        user: (
            <>
                <circle cx="12" cy="8" r="4" />
                <path d="M4 21a8 8 0 0 1 16 0" />
            </>
        ),
        x: (
            <>
                <circle cx="12" cy="12" r="9" />
                <path d="m9 9 6 6M15 9l-6 6" />
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

function TaskCard({ task }) {
    const color = task.completed ? 'border-green-500 text-green-500' : 'border-red-500 text-red-500';

    return (
        <article className="relative pl-[42px]">
            <span className="absolute left-[11px] top-1/2 z-10 h-[13px] w-[13px] -translate-y-1/2 rounded-full bg-[#8DA4BE]" />
            <div className={`flex items-start justify-between gap-3 rounded-[14px] border-2 bg-white px-4 py-3.5 shadow-[0_4px_12px_rgba(0,0,0,0.05)] ${color}`}>
                <div className="flex min-w-0 flex-col gap-[3px] text-slate-800">
                    <h2 className="mb-1 text-base font-bold text-slate-900">{task.title}</h2>
                    {task.details.map((detail) => (
                        <p key={detail} className="text-[13px] font-semibold leading-[1.35]">
                            {detail}
                        </p>
                    ))}
                </div>
                <Icon name={task.completed ? 'check' : 'x'} className="h-6 w-6 shrink-0" />
            </div>
        </article>
    );
}

export default function SubjectTasks({ subject, tasks }) {
    const userName = usePage().props.auth?.user?.name ?? 'Student Name';
    const initials = userName
        .split(' ')
        .slice(0, 2)
        .map((name) => name[0])
        .join('')
        .toUpperCase();

    return (
        <>
            <Head title={`${subject.name} - Tugas`} />

            <main className="flex min-h-screen justify-center bg-[#E5EDF5] sm:items-center sm:px-5 sm:py-6">
                <section className="relative flex h-screen min-h-0 w-full max-w-[414px] flex-col overflow-hidden bg-[#EDF4FA] shadow-[0_12px_35px_rgba(0,0,0,0.15)] sm:h-[896px] sm:rounded-[36px]">
                    <header className="z-10 rounded-b-[32px] bg-gradient-to-br from-[#42A5F5] via-[#3378DC] to-[#303F9F] px-5 pb-7 pt-6 text-white shadow-[0_6px_18px_rgba(48,63,159,0.25)]">
                        <div className="mb-[22px] flex items-center justify-between gap-4">
                            <Link
                                href={route('subjects.index')}
                                aria-label="Kembali ke daftar mata pelajaran"
                                viewTransition
                                className="flex h-9 w-9 items-center justify-center rounded-full transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white"
                            >
                                <Icon name="arrowLeft" className="h-6 w-6" />
                            </Link>

                            <div className="flex items-center gap-3.5">
                                <div className="flex h-[38px] w-[38px] items-center justify-center rounded-full border-2 border-white/80 bg-white/20 text-xs font-extrabold">
                                    {initials}
                                </div>
                                <button
                                    type="button"
                                    aria-label="Notifikasi"
                                    className="relative rounded-full p-1 transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white"
                                >
                                    <Icon name="bell" className="h-6 w-6" />
                                    <span className="absolute right-0 top-0 h-2.5 w-2.5 rounded-full border-2 border-white bg-red-500" />
                                </button>
                            </div>
                        </div>

                        <h1 className="text-[27px] font-bold tracking-tight">{subject.name}</h1>
                        <p className="mt-1 text-xs font-medium text-white/75">{subject.teacher}</p>
                    </header>

                    <div className="flex-1 overflow-y-auto px-4 pb-24 pt-6">
                        <div className="relative flex flex-col gap-[22px]">
                            <span className="absolute bottom-0 left-[16px] top-0 w-[3px] rounded-full bg-[#8DA4BE]" />
                            {tasks.map((task) => (
                                <TaskCard key={task.id} task={task} />
                            ))}
                        </div>
                    </div>

                    <nav className="absolute inset-x-0 bottom-0 z-20 flex h-[76px] items-center justify-around gap-1 border-t border-slate-200 bg-white px-3 sm:rounded-b-[36px]" aria-label="Navigasi utama">
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
