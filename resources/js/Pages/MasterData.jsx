import { Button, EmptyState, Field, LoadingState, Notice, useApiData, useApiForm } from '@/Components/AcademicUI';
import StudentLayout from '@/Layouts/StudentLayout';
import { Head } from '@inertiajs/react';

function MapelForm({ onCreated }) {
    const form = useApiForm({ nama_mapel: '' });

    async function submit(event) {
        event.preventDefault();
        const result = await form.submit('post', '/api/v1/mapel');

        if (result) {
            onCreated();
        }
    }

    return (
        <form onSubmit={submit} className="surface flex flex-col gap-5 p-6">
            <div>
                <h2 className="text-lg font-bold text-slate-900">Tambah mata pelajaran</h2>
                <p className="mt-1 text-sm leading-6 text-slate-500">Tambahkan mata pelajaran yang akan digunakan saat membuat kelas belajar.</p>
            </div>
            <Field label="Nama mata pelajaran" error={form.errors.nama_mapel}>
                <input
                    className="input"
                    required
                    maxLength={255}
                    value={form.data.nama_mapel}
                    onChange={(event) => form.setData('nama_mapel', event.target.value)}
                    placeholder="Contoh: Matematika"
                />
            </Field>
            <Notice message={form.errors._general} />
            <Notice tone="success" message={form.message} />
            <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Menyimpan...' : 'Simpan mata pelajaran'}
            </Button>
        </form>
    );
}

function KelasForm({ onCreated }) {
    const form = useApiForm({ nama_kelas: '', tahun_ajaran: '' });

    async function submit(event) {
        event.preventDefault();
        const result = await form.submit('post', '/api/v1/kelas');

        if (result) {
            onCreated();
        }
    }

    return (
        <form onSubmit={submit} className="surface flex flex-col gap-5 p-6">
            <div>
                <h2 className="text-lg font-bold text-slate-900">Tambah kelas sekolah</h2>
                <p className="mt-1 text-sm leading-6 text-slate-500">Kelas sekolah menjadi acuan untuk profil siswa dan kegiatan akademik.</p>
            </div>
            <Field label="Nama kelas" error={form.errors.nama_kelas}>
                <input
                    className="input"
                    required
                    maxLength={255}
                    value={form.data.nama_kelas}
                    onChange={(event) => form.setData('nama_kelas', event.target.value)}
                    placeholder="Contoh: X IPA 1"
                />
            </Field>
            <Field label="Tahun ajaran" error={form.errors.tahun_ajaran}>
                <input
                    className="input"
                    required
                    maxLength={20}
                    value={form.data.tahun_ajaran}
                    onChange={(event) => form.setData('tahun_ajaran', event.target.value)}
                    placeholder="Contoh: 2026/2027"
                />
            </Field>
            <Notice message={form.errors._general} />
            <Notice tone="success" message={form.message} />
            <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Menyimpan...' : 'Simpan kelas'}
            </Button>
        </form>
    );
}

export default function MasterData() {
    const master = useApiData('/api/v1/master-data');

    return (
        <StudentLayout active="master" title="Data sekolah">
            <Head title="Data Sekolah" />
            <div className="mb-7">
                <p className="eyebrow mb-2">Referensi akademik</p>
                <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Data sekolah</h1>
                <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Kelola mata pelajaran dan kelas yang tersedia untuk kegiatan belajar di SINTAS.</p>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <MapelForm onCreated={master.reload} />
                <KelasForm onCreated={master.reload} />
            </div>

            <div className="mt-8">
                {master.loading ? (
                    <LoadingState />
                ) : master.error ? (
                    <div className="flex flex-col gap-3">
                        <Notice message={master.error} />
                        <Button className="self-start" variant="secondary" onClick={master.reload}>Coba lagi</Button>
                    </div>
                ) : (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <section className="surface p-6">
                            <div className="mb-5 flex items-center justify-between gap-3">
                                <h2 className="text-lg font-bold text-slate-900">Mata pelajaran</h2>
                                <span className="rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700">{master.data?.mapel?.length ?? 0} data</span>
                            </div>
                            {master.data?.mapel?.length ? (
                                <ul className="flex flex-col gap-3">
                                    {master.data.mapel.map((item) => (
                                        <li key={item.id} className="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                                            {item.nama_mapel}
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <EmptyState title="Belum ada mata pelajaran" description="Gunakan formulir di atas untuk menambahkan mata pelajaran pertama." />
                            )}
                        </section>

                        <section className="surface p-6">
                            <div className="mb-5 flex items-center justify-between gap-3">
                                <h2 className="text-lg font-bold text-slate-900">Kelas sekolah</h2>
                                <span className="rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700">{master.data?.kelas?.length ?? 0} data</span>
                            </div>
                            {master.data?.kelas?.length ? (
                                <ul className="flex flex-col gap-3">
                                    {master.data.kelas.map((item) => (
                                        <li key={item.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                            <span className="text-sm font-semibold text-slate-700">{item.nama_kelas}</span>
                                            <span className="text-xs text-slate-500">{item.tahun_ajaran}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <EmptyState title="Belum ada kelas sekolah" description="Gunakan formulir di atas untuk menambahkan kelas pertama." />
                            )}
                        </section>
                    </div>
                )}
            </div>
        </StudentLayout>
    );
}
