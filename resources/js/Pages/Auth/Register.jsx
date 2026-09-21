import AccountAuthForm from '@/Components/AccountAuthForm';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';

export default function Register({ initialRole = 'siswa' }) {
    return (
        <GuestLayout>
            <Head title="Daftar Akun" />
            <h1 className="mb-2 text-2xl font-extrabold text-slate-900">Buat akun SINTAS</h1>
            <p className="mb-6 text-sm text-slate-500">Daftar dengan email kamu, lalu verifikasi kode yang dikirim ke kotak masuk.</p>
            <AccountAuthForm key={initialRole} registering initialRole={initialRole} />
        </GuestLayout>
    );
}
