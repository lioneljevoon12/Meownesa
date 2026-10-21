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
        Schema::create('kucing', function (Blueprint $table) {
            $table->id('id_kucing');
            $table->foreignId('id_lokasi')->constrained('lokasi', 'id_lokasi')->cascadeOnDelete();
            $table->string('kode_kucing', 30)->unique();
            $table->string('nama_kucing', 100);
            $table->string('jenis_kelamin', 20); // Jantan, Betina
            $table->string('kelompok_usia', 30)->nullable(); // Kitten, Remaja, Dewasa
            $table->string('status_steril', 20)->default('Belum'); // Sudah, Belum
            $table->string('status_kucing', 30)->default('Tersedia'); // Tersedia, Proses Screening, Diadopsi, Almarhum
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kucing');
    }
};
