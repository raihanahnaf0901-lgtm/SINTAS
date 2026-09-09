<?php

namespace App\Http\Controllers;

use App\Models\KelasMapel;
use App\Models\Ujian;
use App\Services\AcademicAccess;
use App\Services\AcademicNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UjianController extends Controller
{
    public function index(Request $request, KelasMapel $kelasMapel): JsonResponse
    {
        Gate::authorize('view', $kelasMapel);
        $query = $kelasMapel->ujian()->orderBy('tanggal');
        if ($request->user()->role === 'siswa') {
            $query->with(['penilaian' => fn ($q) => $q->where('siswa_id', $request->user()->siswa->id)]);
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function store(Request $request, KelasMapel $kelasMapel, AcademicAccess $access, AcademicNotification $notifications): JsonResponse
    {
        Gate::authorize('manageAcademic', $kelasMapel);
        $data = $request->validate($this->rules());
        $ujian = $access->write($kelasMapel, function (KelasMapel $kelas) use ($request, $data, $notifications): Ujian {
            $ujian = $kelas->ujian()->create([...$data, 'guru_id' => $request->user()->guru->id]);
            $notifications->kelas($kelas, 'Jadwal ujian', $ujian->judul, 'ujian', ['ujian_id' => $ujian->id]);

            return $ujian;
        });

        return response()->json(['data' => $ujian], 201);
    }

    public function update(Request $request, KelasMapel $kelasMapel, Ujian $ujian, AcademicAccess $access): JsonResponse
    {
        abort_unless($ujian->kelas_mapel_id === $kelasMapel->id, 404);
        Gate::authorize('manageAcademic', $kelasMapel);
        $data = $request->validate($this->rules());
        $access->write($kelasMapel, fn () => $ujian->update($data));

        return response()->json(['data' => $ujian->refresh()]);
    }

    private function rules(): array
    {
        return ['judul' => ['required', 'string', 'max:255'], 'jenis_ujian' => ['required', Rule::in(['UH', 'US'])],
            'tanggal' => ['required', 'date_format:Y-m-d'], 'keterangan' => ['nullable', 'string', 'max:20000']];
    }
}
