<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table): void {
            $table->string('kelas_siswa')->nullable();
        });

        DB::table('kelas')->select('id', 'nama_kelas')->orderBy('id')->each(function (object $kelas): void {
            DB::table('siswa')->where('kelas_id', $kelas->id)->update(['kelas_siswa' => $kelas->nama_kelas]);
        }, 200);
    }

    /**
     * Reverse the migrations. Student-entered class names are lost on rollback.
     */
    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table): void {
            $table->dropColumn('kelas_siswa');
        });
    }
};
