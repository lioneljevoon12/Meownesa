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
        Schema::create('pasca_adopsi', function (Blueprint $table) {
            $table->id('id_pasca_adopsi');
            $table->foreignId('id_serah_terima')->constrained('serah_terima', 'id_serah_terima')->cascadeOnDelete();
            $table->date('tanggal_update');
            $table->string('status_kondisi', 30); // Sehat, Perawatan, Sakit
            $table->text('catatan')->nullable(); // detail perkembangan dari adopter
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pasca_adopsi');
    }
};
