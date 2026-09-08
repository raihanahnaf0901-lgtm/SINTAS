import Brand from '@/Components/Brand';
import Icon from '@/Components/Icon';
import { Head, Link } from '@inertiajs/react';

export default function Welcome() {
    return (
        <div className="flex min-h-screen w-full min-w-0 flex-col bg-[#F5F7F9]">
            <Head title="Selamat datang di SINTAS" />
            <header className="border-b border-slate-200/80 bg-white">
                <nav aria-label="Navigasi utama" className="mx-auto flex w-full min-w-0 max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 sm:px-8">
                    <Link href={route('home')} className="min-w-0 max-w-full"><Brand /></Link>
                    <Link href={route('login')} className="ml-auto shrink-0 rounded-xl px-3 py-2 text-sm font-bold text-teal-700 transition hover:bg-teal-50 sm:px-4">Masuk</Link>
                </nav>
            </header>
            <main className="mx-auto flex w-full min-w-0 max-w-6xl flex-1 items-center px-4 py-10 sm:px-8 sm:py-16">
                <section className="hero-pattern relative grid w-full min-w-0 grid-cols-1 items-center gap-10 rounded-3xl bg-[#142E36] p-5 text-white sm:p-10 lg:grid-cols-[minmax(0,1fr)_280px] lg:p-14">
                    <div className="relative z-10 min-w-0 [overflow-wrap:anywhere]">
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-teal-200">Selamat datang di SINTAS</p>
                        <h1 className="mt-5 text-2xl font-extrabold leading-tight tracking-tight sm:text-4xl lg:text-5xl">Belajar lebih terarah.<br /><span className="text-teal-200">Mulai dari sini.</span></h1>
                        <p className="mt-6 max-w-lg text-sm leading-7 text-slate-300">Masuk ke akunmu untuk melihat tugas, ujian, mata pelajaran, dan progres belajar. Semua informasi belajarmu tersedia setelah login.</p>
                        <Link href={route('login')} className="mt-8 flex w-full min-w-0 items-center justify-center gap-2 rounded-xl bg-white px-3 py-3.5 text-sm font-bold text-teal-900 transition hover:bg-teal-50 sm:inline-flex sm:w-auto sm:gap-3 sm:px-5"><span className="min-w-0">Masuk untuk melanjutkan</span><Icon name="arrow" className="h-4 w-4 shrink-0" /></Link>
                        <p className="mt-4 text-xs leading-6 text-slate-300">Gunakan akun siswa, guru, atau admin yang sudah terdaftar.</p>
                    </div>
                    <div aria-hidden="true" className="relative hidden aspect-square items-center justify-center lg:flex">
                        <div className="absolute inset-0 rounded-full border border-teal-200/15" />
                        <div className="absolute inset-7 rounded-full border border-teal-200/20 bg-teal-200/5" />
                        <span className="flex h-32 w-32 -rotate-6 items-center justify-center rounded-3xl border border-teal-200/20 bg-teal-200/10 text-teal-200"><Icon name="book" className="h-16 w-16" /></span>
                        <span className="absolute bottom-8 right-7 flex h-16 w-16 rotate-12 items-center justify-center rounded-2xl bg-[#F4C77D] text-amber-950"><Icon name="lock" className="h-8 w-8" /></span>
                    </div>
                </section>
            </main>
            <footer className="px-5 pb-7 text-center text-xs text-slate-500">SINTAS · Teman belajar setiap hari</footer>
        </div>
    );
}
