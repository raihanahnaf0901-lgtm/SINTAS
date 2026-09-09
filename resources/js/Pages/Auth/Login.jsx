import AccountAuthForm from '@/Components/AccountAuthForm';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';

export default function Login({ status }) {
    return (
        <GuestLayout>
            <Head title="Masuk SINTAS" />
            <h1 className="mb-2 text-2xl font-extrabold text-slate-900">Masuk ke SINTAS</h1>
            <p className="mb-6 text-sm text-slate-500">Gunakan email dan password akun SINTAS kamu.</p>
            {status && <p role="status" className="mb-4 text-sm text-teal-700">{status}</p>}
            <AccountAuthForm />
        </GuestLayout>
    );
}
