<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * dev-backend-15: dukungan offline untuk PWA.
     *  - client_uuid pada riwayat kesehatan dan foto kucing: HP boleh mengirim ulang tanpa membuat data ganda.
     *  - indeks updated_at: penarikan perubahan (delta) selalu memfilter "diubah sejak" lalu mengurutkannya.
     *  - sync_penghapusan: catatan data yang dihapus, agar HP tahu apa yang harus dibuang dari salinan lokalnya.
     */
    public function up(): void
    {
        Schema::table('riwayat_kesehatan', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->unique()->after('id_kesehatan');
            $table->index('updated_at', 'riwayat_kesehatan_updated_at_index');
        });

        Schema::table('foto_kucing', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->unique()->after('id_foto_kucing');
        });

        Schema::table('kucing', fn (Blueprint $table) => $table->index('updated_at', 'kucing_updated_at_index'));
        Schema::table('lokasi', fn (Blueprint $table) => $table->index('updated_at', 'lokasi_updated_at_index'));
        Schema::table('pemberian_makan', fn (Blueprint $table) => $table->index('updated_at', 'pemberian_makan_updated_at_index'));

        Schema::create('sync_penghapusan', function (Blueprint $table) {
            $table->id('id_penghapusan');
            $table->string('entitas', 30);                 // nama tabel: kucing, lokasi, riwayat_kesehatan, pemberian_makan
            $table->unsignedBigInteger('id_data');
            $table->dateTime('dihapus_pada');
            $table->index('dihapus_pada', 'sync_penghapusan_waktu_index');
            $table->index(['entitas', 'id_data'], 'sync_penghapusan_entitas_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_penghapusan');

        Schema::table('pemberian_makan', fn (Blueprint $table) => $table->dropIndex('pemberian_makan_updated_at_index'));
        Schema::table('lokasi', fn (Blueprint $table) => $table->dropIndex('lokasi_updated_at_index'));
        Schema::table('kucing', fn (Blueprint $table) => $table->dropIndex('kucing_updated_at_index'));

        Schema::table('foto_kucing', function (Blueprint $table) {
            $table->dropUnique(['client_uuid']);
            $table->dropColumn('client_uuid');
        });

        Schema::table('riwayat_kesehatan', function (Blueprint $table) {
            $table->dropIndex('riwayat_kesehatan_updated_at_index');
            $table->dropUnique(['client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
