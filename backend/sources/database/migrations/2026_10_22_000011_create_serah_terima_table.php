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
        Schema::create('serah_terima', function (Blueprint $table) {
            $table->id('id_serah_terima');
            $table->foreignId('id_pengajuan')->unique()->constrained('pengajuan_adopsi', 'id_pengajuan')->cascadeOnDelete();
            $table->date('tanggal_serah_terima');
            $table->string('status_serah_terima', 30)->default('Selesai');
            $table->string('dokumen_perjanjian', 255)->nullable(); // URL / path berkas BAST PDF
            $table->string('foto_serah_terima', 255)->nullable(); // URL / path foto penyerahan kucing
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serah_terima');
    }
};
