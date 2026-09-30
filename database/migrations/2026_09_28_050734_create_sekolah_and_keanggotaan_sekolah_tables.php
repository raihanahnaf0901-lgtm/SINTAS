<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sekolah', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_sekolah');
            $table->string('npsn', 8)->unique();
            $table->text('alamat')->nullable();
            $table->foreignId('admin_guru_id')->unique()->constrained('guru')->restrictOnDelete();
            $table->string('kode_sekolah', 16)->unique();
            $table->string('status', 20)->default('pending');
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('keanggotaan_sekolah', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->foreignId('guru_id')->unique()->constrained('guru')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('guru')->nullOnDelete();
            $table->timestamps();
            $table->index(['sekolah_id', 'status']);
        });

        Schema::table('kelas_mapel', function (Blueprint $table): void {
            $table->foreignId('sekolah_id')->nullable()->constrained('sekolah')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kelas_mapel', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sekolah_id');
        });
        Schema::dropIfExists('keanggotaan_sekolah');
        Schema::dropIfExists('sekolah');
    }
};
