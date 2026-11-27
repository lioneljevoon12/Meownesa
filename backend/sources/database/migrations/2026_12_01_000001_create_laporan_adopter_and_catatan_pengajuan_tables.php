<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * dev-backend-17.
     *  - laporan_adopter: kabar kondisi kucing yang dikirim adopter sendiri. TIDAK langsung menjadi catatan pemantauan:
     *    admin meninjau lalu mencatatnya (MEOW-BR-13: admin yang mencatat), sehingga data pasca_adopsi tetap
     *    terkendali. Satu laporan yang dicatat menunjuk ke baris pasca_adopsi hasilnya.
     *  - catatan_pengajuan: catatan internal admin per pengajuan (termasuk hasil kunjungan rumah). Tidak pernah
     *    terlihat pelamar.
     */
    public function up(): void
    {
        Schema::create('laporan_adopter', function (Blueprint $table) {
            $table->id('id_laporan');
            $table->foreignId('id_serah_terima')->constrained('serah_terima', 'id_serah_terima')->cascadeOnDelete();
            $table->foreignId('id_user')->nullable()->constrained('users', 'id_user')->nullOnDelete();   // adopter pengirim
            $table->date('tanggal_laporan');                       // tanggal pengiriman menurut WIB
            $table->string('status_kondisi', 20);                  // Sehat, Perawatan, Sakit, Hilang, Meninggal
            $table->text('catatan')->nullable();
            $table->string('status_laporan', 20)->default('MENUNGGU');   // MENUNGGU, DICATAT, DITOLAK
            $table->foreignId('id_pasca_adopsi')->nullable()->constrained('pasca_adopsi', 'id_pasca_adopsi')->nullOnDelete();
            $table->string('catatan_admin', 500)->nullable();      // alasan penolakan / keterangan peninjau
            $table->foreignId('ditinjau_oleh')->nullable()->constrained('users', 'id_user')->nullOnDelete();
            $table->dateTime('ditinjau_pada')->nullable();
            $table->timestamps();

            $table->index(['status_laporan', 'created_at'], 'laporan_adopter_status_index');
            $table->index(['id_serah_terima', 'status_laporan'], 'laporan_adopter_serah_terima_index');
        });

        Schema::create('catatan_pengajuan', function (Blueprint $table) {
            $table->id('id_catatan');
            $table->foreignId('id_pengajuan')->constrained('pengajuan_adopsi', 'id_pengajuan')->cascadeOnDelete();
            $table->foreignId('id_user')->nullable()->constrained('users', 'id_user')->nullOnDelete();   // penulis
            $table->string('jenis', 30)->default('Catatan');       // Catatan, Kunjungan Rumah
            $table->text('isi');
            $table->date('tanggal_kunjungan')->nullable();         // hanya untuk jenis Kunjungan Rumah
            $table->timestamps();

            $table->index(['id_pengajuan', 'created_at'], 'catatan_pengajuan_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_pengajuan');
        Schema::dropIfExists('laporan_adopter');
    }
};
