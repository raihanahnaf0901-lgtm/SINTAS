<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\KelasMapel;
use App\Services\AcademicAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class JadwalController extends Controller
{
    public function index(KelasMapel $kelasMapel): JsonResponse
    {
        Gate::authorize('view', $kelasMapel);

        return response()->json(['data' => $kelasMapel->jadwal()->orderBy('hari')->orderBy('jam_mulai')->get()]);
    }

    public function store(Request $request, KelasMapel $kelasMapel, AcademicAccess $access): JsonResponse
    {
        Gate::authorize('manageAcademic', $kelasMapel);
        $data = $request->validate($this->rules());
        $jadwal = $access->write($kelasMapel, fn (KelasMapel $kelas) => $kelas->jadwal()->create([
            ...$data, 'guru_id' => $request->user()->guru->id,
        ]));

        return response()->json(['data' => $jadwal], 201);
    }

    public function update(Request $request, KelasMapel $kelasMapel, Jadwal $jadwal, AcademicAccess $access): JsonResponse
    {
        abort_unless($jadwal->kelas_mapel_id === $kelasMapel->id, 404);
        Gate::authorize('manageAcademic', $kelasMapel);
        $data = $request->validate($this->rules());
        $access->write($kelasMapel, fn () => $jadwal->update($data));

        return response()->json(['data' => $jadwal->refresh()]);
    }

    private function rules(): array
    {
        return [
            'hari' => ['required', Rule::in(['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'])],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'ruangan' => ['nullable', 'string', 'max:50'],
        ];
    }
}
