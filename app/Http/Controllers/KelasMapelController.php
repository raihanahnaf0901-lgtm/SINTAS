<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKelasMapelRequest;
use App\Http\Requests\UpdateKelasMapelRequest;
use App\Models\KelasMapel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class KelasMapelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', KelasMapel::class);
        $rooms = KelasMapel::query()->accessibleTo($request->user())
            ->with(['mapel', 'pembuat:id,nama_lengkap,gelar'])
            ->withCount(['anggota' => fn (Builder $q) => $q->where('status', 'diterima')])
            ->orderByDesc('id')->paginate(20);
        $pending = $request->user()->siswa?->anggotaKelas()
            ->whereIn('status', ['pending', 'ditolak'])->with('kelasMapel.mapel')->latest()->get() ?? [];

        return response()->json(['data' => $rooms, 'permintaan' => $pending]);
    }

    public function store(StoreKelasMapelRequest $request): JsonResponse
    {
        $kelas = DB::transaction(fn () => KelasMapel::query()->create([
            ...$request->validated(),
            'guru_pembuat_id' => $request->user()->guru->id,
        ]));

        return response()->json(['data' => $this->ownerData($kelas->load('mapel'))], 201);
    }

    public function show(Request $request, KelasMapel $kelasMapel): JsonResponse
    {
        Gate::authorize('view', $kelasMapel);
        $kelasMapel->load(['mapel', 'pembuat:id,nama_lengkap,gelar']);

        return response()->json(['data' => $request->user()->can('update', $kelasMapel)
            ? $this->ownerData($kelasMapel) : $kelasMapel->toArray()]);
    }

    public function update(UpdateKelasMapelRequest $request, KelasMapel $kelasMapel): JsonResponse
    {
        DB::transaction(function () use ($request, $kelasMapel): void {
            $kelas = KelasMapel::query()->lockForUpdate()->findOrFail($kelasMapel->id);
            Gate::authorize('update', $kelas);
            $data = $request->safe()->except('regenerate_invite');
            if ($request->boolean('regenerate_invite')) {
                $data['invite_token'] = Str::random(48);
            }
            $kelas->update($data);
        });

        return response()->json(['data' => $this->ownerData($kelasMapel->refresh())]);
    }

    private function ownerData(KelasMapel $kelas): array
    {
        return [...$kelas->toArray(), 'kode_kelas' => $kelas->kode_kelas,
            'invite_token' => $kelas->invite_token,
            'link_undangan' => route('kelas-mapel.invite', $kelas->invite_token)];
    }

    public function invite(Request $request, string $token): JsonResponse|Response
    {
        $kelas = KelasMapel::query()->where('invite_token', $token)->where('status', 'aktif')->firstOrFail();

        if (! $request->expectsJson()) {
            $request->session()->put('kelas_invite', route('kelas-mapel.invite', $token, false));

            return Inertia::render('JoinClass', [
                'invitation' => [...$kelas->only(['id', 'mapel_id', 'nama_kelas_mapel', 'deskripsi']),
                    'mapel' => $kelas->mapel->nama_mapel, 'guru' => $kelas->pembuat->nama_lengkap, 'token' => $token],
            ]);
        }

        return response()->json(['data' => $kelas->only(['id', 'mapel_id', 'nama_kelas_mapel', 'deskripsi']),
            'message' => 'Masuk ke akun siswa lalu ajukan bergabung dengan invite_token ini. Persetujuan guru tetap diperlukan.']);
    }
}
