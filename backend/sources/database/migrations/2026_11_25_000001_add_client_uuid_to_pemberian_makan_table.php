<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kunci idempotensi untuk pencatatan dari HP (offline-first): aplikasi membuat UUID untuk setiap
     * catatan, sehingga pengiriman ulang saat sinyal putus-nyambung tidak menggandakan data.
     * Kolom tambahan ini nullable: catatan lama dan catatan dari web tidak membutuhkannya.
     */
    public function up(): void
    {
        Schema::table('pemberian_makan', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->unique()->after('id_pemberian');
        });
    }

    public function down(): void
    {
        Schema::table('pemberian_makan', function (Blueprint $table) {
            $table->dropUnique(['client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
