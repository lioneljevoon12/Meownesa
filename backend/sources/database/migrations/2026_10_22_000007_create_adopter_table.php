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
        Schema::create('adopter', function (Blueprint $table) {
            $table->id('id_adopter');
            $table->foreignId('id_user')->unique()->constrained('users', 'id_user')->cascadeOnDelete();
            $table->date('tanggal_menjadi_adopter');
            $table->string('status_adopter', 20)->default('aktif'); // aktif, nonaktif
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adopter');
    }
};
