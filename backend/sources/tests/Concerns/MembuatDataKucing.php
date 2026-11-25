<?php

namespace Tests\Concerns;

use App\Models\FotoKucing;
use App\Models\Kampus;
use App\Models\Kucing;
use App\Models\Lokasi;

/** Pembuat data kucing/lokasi/kampus untuk tes modul 04. */
trait MembuatDataKucing
{
    protected Kampus $ktt;
    protected Kampus $lwt;
    protected Lokasi $danau;
    protected Lokasi $fip;

    protected function siapkanWilayah(): void
    {
        $this->ktt = Kampus::create(['nama_kampus' => 'Kampus Ketintang']);
        $this->lwt = Kampus::create(['nama_kampus' => 'Kampus Lidah Wetan']);
        $this->danau = Lokasi::create(['id_kampus' => $this->ktt->id_kampus, 'nama_lokasi' => 'Danau', 'koordinat_x' => -7.31, 'koordinat_y' => 112.72]);
        $this->fip = Lokasi::create(['id_kampus' => $this->lwt->id_kampus, 'nama_lokasi' => 'FIP', 'koordinat_x' => -7.30, 'koordinat_y' => 112.67]);
    }

    /** @param  array<string, mixed>  $o */
    protected function buatKucing(array $o = []): Kucing
    {
        static $n = 0;
        $n++;

        return Kucing::create($o + [
            'id_lokasi'     => $this->danau->id_lokasi,
            'kode_kucing'   => sprintf('TST-%03d', $n),
            'nama_kucing'   => 'Kucing '.$n,
            'jenis_kelamin' => 'Betina',
            'kelompok_usia' => 'Dewasa',
            'status_steril' => 'Sudah',
            'status_kucing' => 'Terdata',
            'deskripsi'     => 'Deskripsi uji '.$n,
        ]);
    }

    protected function buatFoto(Kucing $k, bool $utama = false, ?string $url = null): FotoKucing
    {
        static $m = 0;
        $m++;

        return FotoKucing::create([
            'id_kucing' => $k->id_kucing,
            'url_foto'  => $url ?? "images/kucing/upload/uji-{$m}.jpg",
            'is_utama'  => $utama,
        ]);
    }
}
