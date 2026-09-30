<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\KeanggotaanSekolah;
use App\Models\KelasMapel;
use App\Models\Sekolah;
use App\Services\SchoolContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminSekolahController extends Controller
{
    private function school(Request $request): Sekolah
    {
        $school = app(SchoolContext::class)->school($request);
        abort_unless($school, 403);
        Gate::authorize('manage', $school);

        return $school;
    }

    public function page(Request $request): Response
    {
        $this->school($request);

        return Inertia::render('SchoolAdmin');
    }

    public function index(Request $request): JsonResponse
    {
        $school = $this->school($request);

        return response()->json([
            'school' => [...$school->toArray(), 'subscription_active' => $school->hasActiveSubscription()],
            'members' => $school->anggota()->with('guru:id,nama_lengkap,jenis_guru')->orderByDesc('requested_at')->get()
                ->map(fn (KeanggotaanSekolah $member): array => [...$member->toArray(),
                    'jenis_guru' => $member->jenis_guru ?? $member->guru->jenis_guru]),
            'rooms' => $school->kelasMapel()->with('pembuat:id,nama_lengkap')
                ->withCount(['anggota' => fn ($q) => $q->where('status', 'diterima'), 'tugas'])->latest('id')->get(),
            'stats' => ['guru' => $school->anggota()->where('status', 'diterima')->count(),
                'kelas' => $school->kelasMapel()->count(), 'pending' => $school->anggota()->where('status', 'pending')->count()],
        ]);
    }

    public function review(Request $request, KeanggotaanSekolah $anggota): JsonResponse
    {
        $school = $anggota->sekolah;
        abort_unless($request->user()->isSchoolAdmin($school), 404);
        $data = $request->validate(['status' => ['required', Rule::in(['diterima', 'ditolak'])]]);
        DB::transaction(function () use ($request, $school, $anggota, $data): void {
            $teachers = Guru::query()->whereKey([$school->admin_guru_id, $anggota->guru_id])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $guru = $teachers->get($anggota->guru_id);
            abort_unless($guru, 404);
            $school = Sekolah::query()->lockForUpdate()->findOrFail($school->id);
            Gate::authorize('manage', $school);
            $member = $guru->keanggotaanSekolah()->lockForUpdate()->findOrFail($anggota->id);
            abort_unless($member->sekolah_id === $school->id, 404);
            if ($member->guru_id === $school->admin_guru_id) {
                throw ValidationException::withMessages(['status' => 'Keanggotaan pemilik sekolah tidak dapat diubah di sini.']);
            }
            if ($member->status === $data['status']) {
                return;
            }
            if ($member->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Permintaan ini sudah diproses.']);
            }
            if ($data['status'] === 'diterima' && $guru->user?->status !== 'aktif') {
                throw ValidationException::withMessages(['status' => 'Akun guru harus aktif sebelum diterima.']);
            }
            $member->update([...$data, 'jenis_guru' => 'guru_mapel', 'reviewed_at' => now(), 'reviewed_by' => $request->user()->guru->id]);
            if ($data['status'] === 'diterima') {
                KelasMapel::query()->where('guru_pembuat_id', $guru->id)->whereNull('sekolah_id')
                    ->update(['sekolah_id' => $school->id]);
            }
        });

        return response()->json(['message' => 'Permintaan guru berhasil diproses.']);
    }

    public function updateTeacher(Request $request, Guru $guru): JsonResponse
    {
        $school = $this->school($request);
        abort_unless($guru->keanggotaanSekolah()->where('sekolah_id', $school->id)->where('status', 'diterima')->exists(), 404);
        $data = $request->validate(['jenis_guru' => ['required', Rule::in(['guru_mapel', 'guru_piket'])]]);
        DB::transaction(function () use ($school, $guru, $data): void {
            $locked = Guru::query()->lockForUpdate()->findOrFail($guru->id);
            Gate::authorize('manage', $school);
            abort_unless($locked->keanggotaanSekolah()->where('sekolah_id', $school->id)->where('status', 'diterima')->exists(), 404);
            $locked->keanggotaanSekolah()->where('sekolah_id', $school->id)->where('status', 'diterima')->update($data);
        });

        return response()->json(['message' => 'Jenis guru berhasil diperbarui.']);
    }
}
