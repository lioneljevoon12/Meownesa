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
        Schema::create('lokasi', function (Blueprint $table) {
            $table->id('id_lokasi');
            $table->foreignId('id_kampus')->constrained('kampus', 'id_kampus')->cascadeOnDelete();
            $table->string('nama_lokasi', 100);
            $table->decimal('koordinat_x', 11, 8)->default(0.00); // Koordinat X / Latitude peta
            $table->decimal('koordinat_y', 11, 8)->default(0.00); // Koordinat Y / Longitude peta
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lokasi');
    }
};
