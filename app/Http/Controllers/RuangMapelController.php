<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRuangMapelRequest;
use App\Models\Jadwal;
use App\Models\KeanggotaanRuangMapel;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\RuangMapel;
use App\StatusKeanggotaanRuangMapel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RuangMapelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', RuangMapel::class);

        if ($request->user()->role === 'guru') {
            $guru = $request->user()->guru()->firstOrFail();
            $jadwalTersedia = Jadwal::query()
                ->whereBelongsTo($guru)
                ->with(['mapel:id,kode_mapel,nama_mapel', 'kelas:id,nama_kelas'])
                ->orderBy('mapel_id')
                ->orderBy('kelas_id')
                ->get()
                ->unique(fn (Jadwal $jadwal): string => $jadwal->mapel_id.'-'.$jadwal->kelas_id)
                ->map(fn (Jadwal $jadwal): array => [
                    'mapel' => [
                        'id' => $jadwal->mapel->id,
                        'kode' => $jadwal->mapel->kode_mapel,
                        'nama' => $jadwal->mapel->nama_mapel,
                    ],
                    'kelas' => [
                        'id' => $jadwal->kelas->id,
                        'nama' => $jadwal->kelas->nama_kelas,
                    ],
                ])
                ->values();
            $ruangMapels = RuangMapel::query()
                ->whereBelongsTo($guru)
                ->with([
                    'mapel:id,kode_mapel,nama_mapel',
                    'kelas:id,nama_kelas',
                    'permintaanMenunggu.siswa:id,nama_lengkap,nis',
                ])
                ->withCount([
                    'keanggotaans as jumlah_siswa' => fn (Builder $query): Builder => $query
                        ->where('status', StatusKeanggotaanRuangMapel::Diterima),
                    'keanggotaans as jumlah_menunggu' => fn (Builder $query): Builder => $query
                        ->where('status', StatusKeanggotaanRuangMapel::Menunggu),
                ])
                ->latest()
                ->orderByDesc('id')
                ->get()
                ->map(fn (RuangMapel $ruangMapel): array => $this->roomData($ruangMapel));

            return response()->json([
                'data' => $ruangMapels,
                'jadwal_tersedia' => $jadwalTersedia,
            ]);
        }

        $siswa = $request->user()->siswa()->firstOrFail();
        $keanggotaans = $siswa->keanggotaanRuangMapels()
            ->with([
                'ruangMapel.mapel:id,kode_mapel,nama_mapel',
                'ruangMapel.kelas:id,nama_kelas',
                'ruangMapel.guru:id,nama,gelar',
            ])
            ->latest()
            ->orderByDesc('id')
            ->get()
            ->map(fn (KeanggotaanRuangMapel $keanggotaan): array => [
                'id' => $keanggotaan->id,
                'status' => $keanggotaan->status->value,
                'diajukan_pada' => $keanggotaan->created_at?->toISOString(),
                'ditinjau_pada' => $keanggotaan->ditinjau_pada?->toISOString(),
                'ruang' => $this->roomData($keanggotaan->ruangMapel),
            ]);

        return response()->json(['data' => $keanggotaans]);
    }

    public function store(StoreRuangMapelRequest $request): JsonResponse
    {
        Gate::authorize('create', RuangMapel::class);

        $guru = $request->user()->guru()->firstOrFail();
        $data = $request->validated();

        $mengajarKelasTersebut = Jadwal::query()
            ->whereBelongsTo($guru)
            ->where('mapel_id', $data['mapel_id'])
            ->where('kelas_id', $data['kelas_id'])
            ->exists();

        if (! $mengajarKelasTersebut) {
            throw ValidationException::withMessages([
                'mapel_id' => 'Anda tidak memiliki jadwal mata pelajaran ini untuk kelas yang dipilih.',
            ]);
        }

        $sudahAda = RuangMapel::query()
            ->whereBelongsTo($guru)
            ->where('mapel_id', $data['mapel_id'])
            ->where('kelas_id', $data['kelas_id'])
            ->exists();

        if ($sudahAda) {
            throw ValidationException::withMessages([
                'mapel_id' => 'Ruang mata pelajaran untuk jadwal ini sudah dibuat.',
            ]);
        }

        $mapel = Mapel::query()->findOrFail($data['mapel_id']);
        $kelas = Kelas::query()->findOrFail($data['kelas_id']);

        $ruangMapel = RuangMapel::query()->create([
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'kelas_id' => $kelas->id,
            'nama_ruang' => ($data['nama_ruang'] ?? null) ?: $mapel->nama_mapel.' - '.$kelas->nama_kelas,
        ])->load(['mapel:id,kode_mapel,nama_mapel', 'kelas:id,nama_kelas']);

        return response()->json([
            'message' => 'Ruang mata pelajaran berhasil dibuat.',
            'data' => $this->roomData($ruangMapel),
        ], 201);
    }

    public function show(Request $request, RuangMapel $ruangMapel): JsonResponse
    {
        Gate::authorize('view', $ruangMapel);

        $ruangMapel->load([
            'mapel:id,kode_mapel,nama_mapel',
            'kelas:id,nama_kelas',
            'guru:id,nama,gelar',
        ]);

        $data = $this->roomData($ruangMapel);

        if ($request->user()->role === 'siswa') {
            $siswa = $request->user()->siswa()->firstOrFail();
            $data['status_keanggotaan'] = $ruangMapel->keanggotaans()
                ->whereBelongsTo($siswa)
                ->value('status');
        }

        return response()->json(['data' => $data]);
    }

    /**
     * @return array<string, mixed>
     */
    private function roomData(RuangMapel $ruangMapel): array
    {
        return [
            'id' => $ruangMapel->id,
            'nama_ruang' => $ruangMapel->nama_ruang,
            'kode' => $ruangMapel->kode,
            'aktif' => $ruangMapel->aktif,
            'link_undangan' => route('ruang-mapels.show', $ruangMapel),
            'mapel' => [
                'id' => $ruangMapel->mapel->id,
                'kode' => $ruangMapel->mapel->kode_mapel,
                'nama' => $ruangMapel->mapel->nama_mapel,
            ],
            'kelas' => [
                'id' => $ruangMapel->kelas->id,
                'nama' => $ruangMapel->kelas->nama_kelas,
            ],
            'guru' => $ruangMapel->relationLoaded('guru') ? [
                'id' => $ruangMapel->guru->id,
                'nama' => $ruangMapel->guru->nama,
                'gelar' => $ruangMapel->guru->gelar,
            ] : null,
            'jumlah_siswa' => $ruangMapel->jumlah_siswa ?? null,
            'jumlah_menunggu' => $ruangMapel->jumlah_menunggu ?? null,
            'permintaan_menunggu' => $ruangMapel->relationLoaded('permintaanMenunggu')
                ? $ruangMapel->permintaanMenunggu->map(fn (KeanggotaanRuangMapel $keanggotaan): array => [
                    'id' => $keanggotaan->id,
                    'siswa' => [
                        'id' => $keanggotaan->siswa->id,
                        'nama' => $keanggotaan->siswa->nama_lengkap,
                        'nis' => $keanggotaan->siswa->nis,
                    ],
                    'diajukan_pada' => $keanggotaan->created_at?->toISOString(),
                ])->values()
                : null,
        ];
    }
}
