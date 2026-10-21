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
        Schema::create('riwayat_kesehatan', function (Blueprint $table) {
            $table->id('id_kesehatan');
            $table->foreignId('id_kucing')->constrained('kucing', 'id_kucing')->cascadeOnDelete();
            $table->string('jenis_tindakan', 50); // Sterilisasi, Vaksinasi Tricat, Obat Cacing, Vitamin, dll.
            $table->date('tanggal');
            $table->text('catatan')->nullable(); // detail diagnosa, klinik (Nusantara Vet), dokter hewan
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_kesehatan');
    }
};
