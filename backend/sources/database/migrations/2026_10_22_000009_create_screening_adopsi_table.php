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
        Schema::create('screening_adopsi', function (Blueprint $table) {
            $table->id('id_screening');
            $table->foreignId('id_pengajuan')->unique()->constrained('pengajuan_adopsi', 'id_pengajuan')->cascadeOnDelete();
            $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->text('kelayakan_hunian')->nullable();
            $table->text('kestabilan_ekonomi')->nullable();
            $table->text('pemahaman_perawatan')->nullable();
            $table->text('sikap_proklinik')->nullable();
            $table->string('hasil_screening', 30); // Lolos, Ragu, Tidak Lolos
            $table->date('tanggal_screening');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screening_adopsi');
    }
};
