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
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id('id_notifikasi');
            $table->foreignId('id_pengajuan')->nullable()->constrained('pengajuan_adopsi', 'id_pengajuan')->nullOnDelete();
            $table->foreignId('id_user')->nullable()->constrained('users', 'id_user')->nullOnDelete();
            $table->string('jenis_notifikasi', 50); // Konfirmasi Pengajuan, Status Adopsi, Reminder Monitoring
            $table->string('kanal', 20); // WhatsApp, Email
            $table->text('isi_pesan');
            $table->string('status_pengiriman', 20)->default('PENDING'); // PENDING, TERKIRIM, GAGAL
            $table->date('tanggal_dikirim')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
    }
};
