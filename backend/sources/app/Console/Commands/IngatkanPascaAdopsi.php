<?php

namespace App\Console\Commands;

use App\Services\PengingatPascaAdopsiService;
use Illuminate\Console\Command;

class IngatkanPascaAdopsi extends Command
{
    protected $signature = 'notifikasi:ingatkan';

    protected $description = 'Buat pengingat (WhatsApp + Email) untuk adopter yang jadwal kabar kondisi kucingnya sudah jatuh tempo.';

    public function handle(PengingatPascaAdopsiService $pengingat): int
    {
        $h = $pengingat->jalankan();

        $this->info("Ditinjau {$h['adopsi_ditinjau']} adopsi: pengingat baru {$h['pengingat_dibuat']}, sudah ada {$h['sudah_ada']}, dihentikan {$h['dihentikan']}.");

        return self::SUCCESS;
    }
}
