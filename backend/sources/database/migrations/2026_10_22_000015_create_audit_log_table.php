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
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id('id_audit');
            $table->foreignId('id_user')->nullable()->constrained('users', 'id_user')->nullOnDelete();
            $table->string('aktivitas', 100); // CREATE, UPDATE, DELETE, LOGIN, LOGOUT
            $table->string('nama_tabel', 50)->nullable();
            $table->unsignedBigInteger('id_data')->nullable();
            $table->dateTime('waktu_aktivitas');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
