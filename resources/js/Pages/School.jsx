import { api, Button, EmptyState, Field, LoadingState, Notice, StatusBadge, formatDate, useApiData, useApiForm } from '@/Components/AcademicUI';
import Icon from '@/Components/Icon';
import StudentLayout from '@/Layouts/StudentLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const rupiah = (amount) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);

export function checkoutDestination(value, sandbox = true) {
    try {
        const url = new URL(value);
        const host = sandbox ? 'app.sandbox.midtrans.com' : 'app.midtrans.com';
        return url.protocol === 'https:' && url.hostname === host && !url.port && !url.username && !url.password && url.pathname.startsWith('/snap/') ? url.href : null;
    } catch { return null; }
}

export function SchoolEnrollmentNotice({ auth }) {
    if (auth?.user?.role !== 'guru' || auth.canCreateClass) return null;
    const pending = auth.schoolMembership?.status === 'pending';
    const rejected = auth.schoolMembership?.status === 'ditolak';
    const school = auth.school ?? auth.schoolMembership?.sekolah;
    const message = pending
        ? 'Selesaikan persetujuan keanggotaan atau aktivasi sekolah di Data sekolah sebelum membuat kelas.'
        : rejected ? 'Permintaan bergabung belum disetujui. Anda dapat mengajukan ulang atau memilih sekolah lain di Data sekolah.'
        : school && !school.subscription_active
            ? 'Langganan sekolah belum aktif. Hubungi admin sekolah untuk mengaktifkannya sebelum membuat kelas baru.'
            : !school
                ? 'Anda wajib bergabung ke sekolah terlebih dahulu. Jika sekolah belum terdaftar, daftarkan sekolah dan pilih langganannya.'
                : 'Akun guru piket dapat memantau kelas. Pembuatan kelas hanya tersedia untuk guru mapel.';
    return <div className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900"><p className="max-w-3xl leading-6">{message}</p><Link href={route('master-data.index')} className="shrink-0 font-bold underline underline-offset-4">Buka data sekolah</Link></div>;
}

export function PlanSelection({ plans, form }) {
    if (!plans.length) return <Notice tone="success" message="Paket langganan belum tersedia. Pendaftaran dan pembayaran akan dibuka setelah nama paket, harga, durasi, dan batasannya ditentukan." />;
    return <Field label="Paket langganan" error={form.errors.plan_code}><select className="input" name="plan_code" required disabled={form.processing} value={form.data.plan_code} onChange={(event) => form.setData('plan_code', event.target.value)}><option value="">Pilih paket langganan</option>{plans.map((plan) => <option key={plan.code} value={plan.code}>{plan.name} — {rupiah(plan.amount)} / {plan.duration_months ? plan.duration_months + ' bulan kalender' : plan.duration_days + ' hari'} per sekolah</option>)}</select></Field>;
}

export function RegisterSchoolForm({ plans = [], paymentReady = false, onUpdated, onFailed }) {
    const form = useApiForm({ nama_sekolah: '', npsn: '', alamat: '', plan_code: '' });
    const available = plans.length > 0 && paymentReady;
    async function submit(event) {
        event.preventDefault();
        if (!available) return;
        const result = await form.submit('post', '/api/v1/sekolah');
        if (result) onUpdated?.();
        else onFailed?.();
    }
    return <form onSubmit={submit} className="surface flex flex-col gap-5 p-6">
        <div><h2 className="text-lg font-bold">Daftarkan sekolah baru</h2><p className="mt-2 text-sm leading-6 text-slate-500">Pastikan sekolah belum terdaftar. Pendaftar menjadi admin sekolah setelah pembayaran langganan berhasil diverifikasi. Setiap sekolah memiliki tagihan sendiri; langganan sekolah lain tidak digunakan.</p></div>
        <Field label="Nama sekolah" error={form.errors.nama_sekolah}><input className="input" name="nama_sekolah" required maxLength={255} value={form.data.nama_sekolah} onChange={(event) => form.setData('nama_sekolah', event.target.value)} placeholder="Nama resmi sekolah" /></Field>
        <Field label="NPSN" error={form.errors.npsn}><input className="input" name="npsn" required inputMode="numeric" pattern="[0-9]{8}" maxLength={8} title="Masukkan 8 angka NPSN sekolah." value={form.data.npsn} onChange={(event) => form.setData('npsn', event.target.value)} placeholder="8 angka NPSN sekolah" /></Field>
        <Field label="Alamat (opsional)" error={form.errors.alamat}><textarea className="input" name="alamat" rows={3} maxLength={2000} value={form.data.alamat} onChange={(event) => form.setData('alamat', event.target.value)} /></Field>
        <PlanSelection plans={plans} form={form} />
        {!paymentReady && plans.length > 0 && <Notice tone="success" message="Pembayaran belum tersedia. Konfigurasi Midtrans perlu dilengkapi oleh pengelola SINTAS." />}
        <Notice message={form.errors._general} /><Button type="submit" disabled={form.processing || !available}>{form.processing ? 'Menyiapkan pendaftaran...' : 'Daftar dan lanjutkan pembayaran'}</Button><p className="text-xs leading-5 text-slate-500">Tidak ada pembayaran otomatis dari formulir ini. Pilih metode pembayaran pada halaman Midtrans setelah tagihan berhasil dibuat.</p>
    </form>;
}

export function JoinSchoolForm({ onUpdated }) {
    const form = useApiForm({ kode_sekolah: '' });
    async function submit(event) {
        event.preventDefault();
        const result = await form.submit('post', '/api/v1/sekolah/gabung', { kode_sekolah: form.data.kode_sekolah.trim().toUpperCase() });
        if (result) onUpdated?.();
    }
    return <form onSubmit={submit} className="surface flex flex-col gap-5 p-6"><div><h2 className="text-lg font-bold">Bergabung ke sekolah</h2><p className="mt-2 text-sm leading-6 text-slate-500">Sekolah sudah terdaftar? Minta kode sekolah kepada admin. Anda dapat membuat kelas setelah permintaan diterima dan langganan sekolah aktif.</p></div><Field label="Kode sekolah" error={form.errors.kode_sekolah}><input className="input uppercase" name="kode_sekolah" required maxLength={16} value={form.data.kode_sekolah} onChange={(event) => form.setData('kode_sekolah', event.target.value)} placeholder="Masukkan kode dari admin sekolah" /></Field><Notice message={form.errors._general} /><Button type="submit" disabled={form.processing}>{form.processing ? 'Mengirim permintaan...' : 'Ajukan bergabung'}</Button><p className="text-xs leading-5 text-slate-500">Anda dapat menjadi guru anggota di satu sekolah. Sekolah yang Anda dirikan memiliki keanggotaan dan langganan tersendiri. Pengajuan yang menunggu bisa dibatalkan pada sekolah terkait.</p></form>;
}

export function SchoolMembershipCard({ membership, onUpdated }) {
    const form = useApiForm({});
    const school = membership.sekolah;
    const owner = school.is_owner ?? school.is_admin;
    const pending = membership.status === 'pending';
    async function cancel() {
        const result = await form.submit('delete', '/api/v1/sekolah/permintaan', { sekolah_id: school.id });
        if (result) onUpdated?.();
    }
    return <section className="surface flex flex-col gap-5 p-6">
        <div className="flex flex-wrap items-start justify-between gap-4"><div className="flex min-w-0 gap-4"><span className="self-start rounded-2xl bg-teal-50 p-3 text-teal-700"><Icon name="landmark" /></span><div className="min-w-0"><p className="eyebrow mb-2">{owner ? 'Sekolah yang Anda kelola' : 'Sekolah dipilih'}</p><h2 className="break-words text-xl font-bold">{school.nama_sekolah}</h2></div></div>{owner && pending ? <span className="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Menunggu pembayaran</span> : <StatusBadge status={membership.status} />}</div>
        {pending ? <Notice tone="success" message={owner ? 'Selesaikan pembayaran dan cek statusnya. Sekolah serta role admin sekolah akan aktif setelah pembayaran dikonfirmasi oleh Midtrans.' : 'Permintaan sudah dikirim. Tunggu admin sekolah menerima Anda, lalu perbarui status di halaman ini.'} /> : <>
            <div className="grid gap-4 text-sm sm:grid-cols-2"><div><p className="text-slate-500">Status langganan</p><p className={'mt-1 font-bold ' + (school.subscription_active ? 'text-teal-700' : 'text-amber-700')}>{school.subscription_active ? 'Aktif' : 'Belum aktif atau sudah berakhir'}</p></div><div><p className="text-slate-500">Berlaku sampai</p><p className="mt-1 font-bold">{formatDate(school.subscription_ends_at, true)}</p></div></div>
            {school.kode_sekolah && <div className="rounded-xl bg-slate-50 p-4"><p className="text-xs text-slate-500">Kode untuk mengundang guru</p><p className="mt-2 break-all font-mono text-lg font-bold tracking-wider text-teal-800">{school.kode_sekolah}</p></div>}
            {!school.subscription_active && <Notice tone="success" message={owner ? 'Aktifkan atau perpanjang langganan agar guru dapat membuat kelas baru.' : 'Hubungi admin sekolah untuk mengaktifkan langganan. Pembuatan kelas baru belum tersedia.'} />}
        </>}
        <div className="flex flex-wrap gap-3">{!pending && school.is_admin && <Link href={route('school-admin.index', { sekolah_id: school.id })} className="button-primary">Buka admin sekolah</Link>}{!pending && <Link href={route('subjects.index')} className="button-secondary">Lihat kelas mata pelajaran</Link>}{pending && !owner && <Button variant="danger" disabled={form.processing} onClick={cancel}>{form.processing ? 'Membatalkan...' : 'Batalkan permintaan'}</Button>}</div><Notice message={form.errors._general} />
    </section>;
}

export function PaymentHistory({ payments = [], sandbox = true, onUpdated }) {
    const form = useApiForm({});
    const labels = { creating: 'Menyiapkan tagihan', pending: 'Menunggu pembayaran', paid: 'Pembayaran berhasil', failed: 'Pembayaran gagal', expired: 'Tagihan kedaluwarsa', cancelled: 'Dibatalkan' };
    async function synchronize(payment) {
        const result = await form.submit('post', '/api/v1/pembayaran-sekolah/' + payment.id + '/sinkronisasi');
        if (result) onUpdated?.();
    }
    return <section className="surface flex flex-col gap-5 p-6"><div><h2 className="text-lg font-bold">Tagihan langganan</h2><p className="mt-1 text-sm leading-6 text-slate-500">Setelah menyelesaikan pembayaran di Midtrans, kembali ke halaman ini lalu tekan Cek status. Akses tidak diaktifkan hanya dengan membuka link pembayaran.</p></div><Notice message={form.errors._general} />
        {payments.length ? <ul className="flex flex-col gap-4">{payments.map((payment) => {
            const href = checkoutDestination(payment.checkout_url, sandbox);
            const pending = ['creating', 'pending'].includes(payment.status);
            return <li key={payment.id} className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-100 p-4"><div className="min-w-0"><p className="break-all text-sm font-bold">{payment.order_id}</p><p className="mt-2 text-sm text-slate-500">{rupiah(payment.amount)}</p><p className={'mt-2 text-xs font-semibold ' + (payment.status === 'paid' ? 'text-teal-700' : 'text-slate-600')}>{labels[payment.status] ?? payment.status}</p>{payment.status === 'creating' && !href && <p className="mt-2 max-w-md text-xs leading-5 text-amber-700">Status transaksi belum pasti. Cek status terlebih dahulu. Jika tautan tetap tidak tersedia, hubungi pengelola dengan nomor tagihan ini sebelum membayar ulang.</p>}</div>{pending && <div className="flex flex-wrap gap-2">{href && <a href={href} target="_blank" className="button-primary" rel="noopener noreferrer">Lanjutkan pembayaran</a>}<Button variant="secondary" disabled={form.processing} onClick={() => synchronize(payment)}>{form.processing ? 'Memeriksa...' : 'Cek status'}</Button></div>}</li>;
        })}</ul> : <p className="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">Belum ada tagihan.</p>}
    </section>;
}

export function RenewSubscriptionForm({ school, plans = [], paymentReady, onUpdated, onFailed }) {
    const form = useApiForm({ plan_code: '' });
    const available = plans.length > 0 && paymentReady;
    async function submit(event) {
        event.preventDefault();
        if (!available) return;
        const result = await form.submit('post', '/api/v1/sekolah/' + school.id + '/pembayaran');
        if (result) onUpdated?.();
        else onFailed?.();
    }
    return <form onSubmit={submit} className="surface flex flex-col gap-5 p-6"><div><h2 className="text-lg font-bold">{school.subscription_active ? 'Perpanjang langganan' : 'Aktifkan langganan'}</h2><p className="mt-1 text-sm leading-6 text-slate-500">Pilih paket untuk membuat tagihan. Jika masih ada tagihan yang menunggu, lanjutkan pembayaran dari daftar tagihan.</p></div><PlanSelection plans={plans} form={form} />{!paymentReady && plans.length > 0 && <Notice tone="success" message="Pembayaran belum tersedia. Hubungi pengelola SINTAS untuk konfigurasi Midtrans." />}<Notice message={form.errors._general} /><Button type="submit" disabled={form.processing || !available}>{form.processing ? 'Menyiapkan tagihan...' : 'Buat tagihan langganan'}</Button></form>;
}



export function SchoolSelector({ memberships = [], selectedSchoolId, onSelected }) {
    const form = useApiForm({ sekolah_id: String(selectedSchoolId ?? '') });
    if (memberships.length < 2) return null;

    async function submit(event) {
        event.preventDefault();
        if (!form.data.sekolah_id || String(selectedSchoolId) === form.data.sekolah_id) return;
        const result = await form.submit('post', '/api/v1/sekolah/pilih');
        if (result) onSelected?.();
    }

    return <form onSubmit={submit} className="surface flex flex-col gap-4 p-6">
        <div><h2 className="text-lg font-bold">Pilih sekolah</h2><p className="mt-1 text-sm leading-6 text-slate-500">Pilihan ini menentukan sekolah untuk kelas baru dan menu admin. Daftar mata pelajaran tetap menampilkan semua kelas yang boleh Anda akses.</p></div>
        <Field label="Sekolah dipilih" error={form.errors.sekolah_id}><select className="input" name="sekolah_id" required disabled={form.processing} value={form.data.sekolah_id} onChange={(event) => form.setData('sekolah_id', event.target.value)}>
            <option value="">Pilih sekolah</option>
            {memberships.map((entry) => <option key={entry.id} value={entry.sekolah.id}>{entry.sekolah.nama_sekolah} — {entry.sekolah.is_admin ? 'Admin sekolah' : entry.sekolah.is_owner ? 'Pendaftar sekolah' : 'Guru anggota'}{entry.status === 'pending' ? ' (menunggu)' : entry.status === 'ditolak' ? ' (ditolak/dibatalkan)' : ''}</option>)}
        </select></Field>
        <Notice message={form.errors._general} />
        <Button type="submit" disabled={form.processing || !form.data.sekolah_id || String(selectedSchoolId) === form.data.sekolah_id}>{form.processing ? 'Mengganti sekolah...' : 'Gunakan sekolah ini'}</Button>
    </form>;
}

export function SchoolOnboardingForms({ hasMembership, canRegister = false, canJoin = false, plans, paymentReady, onUpdated, onFailed }) {
    const [mode, setMode] = useState('');
    if (!canRegister && !canJoin) return null;
    if (!hasMembership) return <div className="grid items-start gap-6 xl:grid-cols-2">
        {canJoin && <JoinSchoolForm onUpdated={onUpdated} />}
        {canRegister && <RegisterSchoolForm plans={plans} paymentReady={paymentReady} onUpdated={onUpdated} onFailed={onFailed} />}
    </div>;

    return <section className="flex flex-col gap-5">
        <div className="surface flex flex-col gap-4 p-6"><div><h2 className="text-lg font-bold">Sekolah lainnya</h2><p className="mt-1 text-sm leading-6 text-slate-500">Anda dapat mendaftarkan sekolah tambahan dengan pembayaran langganan terpisah. Sekolah yang sudah ada tidak berubah.</p></div><div className="flex flex-wrap gap-3">
            {canRegister && <Button variant="secondary" aria-expanded={mode === 'register'} onClick={() => setMode(mode === 'register' ? '' : 'register')}>{mode === 'register' ? 'Tutup pendaftaran sekolah' : 'Daftarkan sekolah lain'}</Button>}
            {canJoin && <Button variant="secondary" aria-expanded={mode === 'join'} onClick={() => setMode(mode === 'join' ? '' : 'join')}>{mode === 'join' ? 'Tutup pengajuan sekolah' : 'Gabung sebagai guru anggota'}</Button>}
        </div></div>
        {mode === 'register' && canRegister && <RegisterSchoolForm plans={plans} paymentReady={paymentReady} onUpdated={onUpdated} onFailed={onFailed} />}
        {mode === 'join' && canJoin && <JoinSchoolForm onUpdated={onUpdated} />}
    </section>;
}

export default function School() {
    const auth = usePage().props.auth;
    const resource = useApiData('/api/v1/sekolah');
    const [paymentWarning, setPaymentWarning] = useState('');
    const membership = resource.data?.membership;
    const school = membership?.sekolah;
    const owner = school?.is_owner ?? school?.is_admin;
    const hasMembership = membership && membership.status !== 'ditolak';

    function refresh(clearWarning = true, allProps = false) {
        if (clearWarning) setPaymentWarning('');
        resource.reload();
        router.reload(allProps ? {} : { only: ['auth'], preserveScroll: true });
    }

    async function checkPaymentState() {
        try {
            const result = await api('get', '/api/v1/sekolah');
            const newMembership = result.membership?.id !== membership?.id;
            const newPayment = result.payments?.[0]?.id !== resource.data?.payments?.[0]?.id;
            if (newMembership || newPayment) {
                setPaymentWarning('Pendaftaran atau tagihan sudah tercatat, tetapi pembayaran belum selesai. Periksa tagihan di bawah sebelum mencoba kembali; jangan mendaftarkan sekolah ulang.');
                refresh(false);
            }
        } catch {
            // Keep the original form error and input when the status check also fails.
        }
    }

    return <StudentLayout active="master" title="Data sekolah"><Head title="Data Sekolah" />
        <div className="mb-7 flex flex-wrap items-end justify-between gap-4"><div><p className="eyebrow mb-2">Sekolah dan langganan Anda</p><h1 className="text-3xl font-extrabold tracking-tight">Data sekolah</h1><p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Bergabung ke sekolah Anda atau daftarkan sekolah baru. Langganan dibayar per sekolah; pendaftar dapat mengelola beberapa sekolah dengan tagihan terpisah.</p></div><Button variant="secondary" disabled={resource.loading} onClick={() => refresh()}>Perbarui status</Button></div>
        {auth.canCreateClass && <Link href={route('school-reference.index')} className="mb-6 inline-flex text-sm font-bold text-teal-700 underline">Referensi mata pelajaran &amp; kelas</Link>}
        {paymentWarning && <div className="mb-5"><Notice message={paymentWarning} /></div>}
        {resource.loading ? <LoadingState /> : resource.error ? <div className="flex flex-col gap-3"><Notice message={resource.error} /><Button variant="secondary" onClick={resource.reload} className="self-start">Coba lagi</Button></div> : !resource.data ? <EmptyState title="Data sekolah belum tersedia" /> : <div className="flex flex-col gap-6">
            <SchoolSelector key={school?.id ?? 'none'} memberships={resource.data.memberships ?? []} selectedSchoolId={school?.id} onSelected={() => refresh(true, true)} />
            {resource.data.sandbox && (!hasMembership || owner || resource.data.can_register_school) && <Notice tone="success" message="Mode pengujian Midtrans Sandbox. Gunakan pembayaran simulasi, bukan uang atau kartu sungguhan." />}
            {membership?.status === 'ditolak' && <Notice message={'Permintaan bergabung ke ' + (school?.nama_sekolah ?? 'sekolah') + ' ditolak atau dibatalkan. Periksa kode sekolah atau hubungi admin, lalu ajukan kembali.'} />}
            {hasMembership && <SchoolMembershipCard key={membership.id} membership={membership} onUpdated={refresh} />}
            {hasMembership && owner && <><PaymentHistory key={'payments-' + school.id} payments={resource.data.payments ?? []} sandbox={resource.data.sandbox} onUpdated={refresh} /><RenewSubscriptionForm key={'renew-' + school.id} school={school} plans={resource.data.plans ?? []} paymentReady={resource.data.payment_ready} onUpdated={refresh} onFailed={checkPaymentState} /></>}
            <SchoolOnboardingForms key={'onboarding-' + (membership?.id ?? 'none')} hasMembership={Boolean(hasMembership)} canRegister={resource.data.can_register_school === true} canJoin={resource.data.can_join_school === true} plans={resource.data.plans ?? []} paymentReady={resource.data.payment_ready} onUpdated={refresh} onFailed={checkPaymentState} />
        </div>}
    </StudentLayout>;
}
