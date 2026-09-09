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

        return response()->json(['data' => $kelasMapel->whitelist()->with('guru:id,nama_lengkap,jenis_guru')->get()]);
    }

    public function store(Request $request, KelasMapel $kelasMapel): JsonResponse
    {
        Gate::authorize('update', $kelasMapel);
        $data = $request->validate([
            'guru_id' => ['required', 'integer', 'exists:guru,id'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);
        $entry = DB::transaction(function () use ($request, $kelasMapel, $data) {
            $kelas = KelasMapel::query()->lockForUpdate()->findOrFail($kelasMapel->id);
            Gate::authorize('update', $kelas);
            if ((int) $data['guru_id'] === $kelas->guru_pembuat_id && $data['status'] !== 'aktif') {
                throw ValidationException::withMessages(['status' => 'Pemilik kelas harus tetap aktif dalam whitelist.']);
            }
            $guru = Guru::query()->with('user')->findOrFail($data['guru_id']);
            if ($data['status'] === 'aktif' && ($guru->user?->status !== 'aktif' || $guru->user?->role !== 'guru')) {
                throw ValidationException::withMessages(['guru_id' => 'Akun guru harus aktif.']);
            }

            return $kelas->whitelist()->updateOrCreate(['guru_id' => $guru->id], [
                'status' => $data['status'], 'ditambahkan_oleh' => $request->user()->guru->id,
            ]);
        });

        return response()->json(['data' => $entry]);
    }
}
