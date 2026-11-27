<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * dev-backend-11: kolom pendukung pengiriman.
     *  - kunci          : penanda idempoten (mis. pengingat per adopsi per minggu) agar tidak terkirim ganda.
     *  - percobaan      : berapa kali pengiriman email dicoba.
     *  - catatan_galat  : alasan singkat bila GAGAL (atau catatan admin untuk WhatsApp manual).
     */
    public function up(): void
    {
        Schema::table('notifikasi', function (Blueprint $table) {
            $table->string('kunci', 120)->nullable()->after('kanal');
            $table->unsignedTinyInteger('percobaan')->default(0)->after('status_pengiriman');
            $table->string('catatan_galat', 255)->nullable()->after('percobaan');

            // NULL tidak dianggap sama, jadi notifikasi tanpa kunci tidak terpengaruh.
            $table->unique(['kunci', 'kanal'], 'notifikasi_kunci_kanal_unique');
            $table->index(['status_pengiriman', 'kanal'], 'notifikasi_status_kanal_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifikasi', function (Blueprint $table) {
            $table->dropUnique('notifikasi_kunci_kanal_unique');
            $table->dropIndex('notifikasi_status_kanal_index');
            $table->dropColumn(['kunci', 'percobaan', 'catatan_galat']);
        });
    }
};
