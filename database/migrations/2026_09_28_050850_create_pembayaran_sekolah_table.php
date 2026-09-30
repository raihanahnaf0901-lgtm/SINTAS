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
        Schema::create('pembayaran_sekolah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->string('order_id', 50)->unique();
            $table->string('plan_code', 64);
            $table->string('plan_name');
            $table->unsignedBigInteger('amount');
            $table->unsignedInteger('duration_days');
            $table->string('status', 20)->default('creating');
            $table->text('checkout_url')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('gateway_status', 30)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->timestamps();
            $table->index(['sekolah_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran_sekolah');
    }
};
