import InputError from '@/Components/InputError';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

function Icon({ name, className = 'h-5 w-5' }) {
    const paths = {
        arrowLeft: <path d="m15 18-6-6 6-6" />,
        home: <><path d="m3 11 9-9 9 9" /><path d="M5 10v11h14V10M9 21v-6h6v6" /></>,
        logout: <><path d="M10 17l5-5-5-5" /><path d="M15 12H3" /><path d="M21 19V5a2 2 0 0 0-2-2h-6" /></>,
        mail: <><rect width="20" height="16" x="2" y="4" rx="2" /><path d="m22 7-8.97 5.7a1.9 1.9 0 0 1-2.06 0L2 7" /></>,
        pencil: <><path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" /></>,
        user: <><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></>,
    };

    return (
        <svg aria-hidden="true" className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2">
            {paths[name]}
        </svg>
    );
}

export default function Edit() {
    const user = usePage().props.auth.user;
    const [showLogoutConfirmation, setShowLogoutConfirmation] = useState(false);
    const initials = user.name
        .split(' ')
        .slice(0, 2)
        .map((name) => name[0])
        .join('')
        .toUpperCase();
    const roleLabel = user.role === 'guru' ? 'Guru' : user.role === 'admin' ? 'Admin' : 'Siswa';
    const { data, setData, patch, processing, recentlySuccessful, errors } = useForm({
        name: user.name,
        email: user.email,
    });

    function submit(event) {
        event.preventDefault();
        patch(route('profile.update'));
    }

    function logout() {
        router.post(route('logout'));
    }

    return (
        <>
            <Head title="Profil Saya" />

            <main className="flex min-h-screen justify-center bg-[#DCEAF7] sm:items-center sm:px-5 sm:py-6">
                <section className="relative flex h-screen min-h-0 w-full max-w-[412px] flex-col overflow-hidden bg-[#F4F9FD] shadow-[0_20px_40px_rgba(15,23,42,0.15)] sm:h-[890px] sm:rounded-[36px]">
                    <div className="flex-1 overflow-y-auto pb-24">
                        <header className="bg-gradient-to-b from-[#74C1FF] via-[#A9DCFF] to-[#F4F9FD] px-5 pb-8 pt-6">
                            <div className="flex items-center justify-between">
                                <Link href={route('dashboard')} aria-label="Kembali ke beranda" className="rounded-full p-2 text-blue-800 transition hover:bg-white/40 focus:outline-none focus:ring-2 focus:ring-blue-700">
                                    <Icon name="arrowLeft" className="h-6 w-6" />
                                </Link>
                                <h1 className="text-base font-extrabold text-slate-900">Profil Saya</h1>
                                <span className="h-10 w-10" />
                            </div>

                            <div className="mt-5 flex flex-col items-center text-center">
                                <div className="flex h-24 w-24 items-center justify-center rounded-full border-4 border-white bg-gradient-to-br from-blue-600 to-sky-400 text-2xl font-extrabold text-white shadow-lg">
                                    {initials}
                                </div>
                                <h2 className="mt-3 text-xl font-extrabold text-slate-900">{user.name}</h2>
                                <span className="mt-1 rounded-full bg-white/70 px-3 py-1 text-xs font-bold text-blue-800">{roleLabel}</span>
                            </div>
                        </header>

                        <form onSubmit={submit} className="mx-5 -mt-2 rounded-[22px] bg-white p-5 shadow-[0_8px_24px_rgba(15,23,42,0.08)]">
                            <div className="flex items-center gap-3">
                                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-100 text-sky-700"><Icon name="pencil" /></span>
                                <div>
                                    <h3 className="text-base font-extrabold text-slate-900">Informasi Akun</h3>
                                    <p className="text-xs text-slate-500">Perbarui data profilmu di sini.</p>
                                </div>
                            </div>

                            <div className="mt-5 space-y-4">
                                <label className="block">
                                    <span className="mb-1.5 block text-sm font-bold text-slate-700">Nama lengkap</span>
                                    <input id="name" value={data.name} onChange={(event) => setData('name', event.target.value)} className="input" required autoComplete="name" />
                                    <InputError message={errors.name} className="mt-1.5" />
                                </label>
                                <label className="block">
                                    <span className="mb-1.5 block text-sm font-bold text-slate-700">Email</span>
                                    <span className="flex items-center rounded-lg border border-slate-300 bg-white shadow-sm focus-within:border-sky-500 focus-within:ring-1 focus-within:ring-sky-500">
                                        <Icon name="mail" className="ml-3 h-4 w-4 shrink-0 text-slate-400" />
                                        <input id="email" type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} className="w-full border-0 bg-transparent px-3 py-2.5 text-sm text-slate-900 focus:ring-0" required autoComplete="username" />
                                    </span>
                                    <InputError message={errors.email} className="mt-1.5" />
                                </label>
                            </div>

                            <button type="submit" disabled={processing} className="mt-6 w-full rounded-xl bg-[#1D58A7] px-4 py-3 text-sm font-extrabold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                                {processing ? 'Menyimpan…' : 'Simpan Perubahan'}
                            </button>
                            {recentlySuccessful && <p className="mt-3 text-center text-sm font-semibold text-emerald-600">Perubahan berhasil disimpan.</p>}
                        </form>

                        <section className="mx-5 mt-4 rounded-[22px] border border-red-100 bg-white p-4 shadow-[0_4px_12px_rgba(15,23,42,0.04)]">
                            <h3 className="text-sm font-extrabold text-slate-900">Keluar dari akun</h3>
                            <p className="mt-1 text-xs leading-5 text-slate-500">Kamu dapat masuk lagi kapan saja menggunakan data akunmu.</p>
                            <button type="button" onClick={() => setShowLogoutConfirmation(true)} className="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-red-50 px-4 py-2.5 text-sm font-bold text-red-600 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                <Icon name="logout" className="h-4 w-4" /> Keluar
                            </button>
                        </section>
                    </div>

                    <nav className="absolute inset-x-0 bottom-0 z-10 flex h-[76px] items-center justify-around gap-1 rounded-b-[36px] border-t border-slate-200 bg-white px-4" aria-label="Navigasi utama">
                        <Link href={route('dashboard')} className="flex min-w-[64px] flex-col items-center gap-1 rounded-xl px-3 py-2 text-[11px] font-semibold text-slate-500 transition hover:bg-slate-50 hover:text-[#1D58A7] focus:outline-none focus:ring-2 focus:ring-blue-600"><Icon name="home" className="h-5 w-5" /><span>Beranda</span></Link>
                        <Link href={route('profile.edit')} className="flex min-w-[64px] flex-col items-center gap-1 rounded-xl bg-blue-50 px-3 py-2 text-[11px] font-bold text-[#1D58A7] focus:outline-none focus:ring-2 focus:ring-blue-600"><Icon name="user" className="h-5 w-5" /><span>Profil</span></Link>
                    </nav>
                </section>
            </main>

            {showLogoutConfirmation && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 px-5" role="dialog" aria-modal="true" aria-labelledby="logout-confirmation-title">
                    <div className="w-full max-w-sm rounded-[24px] bg-white p-6 text-center shadow-2xl">
                        <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600">
                            <Icon name="logout" className="h-7 w-7" />
                        </span>
                        <h2 id="logout-confirmation-title" className="mt-4 text-lg font-extrabold text-slate-900">Keluar dari akun?</h2>
                        <p className="mt-2 text-sm leading-6 text-slate-600">Apakah Anda yakin ingin keluar? Kamu perlu masuk kembali untuk mengakses SINTAS.</p>
                        <div className="mt-6 flex gap-3">
                            <button type="button" onClick={() => setShowLogoutConfirmation(false)} className="flex-1 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">Batal</button>
                            <button type="button" onClick={logout} className="flex-1 rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Ya, Keluar</button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
