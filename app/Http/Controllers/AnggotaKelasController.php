<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\KelasMapel;
use App\Models\Notifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnggotaKelasController extends Controller
{
    public function index(KelasMapel $kelasMapel): JsonResponse
    {
        Gate::authorize('view', $kelasMapel);
        abort_unless(request()->user()->role === 'guru', 403);

        return response()->json(['data' => $kelasMapel->anggota()->with('siswa:id,nama_lengkap,nis,kelas_id')
            ->orderByDesc('requested_at')->paginate(50)]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'siswa' && $request->user()->siswa, 403);
        if (! filled($request->user()->siswa->nis)) {
            throw ValidationException::withMessages(['profil' => 'Lengkapi nama dan NIS pada profil sebelum bergabung ke kelas.']);
        }
        $data = $request->validate([
            'mapel_id' => ['required', 'integer', 'exists:mapel,id'],
            'kode_kelas' => ['required_without:invite_token', 'prohibits:invite_token', 'nullable', 'string', 'max:32'],
            'invite_token' => ['required_without:kode_kelas', 'prohibits:kode_kelas', 'nullable', 'string', 'max:64'],
        ]);
        $membership = DB::transaction(function () use ($request, $data): AnggotaKelas {
            $byLink = filled($data['invite_token'] ?? null);
            $kelas = KelasMapel::query()->where('status', 'aktif')->where('mapel_id', $data['mapel_id'])
                ->where($byLink ? 'invite_token' : 'kode_kelas',
                    $byLink ? $data['invite_token'] : Str::upper(trim($data['kode_kelas'])))
                ->lockForUpdate()->first();
            if (! $kelas) {
                throw ValidationException::withMessages(['kode_kelas' => 'Kode/link tidak cocok dengan mata pelajaran atau kelas sudah diarsipkan.']);
            }
            $anggota = $kelas->anggota()->where('siswa_id', $request->user()->siswa->id)->first();
            if ($anggota && $anggota->status !== 'ditolak') {
                throw ValidationException::withMessages(['kode_kelas' => 'Anda sudah bergabung atau masih menunggu persetujuan.']);
            }
            $anggota = $kelas->anggota()->updateOrCreate(['siswa_id' => $request->user()->siswa->id], [
                'status' => 'pending', 'join_method' => $byLink ? 'link' : 'kode',
                'requested_at' => now(), 'approved_at' => null, 'approved_by' => null,
            ]);
            if ($kelas->pembuat->user_id) {
                Notifikasi::query()->create(['user_id' => $kelas->pembuat->user_id,
                    'judul' => 'Permintaan bergabung', 'pesan' => $request->user()->name.' ingin bergabung ke '.$kelas->nama_kelas_mapel,
                    'tipe' => 'permintaan_kelas', 'data' => ['kelas_mapel_id' => $kelas->id, 'anggota_id' => $anggota->id]]);
            }

            return $anggota;
        });

        return response()->json(['data' => $membership, 'message' => 'Permintaan dikirim. Tunggu persetujuan pemilik kelas.'], 201);
    }

    public function update(Request $request, KelasMapel $kelasMapel, AnggotaKelas $anggota): JsonResponse
    {
        abort_unless($anggota->kelas_mapel_id === $kelasMapel->id, 404);
        Gate::authorize('reviewMembers', $kelasMapel);
        $data = $request->validate(['status' => ['required', Rule::in(['diterima', 'ditolak'])]]);
        DB::transaction(function () use ($request, $kelasMapel, $anggota, $data): void {
            $kelas = KelasMapel::query()->lockForUpdate()->findOrFail($kelasMapel->id);
            Gate::authorize('reviewMembers', $kelas);
            $member = $kelas->anggota()->lockForUpdate()->findOrFail($anggota->id);
            if ($member->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Permintaan ini sudah ditinjau.']);
            }
            $member->update(['status' => $data['status'], 'approved_at' => now(), 'approved_by' => $request->user()->guru->id]);
            if ($member->siswa->user_id) {
                Notifikasi::query()->create(['user_id' => $member->siswa->user_id,
                    'judul' => 'Keputusan keanggotaan kelas', 'pesan' => 'Permintaan Anda '.$data['status'].' di '.$kelas->nama_kelas_mapel,
                    'tipe' => 'keanggotaan_kelas', 'data' => ['kelas_mapel_id' => $kelas->id, 'status' => $data['status']]]);
            }
        });

        return response()->json(['data' => $anggota->refresh()]);
    }
}
