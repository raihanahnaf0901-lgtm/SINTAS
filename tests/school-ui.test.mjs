import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import { createElement } from 'react';
import { renderToStaticMarkup, renderToString } from 'react-dom/server';
import { createServer } from 'vite';

const server = await createServer({ server: { middlewareMode: true, hmr: false } });
after(() => server.close());
const { checkoutDestination, RegisterSchoolForm, JoinSchoolForm, SchoolMembershipCard, PaymentHistory, SchoolEnrollmentNotice, SchoolSelector, SchoolOnboardingForms } = await server.ssrLoadModule('/resources/js/Pages/School.jsx');
const { TeacherRow } = await server.ssrLoadModule('/resources/js/Pages/SchoolAdmin.jsx');
const { SchoolAdminAccess, classroomTeacherAccess } = await server.ssrLoadModule('/resources/js/Pages/Classroom/MembersPanel.jsx');
const { profileRoleLabel } = await server.ssrLoadModule('/resources/js/Pages/Profile/Edit.jsx');
const { default: Subjects, CreateClassForm, classCreationPayload } = await server.ssrLoadModule('/resources/js/Pages/Subjects.jsx');
const render = (Component, props) => renderToStaticMarkup(createElement(Component, props));
globalThis.route = (name) => '/' + name;
after(() => { delete globalThis.route; });

test('school registration waits for plans and payment configuration', () => {
    const noPlans = render(RegisterSchoolForm, { paymentReady: true });
    const noKey = render(RegisterSchoolForm, { plans: [{ code: 'trial', name: 'Paket Uji', amount: 50000, duration_days: 30 }] });
    assert.match(noPlans, /Paket langganan belum tersedia/);
    assert.match(noPlans, /<button[^>]*disabled=""/);
    assert.doesNotMatch(noPlans, /Rp/);
    assert.match(noKey, /Konfigurasi Midtrans/);
    assert.match(noKey, /<button[^>]*disabled=""/);
});

test('registration offers server plans without an editable price', () => {
    const html = render(RegisterSchoolForm, { paymentReady: true, plans: [{ code: 'trial', name: '<script>Paket</script>', amount: 50000, duration_days: 30 }] });
    assert.match(html, /&lt;script&gt;Paket&lt;\/script&gt;/);
    assert.match(html, /value="trial"/);
    assert.match(html, /\/ 30 hari/);
    assert.doesNotMatch(html, /<script>|<button[^>]*disabled|name="amount"/);
});

test('teacher joining explains admin approval and uses the server code limit', () => {
    const html = render(JoinSchoolForm, {});
    assert.match(html, /Kode sekolah/);
    assert.match(html, /setelah permintaan diterima/);
    assert.match(html, /Ajukan bergabung/);
    assert.match(html, /maxLength="16"/);
});

test('pending teachers can cancel but pending founders must complete payment', () => {
    const teacher = render(SchoolMembershipCard, { membership: { status: 'pending', sekolah: { nama_sekolah: 'Sekolah Uji', is_owner: false } } });
    const founder = render(SchoolMembershipCard, { membership: { status: 'pending', sekolah: { nama_sekolah: '<script>Sekolah</script>', is_owner: true, is_admin: false } } });
    assert.match(teacher, /Batalkan permintaan/);
    assert.match(teacher, /Tunggu admin sekolah/);
    assert.match(founder, /Menunggu pembayaran/);
    assert.match(founder, /&lt;script&gt;Sekolah/);
    assert.doesNotMatch(founder, /Batalkan permintaan|Buka admin sekolah|<script>/);
});

test('checkout only permits HTTPS Snap URLs for the configured Midtrans environment', () => {
    const sandboxUrl = 'https://app.sandbox.midtrans.com/snap/v4/redirection/example';
    const liveUrl = 'https://app.midtrans.com/snap/v4/redirection/example';
    assert.equal(checkoutDestination(sandboxUrl), sandboxUrl);
    assert.equal(checkoutDestination(liveUrl, false), liveUrl);
    for (const url of [null, 'javascript:alert(1)', 'http://app.sandbox.midtrans.com/snap/', 'https://app.sandbox.midtrans.com.example.org/snap/', 'https://name:secret@app.sandbox.midtrans.com/snap/', 'https://app.sandbox.midtrans.com:8443/snap/', 'https://app.sandbox.midtrans.com/merchant/login', liveUrl]) {
        assert.equal(checkoutDestination(url), null);
    }
    assert.equal(checkoutDestination(sandboxUrl, false), null);
});

test('pending payments can open checkout securely and paid payments cannot be paid again', () => {
    const payment = { id: 1, order_id: '<script>order</script>', status: 'pending', amount: 50000, checkout_url: 'https://app.sandbox.midtrans.com/snap/v4/redirection/example' };
    const pending = render(PaymentHistory, { payments: [payment] });
    const paid = render(PaymentHistory, { payments: [{ ...payment, status: 'paid' }] });
    assert.match(pending, /Lanjutkan pembayaran/);
    assert.match(pending, /target="_blank"/);
    assert.match(pending, /rel="noopener noreferrer"/);
    assert.match(pending, /&lt;script&gt;order/);
    assert.doesNotMatch(pending, /<script>/);
    assert.match(paid, /Pembayaran berhasil/);
    assert.doesNotMatch(paid, /<a|<button/);
});

test('untrusted payment links are hidden and uncertain orders explain recovery', () => {
    const html = render(PaymentHistory, { payments: [{ id: 1, order_id: 'order', status: 'creating', amount: 50000, checkout_url: 'javascript:alert(1)' }] });
    assert.doesNotMatch(html, /<a|javascript:/);
    assert.match(html, /Cek status/);
    assert.match(html, /sebelum membayar ulang/);
});

test('students and teachers who can create classes receive no school warning', () => {
    assert.equal(render(SchoolEnrollmentNotice, { auth: { user: { role: 'siswa' } } }), '');
    assert.equal(render(SchoolEnrollmentNotice, { auth: { user: { role: 'guru' }, canCreateClass: true } }), '');
});

test('admin can review pending teachers but cannot reject the school founder', () => {
    const member = { id: 1, status: 'pending', guru: { id: 2, nama_lengkap: '<script>Nama</script>', jenis_guru: 'guru_mapel' } };
    const regular = render(TeacherRow, { member, ownerId: 1 });
    const owner = render(TeacherRow, { member, ownerId: 2 });
    assert.match(regular, /Terima guru/);
    assert.match(regular, /Tolak/);
    assert.match(regular, /&lt;script&gt;Nama/);
    assert.doesNotMatch(regular, /<script>/);
    assert.doesNotMatch(owner, /Terima guru|>Tolak</);
});

test('accepted teachers only have mapel or piket options and pending teachers cannot change type', () => {
    const member = { id: 1, status: 'diterima', guru: { id: 2, nama_lengkap: 'Guru', jenis_guru: 'guru_piket' } };
    const html = render(TeacherRow, { member, ownerId: 1 });
    assert.match(html, /value="guru_mapel"/);
    assert.match(html, /value="guru_piket" selected=""/);
    assert.doesNotMatch(html, /value="admin_sekolah"/);
    assert.doesNotMatch(render(TeacherRow, { member: { ...member, status: 'pending' }, ownerId: 1 }), /<select/);
});

async function renderSubjects(auth) {
    const response = await createInertiaApp({
        page: { component: 'Subjects', url: '/mata-pelajaran', version: null, props: { auth } },
        resolve: () => Subjects,
        render: renderToString,
        setup: ({ App, props }) => createElement(App, props),
    });
    return response.body;
}

test('class creation uses backend eligibility and admin navigation uses school role', async () => {
    const teacher = { name: 'Guru', role: 'guru', guru: { jenis_guru: 'guru_mapel' } };
    const blocked = await renderSubjects({ user: teacher, canCreateClass: false });
    const allowed = await renderSubjects({ user: teacher, canCreateClass: true, isSchoolAdmin: true });
    assert.doesNotMatch(blocked, /\+ Buat kelas|href="\/school-admin.index"/);
    assert.match(blocked, /wajib bergabung ke sekolah/);
    assert.match(allowed, /\+ Buat kelas/);
    assert.match(allowed, /href="\/school-admin.index"/);
});

test('rejected teachers get a rejoin notice rather than being mislabeled as piket', async () => {
    const html = await renderSubjects({
        user: { name: 'Guru', role: 'guru', guru: { jenis_guru: 'guru_mapel' } }, canCreateClass: false,
        school: { subscription_active: true }, schoolMembership: { status: 'ditolak' },
    });
    assert.match(html, /Permintaan bergabung belum disetujui/);
    assert.doesNotMatch(html, /Akun guru piket dapat memantau/);
});

test('monthly school plan displays calendar months and a separate per-school price', () => {
    const html = render(RegisterSchoolForm, { paymentReady: true, plans: [
        { code: 'monthly', name: 'Bulanan', amount: 75000, duration_months: 1, duration_days: null },
    ] });

    assert.match(html, /75\.000/);
    assert.match(html, /1 bulan kalender per sekolah/);
    assert.match(html, /Setiap sekolah memiliki tagihan sendiri/);
    assert.doesNotMatch(html, /30 hari|\/ hari|name="amount"/);
});

test('teachers already in a school can register another school without bypassing the member-school limit', () => {
    const html = render(SchoolOnboardingForms, { hasMembership: true, canRegister: true, canJoin: false });

    assert.match(html, /Daftarkan sekolah lain/);
    assert.match(html, /pembayaran langganan terpisah/);
    assert.doesNotMatch(html, /Gabung sebagai guru anggota/);
});

test('school founders without a separate member school may also request membership', () => {
    const html = render(SchoolOnboardingForms, { hasMembership: true, canRegister: true, canJoin: true });

    assert.match(html, /Daftarkan sekolah lain/);
    assert.match(html, /Gabung sebagai guru anggota/);
});

test('school selector retains the selected school and distinguishes a pending founder from an admin', () => {
    const html = render(SchoolSelector, { selectedSchoolId: 2, memberships: [
        { id: 10, status: 'diterima', sekolah: { id: 1, nama_sekolah: 'Sekolah Lama', is_owner: true, is_admin: true } },
        { id: 20, status: 'pending', sekolah: { id: 2, nama_sekolah: '<script>Sekolah Baru</script>', is_owner: true, is_admin: false } },
    ] });

    assert.match(html, /value="2" selected=""/);
    assert.match(html, /Sekolah Lama — Admin sekolah/);
    assert.match(html, /&lt;script&gt;Sekolah Baru&lt;\/script&gt; — Pendaftar sekolah \(menunggu\)/);
    assert.doesNotMatch(html, /<script>/);
    assert.match(html, /<button[^>]*disabled=""/);
});

test('class creation identifies the selected school rather than an old form school', () => {
    const payload = classCreationPayload({ nama_kelas_mapel: 'Matematika', deskripsi: 'Kelas pagi', sekolah_id: 1 }, 2);
    const html = render(CreateClassForm, { school: { id: 2, nama_sekolah: '<script>Sekolah Dua</script>' } });

    assert.deepEqual(payload, { nama_kelas_mapel: 'Matematika', deskripsi: 'Kelas pagi', sekolah_id: 2 });
    assert.match(html, /Sekolah tujuan:/);
    assert.match(html, /&lt;script&gt;Sekolah Dua/);
    assert.doesNotMatch(html, /<script>/);
});

test('school founder class creation follows backend permissions even when their teaching type is piket', async () => {
    const html = await renderSubjects({
        user: { name: 'Pendiri', role: 'guru', guru: { jenis_guru: 'guru_piket' } },
        school: { id: 2, nama_sekolah: 'Sekolah Dua' }, schoolTeacherType: 'guru_piket',
        isSchoolAdmin: true, canCreateClass: true,
    });

    assert.match(html, /\+ Buat kelas/);
    assert.match(html, /href="\/school-admin.index"/);
    assert.doesNotMatch(html, /Akun guru piket dapat memantau/);
});

test('admin teacher type editor uses the selected school membership type', () => {
    const html = render(TeacherRow, { member: {
        id: 10, status: 'diterima', jenis_guru: 'guru_piket',
        guru: { id: 3, nama_lengkap: 'Guru', jenis_guru: 'guru_mapel' },
    }, ownerId: 1, schoolId: 2 });

    assert.match(html, /value="guru_piket" selected=""/);
    assert.doesNotMatch(html, /value="guru_mapel" selected=""/);
});

test('implicit school admin access is permanent, escaped, and has no revoke controls', () => {
    const html = render(SchoolAdminAccess, { admin: { id: 3, nama_lengkap: '<script>Pendiri</script>' } });

    assert.match(html, /&lt;script&gt;Pendiri/);
    assert.match(html, /Admin sekolah · Akses tetap/);
    assert.match(html, /akses penuh/);
    assert.match(html, /tidak dapat dicabut/);
    assert.doesNotMatch(html, /<script>|<button|<select|<form/);
    assert.equal(render(SchoolAdminAccess, { admin: null }), '');
});

test('implicit admin is not duplicated in the whitelist and neither founder nor creator can be selected for access revocation', () => {
    const entries = [{ id: 10, guru_id: 1 }, { id: 20, guru_id: 2 }, { id: 30, guru_id: 3 }];
    const teachers = [{ id: 1 }, { id: 2 }, { id: 3 }];

    assert.deepEqual(classroomTeacherAccess({ entries, teachers, creatorId: 1, adminId: 3 }), {
        entries: [entries[0], entries[1]], options: [teachers[1]],
    });
    assert.deepEqual(classroomTeacherAccess({ entries, teachers, creatorId: 1 }), {
        entries, options: [teachers[1], teachers[2]],
    });
});

test('profile role uses the selected school admin and teaching role before the global fallback', () => {
    const user = { role: 'guru', guru: { jenis_guru: 'guru_piket' } };

    assert.equal(profileRoleLabel({ user, isSchoolAdmin: true, schoolTeacherType: 'guru_piket' }), 'Admin sekolah');
    assert.equal(profileRoleLabel({ user, schoolTeacherType: 'guru_mapel' }), 'Guru mata pelajaran');
    assert.equal(profileRoleLabel({ user: { role: 'guru', guru: { jenis_guru: 'guru_mapel' } }, schoolTeacherType: 'guru_piket' }), 'Guru piket');
    assert.equal(profileRoleLabel({ user }), 'Guru piket');
    assert.equal(profileRoleLabel({ user: { role: 'siswa' } }), 'Siswa');
});
