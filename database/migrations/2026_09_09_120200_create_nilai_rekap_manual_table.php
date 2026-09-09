<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nilai_rekap_manual', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('komponen_rekap_id')->constrained('komponen_rekap')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->decimal('nilai', 5, 2);
            $table->foreignId('dinilai_oleh')->constrained('guru')->restrictOnDelete();
            $table->text('catatan')->nullable();
            $table->dateTime('dinilai_at');
            $table->timestamps();
            $table->unique(['komponen_rekap_id', 'siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_rekap_manual');
    }
};
