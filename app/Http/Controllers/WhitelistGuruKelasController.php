<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\KelasMapel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WhitelistGuruKelasController extends Controller
{
    public function index(KelasMapel $kelasMapel): JsonResponse
    {
        Gate::authorize('view', $kelasMapel);
        $entries = $kelasMapel->whitelist()->with([
            'guru:id,nama_lengkap,jenis_guru',
            'guru.keanggotaanSekolah' => fn ($q) => $q->where('sekolah_id', $kelasMapel->sekolah_id)->where('status', 'diterima'),
        ])->get();
        foreach ($entries as $entry) {
            $entry->guru->jenis_guru = $entry->guru->keanggotaanSekolah->first()?->jenis_guru ?? $entry->guru->jenis_guru;
            $entry->guru->unsetRelation('keanggotaanSekolah');
        }
        $admin = $kelasMapel->sekolah?->admin;

        return response()->json([
            'data' => $entries,
            'implicit_admin' => $admin?->user?->isSchoolAdmin($kelasMapel->sekolah)
                ? $admin->only(['id', 'nama_lengkap']) : null,
        ]);
    }

    public function store(Request $request, KelasMapel $kelasMapel): JsonResponse
    {
        Gate::authorize('update', $kelasMapel);
        $data = $request->validate([
            'guru_id' => ['required', 'integer', 'exists:guru,id'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);
        $entry = DB::transaction(function () use ($request, $kelasMapel, $data) {
            $actingGuruId = $request->user()->guru->id;
            $teachers = Guru::query()->with('user')->whereKey([$actingGuruId, $data['guru_id']])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($teachers->has($actingGuruId) && $teachers->has($data['guru_id']), 404);
            $request->user()->setRelation('guru', $teachers->get($actingGuruId));
            $kelas = KelasMapel::query()->lockForUpdate()->findOrFail($kelasMapel->id);
            Gate::authorize('update', $kelas);
            if ((int) $data['guru_id'] === $kelas->guru_pembuat_id && $data['status'] !== 'aktif') {
                throw ValidationException::withMessages(['status' => 'Pemilik kelas harus tetap aktif dalam whitelist.']);
            }
            if ($kelas->sekolah_id !== null && (int) $data['guru_id'] === $kelas->sekolah->admin_guru_id
                && $data['status'] !== 'aktif') {
                throw ValidationException::withMessages(['status' => 'Admin sekolah memiliki akses tetap dan tidak dapat dinonaktifkan melalui whitelist kelas.']);
            }
            $guru = $teachers->get($data['guru_id']);
            if ($data['status'] === 'aktif' && ($guru->user?->status !== 'aktif' || $guru->user?->role !== 'guru')) {
                throw ValidationException::withMessages(['guru_id' => 'Akun guru harus aktif.']);
            }
            if ($data['status'] === 'aktif') {
                $schoolId = $kelas->sekolah_id ?? $kelas->pembuat->sekolahAktif()?->id;
                if (($schoolId !== null && $guru->sekolahAktif($schoolId) === null)
                    || ($schoolId === null && $guru->sekolahAktif() !== null)) {
                    throw ValidationException::withMessages(['guru_id' => 'Guru harus sudah diterima di sekolah yang sama.']);
                }
            }

            return $kelas->whitelist()->updateOrCreate(['guru_id' => $guru->id], [
                'status' => $data['status'], 'ditambahkan_oleh' => $request->user()->guru->id,
            ]);
        });

        return response()->json(['data' => $entry]);
    }
}
