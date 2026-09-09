<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\Mapel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MasterDataController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'mapel' => Mapel::query()->orderBy('nama_mapel')->get(),
            'kelas' => Kelas::query()->where('status', 'aktif')->orderBy('nama_kelas')->get(['id', 'nama_kelas', 'tahun_ajaran']),
            'guru' => $request->user()->role === 'guru'
                ? Guru::query()->whereHas('user', fn ($q) => $q->where('role', 'guru')->where('status', 'aktif'))
                    ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'gelar', 'jenis_guru']) : [],
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
