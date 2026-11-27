<?php

namespace App\Console\Commands;

use App\Services\PengirimanNotifikasiService;
use Illuminate\Console\Command;

class KirimNotifikasi extends Command
{
    protected $signature = 'notifikasi:kirim';

    protected $description = 'Kirim email notifikasi yang masih PENDING (WhatsApp dikirim manual oleh admin lewat tautan wa.me).';

    public function handle(PengirimanNotifikasiService $pengiriman): int
    {
        $hasil = $pengiriman->kirimTertunda();

        $this->info("Diproses {$hasil['diproses']}: terkirim {$hasil['terkirim']}, gagal {$hasil['gagal']}.");

        return self::SUCCESS;
    }
}
