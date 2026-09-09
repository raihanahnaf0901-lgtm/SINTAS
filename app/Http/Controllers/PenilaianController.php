<?php

namespace App\Http\Controllers;

use App\Models\KelasMapel;
use App\Models\Notifikasi;
use App\Models\Penilaian;
use App\Services\AcademicAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PenilaianController extends Controller
{
    public function index(Request $request, KelasMapel $kelasMapel): JsonResponse
    {
        Gate::authorize('view', $kelasMapel);
        $query = Penilaian::query()->where(fn ($q) => $q
            ->whereHas('tugas', fn ($t) => $t->where('kelas_mapel_id', $kelasMapel->id))
            ->orWhereHas('ujian', fn ($u) => $u->where('kelas_mapel_id', $kelasMapel->id)));
        if ($request->user()->role === 'siswa') {
            $query->where('siswa_id', $request->user()->siswa->id);
        }
        $query->with(['siswa:id,nama_lengkap', 'tugas:id,judul', 'ujian:id,judul']);

        return response()->json(['data' => $query->latest('dinilai_at')->paginate(50)]);
    }

    public function store(Request $request, KelasMapel $kelasMapel, AcademicAccess $access): JsonResponse
    {
        Gate::authorize('manageAcademic', $kelasMapel);
        $data = $request->validate([
            'siswa_id' => ['required', 'integer', 'exists:siswa,id'],
            'tugas_id' => ['required_without:ujian_id', 'prohibits:ujian_id', 'nullable', 'integer'],
            'ujian_id' => ['required_without:tugas_id', 'prohibits:tugas_id', 'nullable', 'integer'],
            'nilai' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'catatan' => ['nullable', 'string', 'max:10000'],
        ]);
        $nilai = $access->write($kelasMapel, function (KelasMapel $kelas) use ($request, $data): Penilaian {
            $member = $kelas->anggota()->where('siswa_id', $data['siswa_id'])->where('status', 'diterima')->first();
            if (! $member) {
                throw ValidationException::withMessages(['siswa_id' => 'Siswa belum diterima di kelas ini.']);
            }
            $isTugas = filled($data['tugas_id'] ?? null);
            $targetKey = $isTugas ? 'tugas_id' : 'ujian_id';
            $target = ($isTugas ? $kelas->tugas() : $kelas->ujian())->find($data[$targetKey]);
            if (! $target) {
                throw ValidationException::withMessages([$targetKey => 'Target penilaian bukan milik kelas ini.']);
            }
            $nilai = Penilaian::query()->updateOrCreate(['siswa_id' => $data['siswa_id'], $targetKey => $target->id], [
                'tugas_id' => $isTugas ? $target->id : null, 'ujian_id' => $isTugas ? null : $target->id,
                'nilai' => $data['nilai'], 'catatan' => $data['catatan'] ?? null,
                'dinilai_oleh' => $request->user()->guru->id, 'dinilai_at' => now(),
            ]);
            if ($member->siswa->user_id) {
                Notifikasi::query()->create(['user_id' => $member->siswa->user_id, 'judul' => 'Nilai diperbarui',
                    'pesan' => 'Nilai untuk '.$target->judul.' sudah tersedia.', 'tipe' => 'penilaian',
                    'data' => ['kelas_mapel_id' => $kelas->id, 'penilaian_id' => $nilai->id]]);
            }

            return $nilai;
        });

        return response()->json(['data' => $nilai]);
    }
}
