<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (['siswas' => 'siswa', 'gurus' => 'guru'] as $source => $target) {
                if (DB::table($source)->whereNotNull('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->exists()) {
                    throw new RuntimeException('Relasi user ganda di '.$source.'. Perbaiki sebelum mengulang migrasi.');
                }
            }
            if (DB::table('penilaians')->whereNull('tugas_id')->exists()) {
                throw new RuntimeException('Ada penilaian lama tanpa tugas. Petakan ke tugas/ujian sebelum migrasi; data asli tetap tersedia di penilaians.');
            }
            if (DB::table('penilaians')->groupBy('siswa_id', 'tugas_id')->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Ada nilai ganda untuk siswa/tugas. Tentukan nilai yang berlaku sebelum migrasi.');
            }
            DB::table('siswas')->orderBy('id')->each(function (object $row): void {
                DB::table('siswa')->insert((array) collect((array) $row)->only([
                    'id', 'user_id', 'kelas_id', 'nis', 'nisn', 'nama_lengkap', 'created_at', 'updated_at',
                ])->all());
            });
            DB::table('gurus')->orderBy('id')->each(function (object $row): void {
                DB::table('guru')->insert([
                    'id' => $row->id, 'user_id' => $row->user_id, 'nip' => $row->nip,
                    'nama_lengkap' => $row->nama, 'gelar' => $row->gelar, 'jenis_guru' => 'guru_mapel',
                    'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
                ]);
            });
            DB::table('mapels')->orderBy('id')->each(function (object $row): void {
                DB::table('mapel')->insert([
                    'id' => $row->id, 'nama_mapel' => $row->nama_mapel,
                    'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
                ]);
            });
            DB::table('ruang_mapels')->orderBy('id')->each(function (object $row): void {
                DB::table('kelas_mapel')->insert([
                    'id' => $row->id, 'mapel_id' => $row->mapel_id, 'guru_pembuat_id' => $row->guru_id,
                    'nama_kelas_mapel' => $row->nama_ruang, 'kode_kelas' => $row->kode,
                    'invite_token' => Str::random(48), 'status' => $row->aktif ? 'aktif' : 'arsip',
                    'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
                ]);
                $this->whitelistOwner($row->id, $row->guru_id);
            });
            DB::table('keanggotaan_ruang_mapels')->orderBy('id')->each(function (object $row): void {
                DB::table('anggota_kelas')->insert([
                    'id' => $row->id, 'kelas_mapel_id' => $row->ruang_mapel_id, 'siswa_id' => $row->siswa_id,
                    'status' => $row->status === 'menunggu' ? 'pending' : $row->status,
                    'join_method' => 'kode', 'requested_at' => $row->created_at ?? now(),
                    'approved_at' => $row->ditinjau_pada, 'approved_by' => $row->ditinjau_oleh,
                    'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
                ]);
            });
            DB::table('jadwals')->orderBy('id')->each(function (object $row): void {
                if (strtolower($row->hari) === 'minggu') {
                    throw new RuntimeException('Jadwal Minggu tidak ada pada skema final. Sesuaikan jadwal sebelum migrasi.');
                }
                DB::table('jadwal')->insert([
                    'id' => $row->id, 'kelas_mapel_id' => $this->roomFor($row), 'guru_id' => $row->guru_id,
                    'hari' => strtolower($row->hari), 'jam_mulai' => $row->jam_mulai,
                    'jam_selesai' => $row->jam_selesai, 'ruangan' => $row->ruangan,
                    'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
                ]);
            });
            DB::table('legacy_tugas')->orderBy('id')->each(function (object $row): void {
                DB::table('tugas')->insert([
                    'id' => $row->id, 'kelas_mapel_id' => $this->roomFor($row), 'guru_id' => $row->guru_id,
                    'judul' => $row->judul, 'jenis' => 'tugas', 'deskripsi' => $row->deskripsi,
                    'deadline' => $row->deadline, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
                ]);
            });
            DB::table('penilaians')->orderBy('id')->each(function (object $row): void {
                DB::table('penilaian')->insert([
                    'id' => $row->id, 'siswa_id' => $row->siswa_id, 'tugas_id' => $row->tugas_id,
                    'nilai' => $row->nilai, 'dinilai_oleh' => $row->guru_id, 'catatan' => $row->catatan,
                    'dinilai_at' => $row->tanggal_penilaian ?? $row->created_at ?? now(),
                    'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
                ]);
            });
            DB::table('siswa_login_codes')->orderBy('id')->each(function (object $row): void {
                DB::table('otp_verifications')->insert([
                    'id' => $row->id, 'user_id' => $row->user_id, 'code_hash' => $row->code_hash,
                    'purpose' => 'login', 'expires_at' => $row->expires_at, 'used_at' => $row->used_at,
                    'attempts' => $row->attempts, 'created_at' => $row->created_at,
                ]);
            });
            DB::table('legacy_notifikasi')->orderBy('id')->each(function (object $row): void {
                $profileTable = $row->tipe_user === 'Siswa' ? 'siswa' : 'guru';
                $userId = DB::table($profileTable)->where('id', $row->user_id)->value('user_id');
                if (! $userId) {
                    throw new RuntimeException('Penerima notifikasi lama tidak memiliki akun users. Hubungkan profil sebelum migrasi.');
                }
                DB::table('notifikasi')->insert([
                    'id' => $row->id, 'user_id' => $userId, 'judul' => $row->judul, 'pesan' => $row->pesan,
                    'tipe' => 'legacy', 'read_at' => $row->is_read ? ($row->updated_at ?? now()) : null,
                    'created_at' => $row->waktu_kirim ?? $row->created_at,
                ]);
            });
        });
    }

    private function whitelistOwner(int $roomId, int $guruId): void
    {
        DB::table('whitelist_guru_kelas')->insert([
            'kelas_mapel_id' => $roomId, 'guru_id' => $guruId, 'ditambahkan_oleh' => $guruId,
            'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function roomFor(object $row): int
    {
        $legacyRoom = DB::table('ruang_mapels')->where('mapel_id', $row->mapel_id)
            ->where('guru_id', $row->guru_id)->where('kelas_id', $row->kelas_id)->value('id');
        if ($legacyRoom) {
            return $legacyRoom;
        }
        $code = 'MIG-'.$row->guru_id.'-'.$row->mapel_id.'-'.$row->kelas_id;
        $existing = DB::table('kelas_mapel')->where('kode_kelas', $code)->first();
        if ($existing) {
            return $existing->id;
        }
        $id = DB::table('kelas_mapel')->insertGetId([
            'mapel_id' => $row->mapel_id, 'guru_pembuat_id' => $row->guru_id,
            'nama_kelas_mapel' => DB::table('mapel')->where('id', $row->mapel_id)->value('nama_mapel')
                .' - '.DB::table('kelas')->where('id', $row->kelas_id)->value('nama_kelas'),
            'kode_kelas' => $code, 'invite_token' => Str::random(48), 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->whitelistOwner($id, $row->guru_id);

        return $id;
    }

    public function down(): void
    {
        throw new RuntimeException('Data hasil migrasi dipertahankan. Gunakan backup untuk rollback.');
    }
};
