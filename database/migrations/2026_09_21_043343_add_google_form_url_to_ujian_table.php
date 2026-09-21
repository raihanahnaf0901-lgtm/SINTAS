<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ujian', function (Blueprint $table): void {
            $table->string('google_form_url', 2048)->nullable();
        });
    }

    /**
     * Reverse the migrations. Saved Google Form links are lost on rollback.
     */
    public function down(): void
    {
        Schema::table('ujian', function (Blueprint $table): void {
            $table->dropColumn('google_form_url');
        });
    }
};
