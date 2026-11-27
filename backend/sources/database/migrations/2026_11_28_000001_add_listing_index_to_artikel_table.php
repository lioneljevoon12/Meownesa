<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** dev-backend-12: daftar publik selalu memfilter status terbit lalu mengurutkan sematan + tanggal terbit. */
    public function up(): void
    {
        Schema::table('artikel', function (Blueprint $table) {
            $table->index(['status_publikasi', 'is_pinned', 'tanggal_diterbitkan'], 'artikel_listing_index');
            $table->index('kategori', 'artikel_kategori_index');
        });
    }

    public function down(): void
    {
        Schema::table('artikel', function (Blueprint $table) {
            $table->dropIndex('artikel_listing_index');
            $table->dropIndex('artikel_kategori_index');
        });
    }
};
