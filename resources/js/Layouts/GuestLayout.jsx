import Brand from '@/Components/Brand';
import Icon from '@/Components/Icon';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="grid min-h-screen bg-[#F5F7F9] lg:grid-cols-2">
            <aside className="hero-pattern relative hidden flex-col justify-between overflow-hidden bg-[#142E36] p-12 text-white lg:flex xl:p-16">
                <Link href={route('home')} className="relative z-10 self-start"><Brand light /></Link>
                <div className="relative z-10 py-16">
                    <span className="inline-flex rounded-2xl border border-teal-200/20 bg-teal-200/10 p-4 text-teal-200"><Icon name="sparkles" className="h-8 w-8" /></span>
                    <h2 className="mt-8 text-4xl font-bold leading-tight tracking-tight xl:text-5xl">Langkah kecil.<br /><span className="text-teal-200">Masa depan besar.</span></h2>
                    <p className="mt-5 max-w-sm text-sm leading-7 text-slate-300">Kelola tugas, ikuti progres, dan bangun kebiasaan belajar yang lebih baik bersama SINTAS.</p>
                    <div className="mt-10 flex flex-col gap-4">{['Semua tugas dalam satu tempat', 'Progres belajar lebih terarah', 'Mudah diakses, di mana saja'].map((text) => <p key={text} className="flex items-center gap-3 text-sm text-slate-200"><span className="rounded-full bg-teal-300/10 p-1 text-teal-200"><Icon name="check" className="h-3 w-3" /></span>{text}</p>)}</div>
                </div>
                <p className="relative z-10 text-xs text-slate-400">SINTAS · Teman belajar setiap hari</p>
                <div aria-hidden="true" className="absolute -bottom-48 -right-48 h-[600px] w-[600px] rounded-full border border-white/10" />
                <div aria-hidden="true" className="absolute -bottom-24 -right-24 h-96 w-96 rounded-full border border-white/10" />
            </aside>
            <div className="flex flex-col items-center justify-center px-5 py-10 sm:px-10">
                <Link href={route('home')} className="mb-8 lg:hidden"><Brand /></Link>
                <div className="surface w-full max-w-md p-6 sm:p-8">{children}</div>
                <Link href={route('home')} className="mt-7 inline-flex items-center gap-2 text-xs font-medium text-slate-500 hover:text-teal-700"><Icon name="arrowLeft" className="h-3.5 w-3.5" />Kembali ke beranda</Link>
            </div>
        </div>
    );
}
