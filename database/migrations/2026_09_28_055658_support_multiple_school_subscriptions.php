<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sekolah', function (Blueprint $table): void {
            $table->index('admin_guru_id', 'sekolah_admin_guru_index');
        });
        Schema::table('sekolah', function (Blueprint $table): void {
            $table->dropUnique('sekolah_admin_guru_id_unique');
        });
        Schema::table('keanggotaan_sekolah', function (Blueprint $table): void {
            $table->unique(['guru_id', 'sekolah_id'], 'keanggotaan_guru_sekolah_unique');
        });
        Schema::table('keanggotaan_sekolah', function (Blueprint $table): void {
            $table->dropUnique('keanggotaan_sekolah_guru_id_unique');
            $table->string('jenis_guru', 20)->nullable();
        });
        DB::table('keanggotaan_sekolah')->orderBy('id')->chunkById(200, function ($members): void {
            $types = DB::table('guru')->whereIn('id', $members->pluck('guru_id'))->pluck('jenis_guru', 'id');
            foreach ($members as $member) {
                DB::table('keanggotaan_sekolah')->where('id', $member->id)
                    ->update(['jenis_guru' => $types[$member->guru_id] ?? 'guru_mapel']);
            }
        });
        Schema::table('pembayaran_sekolah', function (Blueprint $table): void {
            $table->unsignedInteger('duration_days')->nullable()->change();
            $table->unsignedInteger('duration_months')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('sekolah')->groupBy('admin_guru_id')->havingRaw('COUNT(*) > 1')->exists()
            || DB::table('keanggotaan_sekolah')->groupBy('guru_id')->havingRaw('COUNT(*) > 1')->exists()
            || DB::table('pembayaran_sekolah')->whereNull('duration_days')->exists()
            || DB::table('keanggotaan_sekolah')->join('guru', 'guru.id', '=', 'keanggotaan_sekolah.guru_id')
                ->whereNotNull('keanggotaan_sekolah.jenis_guru')
                ->whereColumn('keanggotaan_sekolah.jenis_guru', '!=', 'guru.jenis_guru')->exists()) {
            throw new RuntimeException('Rollback tidak aman: terdapat multi-sekolah, tagihan bulanan, atau perubahan jenis guru sekolah. Pertahankan migrasi ini.');
        }

        Schema::table('pembayaran_sekolah', function (Blueprint $table): void {
            $table->dropColumn('duration_months');
            $table->unsignedInteger('duration_days')->nullable(false)->change();
        });
        Schema::table('keanggotaan_sekolah', function (Blueprint $table): void {
            $table->unique('guru_id');
        });
        Schema::table('keanggotaan_sekolah', function (Blueprint $table): void {
            $table->dropUnique('keanggotaan_guru_sekolah_unique');
            $table->dropColumn('jenis_guru');
        });
        Schema::table('sekolah', function (Blueprint $table): void {
            $table->unique('admin_guru_id');
        });
        Schema::table('sekolah', function (Blueprint $table): void {
            $table->dropIndex('sekolah_admin_guru_index');
        });
    }
};
