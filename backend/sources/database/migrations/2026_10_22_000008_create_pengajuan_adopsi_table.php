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
        Schema::create('pengajuan_adopsi', function (Blueprint $table) {
            $table->id('id_pengajuan');
            $table->foreignId('id_kucing')->constrained('kucing', 'id_kucing')->cascadeOnDelete();
            $table->foreignId('id_adopter')->nullable()->constrained('adopter', 'id_adopter')->nullOnDelete();
            $table->string('nomor_pengajuan', 30)->unique();
            $table->string('kode_tracking', 50)->unique();
            $table->string('nama_pemohon', 100);
            $table->string('nomor_whatsapp', 20);
            $table->string('email', 150);
            $table->text('alamat');
            $table->string('status_pengajuan', 30)->default('MENUNGGU'); // MENUNGGU, SCREENING, DISETUJUI, DITOLAK
            $table->date('tanggal_pengajuan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_adopsi');
    }
};
