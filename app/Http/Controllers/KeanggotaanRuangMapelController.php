<?php

namespace App\Http\Controllers;

use App\Http\Requests\JoinRuangMapelRequest;
use App\Http\Requests\ReviewKeanggotaanRuangMapelRequest;
use App\Models\KeanggotaanRuangMapel;
use App\Models\RuangMapel;
use App\StatusKeanggotaanRuangMapel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KeanggotaanRuangMapelController extends Controller
{
    public function store(JoinRuangMapelRequest $request): JsonResponse
    {
        $siswa = $request->user()->siswa()->firstOrFail();
        $ruangMapel = RuangMapel::query()
            ->where('kode', $request->string('kode')->toString())
            ->where('aktif', true)
            ->firstOrFail();

        if ($siswa->kelas_id !== $ruangMapel->kelas_id) {
            throw ValidationException::withMessages([
                'kode' => 'Ruang mata pelajaran ini bukan untuk kelas Anda.',
            ]);
        }

        $keanggotaan = DB::transaction(function () use ($siswa, $ruangMapel): KeanggotaanRuangMapel {
            $keanggotaan = KeanggotaanRuangMapel::query()
                ->whereBelongsTo($ruangMapel)
                ->whereBelongsTo($siswa)
                ->lockForUpdate()
                ->first();

            if ($keanggotaan?->status === StatusKeanggotaanRuangMapel::Menunggu) {
                throw ValidationException::withMessages([
                    'kode' => 'Permintaan Anda masih menunggu persetujuan guru.',
                ]);
            }

            if ($keanggotaan?->status === StatusKeanggotaanRuangMapel::Diterima) {
                throw ValidationException::withMessages([
                    'kode' => 'Anda sudah menjadi anggota ruang mata pelajaran ini.',
                ]);
            }

            if ($keanggotaan) {
                $keanggotaan->update([
                    'status' => StatusKeanggotaanRuangMapel::Menunggu,
                    'ditinjau_oleh' => null,
                    'ditinjau_pada' => null,
                ]);

                return $keanggotaan;
            }

            return KeanggotaanRuangMapel::query()->create([
                'ruang_mapel_id' => $ruangMapel->id,
                'siswa_id' => $siswa->id,
            ]);
        });

        return response()->json([
            'message' => 'Permintaan bergabung berhasil dikirim dan menunggu persetujuan guru.',
            'data' => [
                'id' => $keanggotaan->id,
                'status' => $keanggotaan->status->value,
                'ruang_mapel_id' => $ruangMapel->id,
            ],
        ], 201);
    }

    public function update(
        ReviewKeanggotaanRuangMapelRequest $request,
        string $keanggotaanRuangMapel,
    ): JsonResponse {
        $guru = $request->user()->guru()->firstOrFail();
        $status = StatusKeanggotaanRuangMapel::from($request->string('status')->toString());

        $keanggotaan = DB::transaction(function () use (
            $keanggotaanRuangMapel,
            $guru,
            $status,
        ): KeanggotaanRuangMapel {
            $keanggotaan = KeanggotaanRuangMapel::query()
                ->whereKey($keanggotaanRuangMapel)
                ->whereHas('ruangMapel', fn (Builder $query): Builder => $query->whereBelongsTo($guru))
                ->lockForUpdate()
                ->firstOrFail();

            if ($keanggotaan->status !== StatusKeanggotaanRuangMapel::Menunggu) {
                throw ValidationException::withMessages([
                    'status' => 'Permintaan bergabung ini sudah ditinjau.',
                ]);
            }

            $keanggotaan->update([
                'status' => $status,
                'ditinjau_oleh' => $guru->id,
                'ditinjau_pada' => now(),
            ]);

            return $keanggotaan;
        });

        return response()->json([
            'message' => $status === StatusKeanggotaanRuangMapel::Diterima
                ? 'Siswa berhasil diterima ke ruang mata pelajaran.'
                : 'Permintaan siswa berhasil ditolak.',
            'data' => [
                'id' => $keanggotaan->id,
                'status' => $keanggotaan->status->value,
                'ditinjau_pada' => $keanggotaan->ditinjau_pada?->toISOString(),
            ],
        ]);
    }
}
