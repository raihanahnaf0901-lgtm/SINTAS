import { Button, EmptyState, Field, LoadingState, Notice, StatusBadge, formatDate, useApiData, useApiForm } from '@/Components/AcademicUI';
import StudentLayout from '@/Layouts/StudentLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

export function TeacherRow({ member, ownerId, schoolId, onChanged }) {
    const teacherType = member.jenis_guru ?? member.guru.jenis_guru;
    const form = useApiForm({ jenis_guru: teacherType });
    const review = useApiForm({});
    const owner = member.guru.id === ownerId;

    async function decide(status) {
        const result = await review.submit('patch', '/api/v1/admin-sekolah/anggota/' + member.id, { status });
        if (result) onChanged(result.message);
    }

    async function save(event) {
        event.preventDefault();
        const result = await form.submit('patch', '/api/v1/admin-sekolah/guru/' + member.guru.id, { ...form.data, sekolah_id: schoolId });
        if (result) onChanged(result.message);
    }

    return (
        <li className="flex flex-col gap-4 rounded-2xl border border-slate-100 p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 className="font-bold text-slate-900">{member.guru.nama_lengkap}</h3>
                    <p className="mt-1 text-xs text-slate-500">{owner ? 'Admin sekolah · Pendiri sekolah' : 'Guru sekolah'}</p>
                </div>
                <StatusBadge status={member.status} />
            </div>
            {member.status === 'pending' && !owner && (
                <div className="flex flex-wrap gap-3">
                    <Button disabled={review.processing} onClick={() => decide('diterima')}>Terima guru</Button>
                    <Button variant="danger" disabled={review.processing} onClick={() => decide('ditolak')}>Tolak</Button>
                </div>
            )}
            {member.status === 'diterima' && (
                <form onSubmit={save} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="min-w-0 flex-1">
                        <Field label="Jenis guru" error={form.errors.jenis_guru}>
                            <select className="input" value={form.data.jenis_guru} onChange={(event) => form.setData('jenis_guru', event.target.value)}>
                                <option value="guru_mapel">Guru mata pelajaran</option>
                                <option value="guru_piket">Guru piket</option>
                            </select>
                        </Field>
                    </div>
                    <Button type="submit" variant="secondary" disabled={form.processing || form.data.jenis_guru === teacherType}>
                        {form.processing ? 'Menyimpan...' : 'Simpan jenis guru'}
                    </Button>
                </form>
            )}
            <Notice message={form.errors._general || review.errors._general} />
        </li>
    );
}

export default function SchoolAdmin() {
    const selectedSchoolId = usePage().props.auth.school?.id;
    const state = useApiData(selectedSchoolId ? '/api/v1/admin-sekolah?sekolah_id=' + selectedSchoolId : null);
    const school = state.data?.school;

    function refresh() {
        state.reload();
        router.reload({ only: ['auth'] });
    }

    return (
        <StudentLayout active="school-admin" title="Admin sekolah">
            <Head title="Admin Sekolah" />
            <div className="mb-7 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="eyebrow mb-2">Pengelolaan sekolah</p>
                    <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Admin sekolah</h1>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                        Setujui guru, atur jenis guru, dan kelola seluruh kelas di sekolah yang dipilih.
                    </p>
                </div>
                <Link href={route('master-data.index')} className="button-secondary">Data sekolah & langganan</Link>
            </div>
            {state.loading ? <LoadingState /> : state.error ? (
                <div className="flex flex-col items-start gap-3"><Notice message={state.error} /><Button onClick={state.reload}>Coba lagi</Button></div>
            ) : school && (
                <div className="flex flex-col gap-7">
                    <section className="surface flex flex-col gap-4 p-6">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-xl font-bold text-slate-900">{school.nama_sekolah}</h2>
                                <p className="mt-1 text-sm text-slate-500">NPSN {school.npsn}</p>
                            </div>
                            <StatusBadge status={school.status} />
                        </div>
                        <p className="text-sm leading-6 text-slate-600">
                            Kode bergabung guru: <strong className="select-all font-mono text-teal-800">{school.kode_sekolah}</strong>.
                            {' '}Guru yang menggunakan kode ini tetap memerlukan persetujuan Anda.
                        </p>
                        <p className="text-sm text-slate-500">Langganan sampai {formatDate(school.subscription_ends_at, true)}.</p>
                        {!school.subscription_active && <Notice message="Langganan sudah berakhir. Perpanjang melalui Data sekolah agar guru dapat membuat kelas baru." />}
                    </section>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {[['guru', 'Guru bergabung'], ['kelas', 'Kelas sekolah'], ['pending', 'Menunggu persetujuan']].map(([key, label]) => (
                            <div key={key} className="surface p-5"><p className="text-sm text-slate-500">{label}</p><p className="mt-2 text-3xl font-extrabold text-teal-800">{state.data.stats[key]}</p></div>
                        ))}
                    </div>
                    <section className="surface flex flex-col gap-5 p-6">
                        <div>
                            <h2 className="text-xl font-bold text-slate-900">Guru & permintaan bergabung</h2>
                            <p className="mt-2 text-sm leading-6 text-slate-500">Guru mapel mengelola kelas miliknya. Guru piket memantau sesuai izin kelas. Sebagai admin pendiri sekolah, Anda dapat mengelola seluruh kelas sekolah ini.</p>
                        </div>
                        {state.data.members.length ? (
                            <ul className="flex flex-col gap-4">
                                {state.data.members.map((member) => <TeacherRow key={member.id} member={member} ownerId={school.admin_guru_id} schoolId={school.id} onChanged={refresh} />)}
                            </ul>
                        ) : <EmptyState title="Belum ada guru" description="Bagikan kode sekolah untuk mengundang guru." />}
                    </section>
                    <section className="flex flex-col gap-5">
                        <div>
                            <h2 className="text-xl font-bold text-slate-900">Kelola semua kelas</h2>
                            <p className="mt-2 text-sm leading-6 text-slate-500">Sebagai pendiri sekolah, Anda dapat mengelola kelas, tugas, ujian, anggota, dan penilaian seluruh guru di sekolah ini.</p>
                        </div>
                        {state.data.rooms.length ? (
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {state.data.rooms.map((room) => (
                                    <article key={room.id} className="surface flex flex-col gap-4 p-5">
                                        <div className="flex flex-wrap items-start justify-between gap-2"><h3 className="font-bold text-slate-900">{room.nama_kelas_mapel}</h3><StatusBadge status={room.status} /></div>
                                        <p className="text-sm text-slate-500">{room.pembuat?.nama_lengkap}</p>
                                        <p className="text-sm text-slate-600">{room.anggota_count} siswa · {room.tugas_count} tugas</p>
                                        <Link href={route('subjects.show', room.id)} className="button-secondary">Kelola kelas</Link>
                                    </article>
                                ))}
                            </div>
                        ) : <EmptyState title="Belum ada kelas sekolah" description="Kelas akan muncul setelah guru yang diterima membuat kelas mata pelajarannya." />}
                    </section>
                </div>
            )}
        </StudentLayout>
    );
}
