import AccountAuthForm from '@/Components/AccountAuthForm';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';

export default function Register() {
    return (
        <GuestLayout>
            <Head title="Daftar Siswa" />
            <h1 className="mb-2 text-2xl font-extrabold text-slate-900">Buat akun siswa</h1>
            <p className="mb-6 text-sm text-slate-500">Daftar dengan email kamu, lalu verifikasi kode yang dikirim ke kotak masuk.</p>
            <AccountAuthForm registering />
        </GuestLayout>
    );
}
