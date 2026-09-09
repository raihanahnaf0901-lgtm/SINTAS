import Brand from '@/Components/Brand';
import Icon from '@/Components/Icon';
import Modal from '@/Components/Modal';
import { DialogTitle } from '@headlessui/react';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function StudentLayout({ active = 'home', title, children }) {
    const user = usePage().props.auth?.user;
    const { unreadNotifications = 0, pendingInvite } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const homeHref = route(user ? 'dashboard' : 'home');
    const navigation = [
        { id: 'home', label: 'Beranda', icon: 'home', href: homeHref },
        ...(user ? [{ id: 'subjects', label: 'Mata pelajaran', icon: 'book', href: route('subjects.index') }] : []),
        ...(user ? [
            { id: 'jadwal', label: 'Jadwal pelajaran', icon: 'calendar', href: route('learning.overview', { section: 'jadwal' }) },
            { id: 'tugas', label: 'Tugas', icon: 'clipboard', href: route('learning.overview', { section: 'tugas' }) },
            { id: 'ujian', label: 'Ujian', icon: 'book', href: route('learning.overview', { section: 'ujian' }) },
            { id: 'nilai', label: user.role === 'guru' ? 'Penilaian & rekap' : 'Nilai saya', icon: 'chart', href: route('learning.overview', { section: 'nilai' }) },
            { id: 'notifications', label: 'Notifikasi', icon: 'bell', href: route('notifications.index') },
            ...(user.guru?.jenis_guru === 'guru_mapel' ? [{ id: 'master', label: 'Data sekolah', icon: 'landmark', href: route('master-data.index') }] : []),
        ] : []),
        { id: 'profile', label: user ? 'Profil saya' : 'Masuk', icon: 'user', href: route(user ? 'profile.edit' : 'login') },
    ];
    const initials = (user?.name ?? 'Siswa').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();

    function navItems(mobile = false) {
        return navigation.filter((item) => !mobile || ['home', 'subjects', 'jadwal', 'notifications', 'profile'].includes(item.id)).map((item) => (
            <Link key={item.id} href={item.href} aria-current={active === item.id ? 'page' : undefined}
                onClick={() => setMenuOpen(false)}
                className={`flex items-center gap-3 rounded-xl font-semibold transition ${mobile ? 'min-h-[52px] flex-1 flex-col justify-center gap-1 px-2 py-2 text-[10px]' : 'px-4 py-3.5 text-sm'} ${active === item.id ? 'bg-teal-50 text-teal-800' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'}`}>
                <Icon name={item.icon} /><span>{item.label}</span>
                {!mobile && active === item.id && <span className="ml-auto h-1.5 w-1.5 rounded-full bg-teal-600" />}
            </Link>
        ));
    }

    return (
        <div className="min-h-screen bg-[#F5F7F9]">
            <a href="#main-content" className="sr-only z-50 rounded-lg bg-white p-4 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Lewati ke konten</a>
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col overflow-y-auto border-r border-slate-200/80 bg-white px-5 py-8 lg:flex xl:w-64">
                <Link href={homeHref} className="px-3"><Brand /></Link>
                <p className="eyebrow mb-4 mt-8 px-4">{user?.role === 'guru' ? 'Ruang mengajar' : 'Ruang belajar'}</p>
                <nav aria-label="Navigasi utama" className="flex flex-col gap-2">{navItems()}</nav>
                <div className="mt-8 rounded-2xl bg-[#F1F7F6] p-5">
                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-teal-700"><Icon name="sparkles" /></span>
                    <p className="mt-4 text-sm font-bold text-slate-800">Sedikit demi sedikit,<br />jadi lebih baik.</p>
                    <p className="mt-2 text-xs leading-6 text-slate-500">Satu tugas selesai, satu langkah lebih dekat ke tujuanmu.</p>
                </div>
                <p className="mt-6 px-3 text-[10px] text-slate-400">SINTAS · Teman belajar setiap hari</p>
            </aside>
            <div className="lg:pl-60 xl:pl-64">
                <header className="border-b border-slate-200/80 bg-white/90">
                    <div className="mx-auto flex min-h-[88px] max-w-7xl items-center justify-between gap-4 px-5 sm:px-8 xl:px-10">
                        <div className="flex items-center gap-3 lg:hidden"><button type="button" className="rounded-xl p-2 text-slate-600" aria-label="Buka semua menu" onClick={() => setMenuOpen(true)}><Icon name="menu" /></button><Link href={homeHref}><Brand /></Link></div>
                        <div className="hidden items-center gap-3 text-sm lg:flex"><span className="text-slate-400">Ruang belajar</span><Icon name="chevron" className="h-3 w-3 text-slate-400" /><span className="font-semibold text-slate-700">{title}</span></div>
                        <div className="flex items-center gap-3">{user && <Link className="relative rounded-xl p-2 text-slate-500" href={route('notifications.index')} aria-label={`Notifikasi, ${unreadNotifications} belum dibaca`}><Icon name="bell" />{unreadNotifications > 0 && <span className="absolute -right-1 -top-1 rounded-full bg-teal-700 px-1.5 py-0.5 text-[10px] text-white">{unreadNotifications > 99 ? '99+' : unreadNotifications}</span>}</Link>}<Link href={route(user ? 'profile.edit' : 'login')} className="flex min-w-0 items-center gap-3 rounded-xl p-1">
                            <span className="hidden max-w-48 text-right sm:block"><span className="block truncate text-sm font-bold text-slate-800">{user?.name ?? 'Selamat datang'}</span><span className="text-[11px] text-slate-500">{user ? 'Akun ' + (user.role === 'guru' ? 'guru' : user.role === 'admin' ? 'admin' : 'siswa') : 'Masuk ke akunmu'}</span></span>
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-4 border-white bg-teal-100 text-xs font-extrabold text-teal-800 ring-1 ring-slate-200">{initials}</span>
                        </Link></div>
                    </div>
                </header>
                <main id="main-content" className="mx-auto max-w-7xl px-5 pb-28 pt-7 sm:px-8 sm:pt-9 lg:pb-10 xl:px-10">{user?.role === 'siswa' && pendingInvite && <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800"><span>Ada undangan kelas yang bisa kamu lanjutkan.</span><Link className="font-bold underline" href={pendingInvite}>Buka undangan</Link></div>}{children}</main>
            </div>
            <nav aria-label="Navigasi seluler" className="fixed inset-x-0 bottom-0 z-40 flex gap-2 border-t border-slate-200 bg-white/95 px-4 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 backdrop-blur-lg lg:hidden">{navItems(true)}</nav>
            <Modal show={menuOpen} onClose={() => setMenuOpen(false)} maxWidth="sm"><div className="p-5"><div className="mb-4 flex items-center justify-between"><DialogTitle className="font-bold">Menu SINTAS</DialogTitle><button type="button" onClick={() => setMenuOpen(false)} className="rounded-lg p-2" aria-label="Tutup menu"><Icon name="close" /></button></div><nav aria-label="Semua menu seluler" className="flex max-h-[70vh] flex-col gap-1 overflow-y-auto">{navItems()}</nav></div></Modal>
        </div>
    );
}
