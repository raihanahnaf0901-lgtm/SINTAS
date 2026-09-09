<?php

namespace App\Services;

use App\Models\KelasMapel;
use App\Models\Notifikasi;

class AcademicNotification
{
    public function kelas(KelasMapel $kelas, string $judul, string $pesan, string $tipe, array $data = []): void
    {
        $kelas->anggota()->where('status', 'diterima')->with('siswa:id,user_id')->chunkById(100, function ($anggota) use ($kelas, $judul, $pesan, $tipe, $data): void {
            foreach ($anggota as $member) {
                if ($member->siswa->user_id) {
                    Notifikasi::query()->create([
                        'user_id' => $member->siswa->user_id, 'judul' => $judul, 'pesan' => $pesan,
                        'tipe' => $tipe, 'data' => ['kelas_mapel_id' => $kelas->id, ...$data],
                    ]);
                }
            }
        });
    }
}
