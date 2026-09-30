<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\Mapel;
use App\Services\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MasterDataController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['kelas_mapel_id' => ['nullable', 'integer', 'min:1']]);
        $school = null;
        if ($request->filled('kelas_mapel_id')) {
            abort_unless($request->user()->role === 'guru', 403);
            $kelas = KelasMapel::query()->findOrFail($request->integer('kelas_mapel_id'));
            Gate::authorize('view', $kelas);
            $school = $kelas->sekolah;
        } elseif ($request->user()->role === 'guru') {
            $school = app(SchoolContext::class)->school($request);
        }
        $members = fn (Builder|Relation $query) => $query->where('sekolah_id', $school?->id)->where('status', 'diterima');
        $teachers = $school === null ? collect() : Guru::query()
            ->whereHas('user', fn (Builder $query) => $query->where('role', 'guru')->where('status', 'aktif'))
            ->whereHas('keanggotaanSekolah', $members)->with(['keanggotaanSekolah' => $members])
            ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'gelar', 'jenis_guru'])
            ->map(fn (Guru $guru): array => [
                ...$guru->only(['id', 'nama_lengkap', 'gelar']),
                'jenis_guru' => $guru->keanggotaanSekolah->first()?->jenis_guru ?? $guru->jenis_guru,
            ]);

        return response()->json([
            'mapel' => Mapel::query()->orderBy('nama_mapel')->get(),
            'kelas' => Kelas::query()->where('status', 'aktif')->orderBy('nama_kelas')->get(['id', 'nama_kelas', 'tahun_ajaran']),
            'guru' => $teachers,
        ]);
    }

    public function mapel(Request $request): JsonResponse
    {
        Gate::authorize('create', KelasMapel::class);
        $data = $request->validate(['nama_mapel' => ['required', 'string', 'max:255']]);

        return response()->json(['data' => Mapel::query()->create($data)], 201);
    }

    public function kelas(Request $request): JsonResponse
    {
        Gate::authorize('create', KelasMapel::class);
        $data = $request->validate(['nama_kelas' => ['required', 'string', 'max:255'],
            'tahun_ajaran' => ['required', 'string', 'max:20']]);

        return response()->json(['data' => Kelas::query()->create([...$data, 'status' => 'aktif'])], 201);
    }
}
