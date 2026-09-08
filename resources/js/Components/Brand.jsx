import Icon from '@/Components/Icon';

export default function Brand({ light = false }) {
    return (
        <span className="flex items-center gap-3">
            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-700 text-white shadow-sm"><Icon name="book" className="h-6 w-6" /></span>
            <span>
                <span className={`block text-xl font-extrabold tracking-[0.08em] ${light ? 'text-white' : 'text-slate-900'}`}>SINTAS<span className="text-teal-500">.</span></span>
                <span className={`block text-[10px] font-medium ${light ? 'text-slate-300' : 'text-slate-500'}`}>Sistem Informasi Tugas</span>
            </span>
        </span>
    );
}
