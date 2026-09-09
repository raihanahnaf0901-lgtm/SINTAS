<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KelasMapel;
use App\Models\Tugas;
use App\Services\AcademicAccess;
use App\Services\AcademicNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TugasController extends Controller
{
    public function index(Request $request, KelasMapel $kelasMapel): JsonResponse
    {
        Gate::authorize('view', $kelasMapel);
        $query = $kelasMapel->tugas()->orderBy('deadline');
        if ($request->user()->role === 'siswa') {
            $siswaId = $request->user()->siswa->id;
            $query->with(['pengumpulan' => fn ($q) => $q->where('siswa_id', $siswaId),
                'penilaian' => fn ($q) => $q->where('siswa_id', $siswaId)]);
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function store(Request $request, KelasMapel $kelasMapel, AcademicAccess $access, AcademicNotification $notifications): JsonResponse
    {
        Gate::authorize('manageAcademic', $kelasMapel);
        $data = $request->validate($this->rules());
        $tugas = $access->write($kelasMapel, function (KelasMapel $kelas) use ($request, $data, $notifications): Tugas {
            $tugas = $kelas->tugas()->create([...$data, 'guru_id' => $request->user()->guru->id]);
            $notifications->kelas($kelas, 'Tugas baru', $tugas->judul, 'tugas', ['tugas_id' => $tugas->id]);

            return $tugas;
        });

        return response()->json(['data' => $tugas], 201);
    }

    public function update(Request $request, KelasMapel $kelasMapel, Tugas $tugas, AcademicAccess $access): JsonResponse
    {
        abort_unless($tugas->kelas_mapel_id === $kelasMapel->id, 404);
        Gate::authorize('manageAcademic', $kelasMapel);
        $data = $request->validate($this->rules());
        $access->write($kelasMapel, fn () => $tugas->update($data));

        return response()->json(['data' => $tugas->refresh()]);
    }

    private function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:255'],
            'jenis' => ['required', Rule::in(['tugas', 'pr', 'proyek'])],
            'deskripsi' => ['nullable', 'string', 'max:20000'],
            'deadline' => ['required', 'date'],
        ];
    }
}
