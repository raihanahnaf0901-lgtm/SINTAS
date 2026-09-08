import Brand from '@/Components/Brand';
import Icon from '@/Components/Icon';
import { Link, usePage } from '@inertiajs/react';

export default function StudentLayout({ active = 'home', title, children }) {
    const user = usePage().props.auth?.user;
    const homeHref = route(user ? 'dashboard' : 'home');
    const navigation = [
        { id: 'home', label: 'Beranda', icon: 'home', href: homeHref },
        ...(user ? [{ id: 'subjects', label: 'Mata pelajaran', icon: 'book', href: route('subjects.index') }] : []),
        { id: 'profile', label: user ? 'Profil saya' : 'Masuk', icon: 'user', href: route(user ? 'profile.edit' : 'login') },
    ];
    const initials = (user?.name ?? 'Siswa').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();

    function navItems(mobile = false) {
        return navigation.map((item) => (
            <Link key={item.id} href={item.href} aria-current={active === item.id ? 'page' : undefined}
                className={`flex items-center gap-3 rounded-xl font-semibold transition ${mobile ? 'min-h-[52px] flex-1 flex-col justify-center gap-1 px-2 py-2 text-[10px]' : 'px-4 py-3.5 text-sm'} ${active === item.id ? 'bg-teal-50 text-teal-800' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'}`}>
                <Icon name={item.icon} /><span>{item.label}</span>
                {!mobile && active === item.id && <span className="ml-auto h-1.5 w-1.5 rounded-full bg-teal-600" />}
            </Link>
        ));
    }

    return (
        <div className="min-h-screen bg-[#F5F7F9]">
            <a href="#main-content" className="sr-only z-50 rounded-lg bg-white p-4 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Lewati ke konten</a>
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col border-r border-slate-200/80 bg-white px-5 py-8 lg:flex xl:w-64">
                <Link href={homeHref} className="px-3"><Brand /></Link>
                <p className="eyebrow mb-4 mt-12 px-4">Ruang belajar</p>
                <nav aria-label="Navigasi utama" className="flex flex-col gap-2">{navItems()}</nav>
                <div className="mt-auto rounded-2xl bg-[#F1F7F6] p-5">
                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-teal-700"><Icon name="sparkles" /></span>
                    <p className="mt-4 text-sm font-bold text-slate-800">Sedikit demi sedikit,<br />jadi lebih baik.</p>
                    <p className="mt-2 text-xs leading-6 text-slate-500">Satu tugas selesai, satu langkah lebih dekat ke tujuanmu.</p>
                </div>
                <p className="mt-6 px-3 text-[10px] text-slate-400">SINTAS · Teman belajar setiap hari</p>
            </aside>
            <div className="lg:pl-60 xl:pl-64">
                <header className="border-b border-slate-200/80 bg-white/90">
                    <div className="mx-auto flex min-h-[88px] max-w-7xl items-center justify-between gap-4 px-5 sm:px-8 xl:px-10">
                        <div className="lg:hidden"><Link href={homeHref}><Brand /></Link></div>
                        <div className="hidden items-center gap-3 text-sm lg:flex"><span className="text-slate-400">Ruang belajar</span><Icon name="chevron" className="h-3 w-3 text-slate-400" /><span className="font-semibold text-slate-700">{title}</span></div>
                        <Link href={route(user ? 'profile.edit' : 'login')} className="flex min-w-0 items-center gap-3 rounded-xl p-1">
                            <span className="hidden max-w-48 text-right sm:block"><span className="block truncate text-sm font-bold text-slate-800">{user?.name ?? 'Selamat datang'}</span><span className="text-[11px] text-slate-500">{user ? 'Akun ' + (user.role === 'guru' ? 'guru' : user.role === 'admin' ? 'admin' : 'siswa') : 'Masuk ke akunmu'}</span></span>
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-4 border-white bg-teal-100 text-xs font-extrabold text-teal-800 ring-1 ring-slate-200">{initials}</span>
                        </Link>
                    </div>
                </header>
                <main id="main-content" className="mx-auto max-w-7xl px-5 pb-28 pt-7 sm:px-8 sm:pt-9 lg:pb-10 xl:px-10">{children}</main>
            </div>
            <nav aria-label="Navigasi seluler" className="fixed inset-x-0 bottom-0 z-40 flex gap-2 border-t border-slate-200 bg-white/95 px-4 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 backdrop-blur-lg lg:hidden">{navItems(true)}</nav>
        </div>
    );
}
