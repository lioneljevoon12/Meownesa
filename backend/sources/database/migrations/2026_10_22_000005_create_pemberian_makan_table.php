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
        Schema::create('pemberian_makan', function (Blueprint $table) {
            $table->id('id_pemberian');
            $table->foreignId('id_kucing')->nullable()->constrained('kucing', 'id_kucing')->nullOnDelete();
            $table->foreignId('id_lokasi')->constrained('lokasi', 'id_lokasi')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('status_pemberian', 20)->default('Tuntas'); // Tuntas, Terjadwal
            $table->text('catatan')->nullable(); // takaran gram, shift pagi/sore, merk pakan
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pemberian_makan');
    }
};
