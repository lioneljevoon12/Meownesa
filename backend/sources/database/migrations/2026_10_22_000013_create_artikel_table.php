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
        Schema::create('artikel', function (Blueprint $table) {
            $table->id('id_artikel');
            $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->string('judul', 200);
            $table->string('slug', 220)->unique();
            $table->string('kategori', 50); // Edukasi, Kampanye Kalung, Cerita Kucing, Tips
            $table->longText('isi');
            $table->string('thumbnail_url', 255)->nullable();
            $table->string('status_publikasi', 20)->default('DRAFT'); // DRAFT, PUBLISHED, ARCHIVED
            $table->boolean('is_featured')->default(false); // Penanda artikel unggulan di landing page
            $table->boolean('is_pinned')->default(false); // Penanda artikel disematkan
            $table->date('tanggal_dibuat');
            $table->date('tanggal_diterbitkan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artikel');
    }
};
