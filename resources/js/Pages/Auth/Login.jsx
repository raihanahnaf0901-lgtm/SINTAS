import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

const roleOptions = [
    { id: 'siswa', label: 'Siswa', description: 'Nama lengkap, NIS, dan kelas' },
    { id: 'guru', label: 'Guru', description: 'Nama, gelar, dan NIP' },
    { id: 'admin', label: 'Admin', description: 'Email dan kata sandi' },
];

const examples = {
    siswa: { nama_lengkap: 'Raihan Ahnaf', nis: '12345', kelas_id: '1' },
    guru: { nama: 'Budi Santoso', gelar: 'S.Pd', nip: '19850101' },
    admin: { email: 'admin@sintas.test', password: 'password' },
};

export default function Login({ classes, status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        role: 'siswa',
        email: '',
        password: '',
        nama_lengkap: '',
        nis: '',
        kelas_id: '',
        nama: '',
        gelar: '',
        nip: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'));
    };

    const selectRole = (role) => {
        reset();
        setData('role', role);
    };

    const useExample = () => {
        setData({ ...data, ...examples[data.role] });
    };

    return (
        <GuestLayout>
            <Head title="Masuk SINTAS" />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <div className="mb-7">
                <p className="eyebrow text-teal-700">Senang bertemu lagi</p>
                <h1 className="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">Masuk ke SINTAS</h1>
                <p className="mt-2 text-sm leading-6 text-slate-500">Pilih peranmu dan lanjutkan perjalanan belajar.</p>
            </div>

            <div className="grid grid-cols-3 gap-2" aria-label="Pilih peran">
                {roleOptions.map((role) => (
                    <button
                        key={role.id}
                        type="button"
                        aria-pressed={data.role === role.id}
                        onClick={() => selectRole(role.id)}
                        className={`rounded-xl border px-3 py-3 text-left transition focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 ${
                            data.role === role.id
                                ? 'border-teal-600 bg-teal-50 text-teal-900 ring-1 ring-teal-600'
                                : 'border-slate-200 bg-white text-slate-500 hover:border-teal-300'
                        }`}
                    >
                        <span className="block text-sm font-bold">{role.label}</span>
                        <span className="mt-1 block text-[11px] leading-4">{role.description}</span>
                    </button>
                ))}
            </div>

            <form onSubmit={submit} className="mt-6 space-y-4">
                {data.role === 'siswa' && (
                    <>
                        <Field label="Nama lengkap" error={errors.nama_lengkap}>
                            <input value={data.nama_lengkap} onChange={(event) => setData('nama_lengkap', event.target.value)} className="input" autoComplete="name" />
                        </Field>
                        <Field label="NIS" error={errors.nis}>
                            <input value={data.nis} onChange={(event) => setData('nis', event.target.value)} className="input" inputMode="numeric" />
                        </Field>
                        <Field label="Kelas" error={errors.kelas_id}>
                            <select value={data.kelas_id} onChange={(event) => setData('kelas_id', event.target.value)} className="input">
                                <option value="">Pilih kelas</option>
                                {classes.map((kelas) => <option key={kelas.id} value={kelas.id}>{kelas.nama_kelas}</option>)}
                            </select>
                        </Field>
                    </>
                )}

                {data.role === 'guru' && (
                    <>
                        <Field label="Nama" error={errors.nama}><input value={data.nama} onChange={(event) => setData('nama', event.target.value)} className="input" autoComplete="name" /></Field>
                        <Field label="Gelar" error={errors.gelar}><input value={data.gelar} onChange={(event) => setData('gelar', event.target.value)} className="input" /></Field>
                        <Field label="NIP" error={errors.nip}><input value={data.nip} onChange={(event) => setData('nip', event.target.value)} className="input" inputMode="numeric" /></Field>
                    </>
                )}

                {data.role === 'admin' && (
                    <>
                        <Field label="Email" error={errors.email}><input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} className="input" autoComplete="username" /></Field>
                        <Field label="Kata sandi" error={errors.password}><input type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} className="input" autoComplete="current-password" /></Field>
                    </>
                )}

                <InputError message={errors.role} />

                <button type="button" onClick={useExample} className="w-full rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-3 text-xs font-semibold text-slate-600 transition hover:border-teal-400 hover:bg-teal-50 hover:text-teal-800">
                    Isi contoh akun {data.role}
                </button>
                <button type="submit" disabled={processing} className="button-primary w-full">
                    {processing ? 'Memeriksa data…' : 'Masuk'}
                </button>
            </form>
        </GuestLayout>
    );
}

function Field({ label, error, children }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-sm font-medium text-slate-700">{label}</span>
            {children}
            <InputError message={error} className="mt-1.5" />
        </label>
    );
}
