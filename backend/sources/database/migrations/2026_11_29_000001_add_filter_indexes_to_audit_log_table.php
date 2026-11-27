<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * dev-backend-14: penampil audit log memfilter menurut tabel, pengguna, aktivitas, dan rentang waktu, lalu
     * mengurutkan dari yang terbaru. Tabel ini terus bertambah, jadi tiap filter punya indeks gabungan dengan
     * waktu agar tetap cepat walau jutaan baris.
     */
    public function up(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            $table->index('waktu_aktivitas', 'audit_waktu_index');
            $table->index(['nama_tabel', 'waktu_aktivitas'], 'audit_tabel_waktu_index');
            $table->index(['id_user', 'waktu_aktivitas'], 'audit_user_waktu_index');
            $table->index(['aktivitas', 'waktu_aktivitas'], 'audit_aktivitas_waktu_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            $table->dropIndex('audit_waktu_index');
            $table->dropIndex('audit_tabel_waktu_index');
            $table->dropIndex('audit_user_waktu_index');
            $table->dropIndex('audit_aktivitas_waktu_index');
        });
    }
};
