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
        Schema::create('lampiran_pengajuan', function (Blueprint $table) {
            $table->id('id_lampiran');
            $table->foreignId('id_pengajuan')->constrained('pengajuan_adopsi', 'id_pengajuan')->cascadeOnDelete();
            $table->string('jenis_lampiran', 30); // KTP, Foto Rumah, Dokumen Pendukung
            $table->string('url_file', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lampiran_pengajuan');
    }
};
