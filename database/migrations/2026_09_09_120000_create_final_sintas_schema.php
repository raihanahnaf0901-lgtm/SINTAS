<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->preflight();
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable()->unique();
            $table->enum('status', ['pending', 'aktif', 'nonaktif'])->default('aktif');
            $table->string('role', 20)->default('siswa')->change();
        });
        DB::table('users')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update(['username' => 'user_'.$user->id]);
        });
        Schema::table('users', fn (Blueprint $table) => $table->string('username')->nullable(false)->change());
        Schema::table('kelas', function (Blueprint $table): void {
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->string('tingkat', 10)->nullable()->change();
        });

        Schema::create('siswa', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->string('nis', 20)->nullable()->unique();
            $table->string('nisn', 20)->nullable()->unique();
            $table->string('nama_lengkap');
            $table->timestamps();
        });
        Schema::create('guru', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $table->string('nip', 30)->nullable()->unique();
            $table->string('nama_lengkap');
            $table->string('gelar', 50)->nullable();
            $table->enum('jenis_guru', ['guru_mapel', 'guru_piket'])->default('guru_mapel');
            $table->timestamps();
        });
        Schema::create('mapel', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_mapel');
            $table->timestamps();
        });
        Schema::create('otp_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code_hash');
            $table->enum('purpose', ['register', 'login', 'reset_password']);
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'purpose', 'used_at']);
        });
        Schema::create('kelas_mapel', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mapel_id')->constrained('mapel')->restrictOnDelete();
            $table->foreignId('guru_pembuat_id')->constrained('guru')->restrictOnDelete();
            $table->string('nama_kelas_mapel');
            $table->string('kode_kelas', 32)->unique();
            $table->string('invite_token', 64)->unique();
            $table->text('deskripsi')->nullable();
            $table->enum('status', ['aktif', 'arsip'])->default('aktif');
            $table->timestamps();
        });
        Schema::create('whitelist_guru_kelas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kelas_mapel_id')->constrained('kelas_mapel')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->restrictOnDelete();
            $table->foreignId('ditambahkan_oleh')->constrained('guru')->restrictOnDelete();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();
            $table->unique(['kelas_mapel_id', 'guru_id']);
        });
        Schema::create('anggota_kelas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kelas_mapel_id')->constrained('kelas_mapel')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->enum('status', ['pending', 'diterima', 'ditolak'])->default('pending');
            $table->enum('join_method', ['kode', 'link']);
            $table->dateTime('requested_at');
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('guru')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['kelas_mapel_id', 'siswa_id']);
            $table->index(['kelas_mapel_id', 'status']);
        });
        Schema::create('jadwal', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kelas_mapel_id')->constrained('kelas_mapel')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->restrictOnDelete();
            $table->enum('hari', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu']);
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('ruangan', 50)->nullable();
            $table->timestamps();
        });
        Schema::rename('tugas', 'legacy_tugas');
        Schema::create('tugas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kelas_mapel_id')->constrained('kelas_mapel')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru', indexName: 'final_tugas_guru_foreign')->restrictOnDelete();
            $table->string('judul');
            $table->enum('jenis', ['tugas', 'pr', 'proyek'])->default('tugas');
            $table->text('deskripsi')->nullable();
            $table->dateTime('deadline');
            $table->timestamps();
        });
        Schema::create('ujian', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kelas_mapel_id')->constrained('kelas_mapel')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->restrictOnDelete();
            $table->enum('jenis_ujian', ['UH', 'US']);
            $table->string('judul');
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
        Schema::create('pengumpulan_tugas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tugas_id')->constrained('tugas')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->enum('status', ['belum', 'dikumpulkan', 'terlambat'])->default('belum');
            $table->string('file_path')->nullable();
            $table->text('catatan_siswa')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['tugas_id', 'siswa_id']);
        });
        Schema::create('penilaian', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('tugas_id')->nullable()->constrained('tugas')->cascadeOnDelete();
            $table->foreignId('ujian_id')->nullable()->constrained('ujian')->cascadeOnDelete();
            $table->decimal('nilai', 5, 2);
            $table->foreignId('dinilai_oleh')->constrained('guru')->restrictOnDelete();
            $table->text('catatan')->nullable();
            $table->dateTime('dinilai_at');
            $table->timestamps();
            $table->unique(['siswa_id', 'tugas_id']);
            $table->unique(['siswa_id', 'ujian_id']);
        });
        Schema::create('konfigurasi_rekap', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kelas_mapel_id')->constrained('kelas_mapel')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->restrictOnDelete();
            $table->string('nama_konfigurasi');
            $table->enum('status', ['draft', 'aktif'])->default('draft');
            $table->timestamps();
        });
        Schema::create('komponen_rekap', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('konfigurasi_rekap_id')->constrained('konfigurasi_rekap')->cascadeOnDelete();
            $table->enum('jenis', ['tugas', 'UH', 'US']);
            $table->decimal('bobot', 5, 2);
            $table->enum('metode', ['rata_rata', 'manual'])->default('rata_rata');
            $table->timestamps();
            $table->unique(['konfigurasi_rekap_id', 'jenis']);
        });
        Schema::rename('notifikasi', 'legacy_notifikasi');
        Schema::create('notifikasi', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('judul');
            $table->text('pesan');
            $table->string('tipe');
            $table->json('data')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'read_at']);
        });

        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $operation) {
                $name = 'penilaian_target_'.strtolower($operation);
                DB::unprepared('CREATE TRIGGER '.$name.' BEFORE '.$operation.' ON penilaian '
                    .'WHEN ((NEW.tugas_id IS NULL) = (NEW.ujian_id IS NULL)) OR NEW.nilai < 0 OR NEW.nilai > 100 '
                    ."BEGIN SELECT RAISE(ABORT, 'Penilaian wajib tepat satu target dan nilai 0-100'); END");
            }
        } else {
            DB::statement('ALTER TABLE penilaian ADD CONSTRAINT penilaian_target_check CHECK ((tugas_id IS NULL) <> (ujian_id IS NULL))');
            DB::statement('ALTER TABLE penilaian ADD CONSTRAINT penilaian_nilai_check CHECK (nilai >= 0 AND nilai <= 100)');
        }
    }

    private function preflight(): void
    {
        foreach (['siswas', 'gurus'] as $table) {
            if (DB::table($table)->whereNotNull('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Relasi user ganda di '.$table.'. Perbaiki sebelum migrasi; skema belum diubah.');
            }
        }
        if (DB::table('penilaians')->whereNull('tugas_id')->exists()) {
            throw new RuntimeException('Penilaian lama tanpa tugas perlu dipetakan ke ujian terlebih dahulu. Skema belum diubah.');
        }
        if (DB::table('penilaians')->groupBy('siswa_id', 'tugas_id')->havingRaw('COUNT(*) > 1')->exists()
            || DB::table('penilaians')->where('nilai', '<', 0)->orWhere('nilai', '>', 100)->exists()) {
            throw new RuntimeException('Perbaiki nilai ganda atau nilai di luar 0-100 sebelum migrasi. Skema belum diubah.');
        }
        if (DB::table('jadwals')->where('hari', 'Minggu')->exists()) {
            throw new RuntimeException('Skema final hanya mendukung Senin-Sabtu. Sesuaikan jadwal Minggu sebelum migrasi.');
        }
        DB::table('notifikasi')->orderBy('id')->each(function (object $row): void {
            $table = $row->tipe_user === 'Siswa' ? 'siswas' : 'gurus';
            if (! DB::table($table)->where('id', $row->user_id)->whereNotNull('user_id')->exists()) {
                throw new RuntimeException('Penerima notifikasi lama belum memiliki akun users. Hubungkan profil sebelum migrasi.');
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Migrasi skema final tidak dapat di-rollback otomatis. Pulihkan backup database agar data akademik baru tidak hilang.');
    }
};
