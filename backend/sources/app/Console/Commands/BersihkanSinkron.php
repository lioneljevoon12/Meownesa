<?php

namespace App\Console\Commands;

use App\Services\SinkronService;
use Illuminate\Console\Command;

class BersihkanSinkron extends Command
{
    protected $signature = 'sync:bersihkan';

    protected $description = 'Hapus catatan penghapusan sinkronisasi yang sudah melewati masa simpan.';

    public function handle(SinkronService $sinkron): int
    {
        $this->info('Catatan penghapusan dibersihkan: '.$sinkron->bersihkan());

        return self::SUCCESS;
    }
}
