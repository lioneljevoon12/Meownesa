<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Db;
use App\Core\View;

/** Dashboard statistik admin (MEOW-F-40). Agregasi dihitung langsung dari tabel sumber. */
final class DashboardController
{
    public function index(): void
    {
        $total = (int) Db::value("SELECT COUNT(*) FROM kucing WHERE status_kucing <> 'Almarhum'");
        $steril = (int) Db::value("SELECT COUNT(*) FROM kucing WHERE status_kucing <> 'Almarhum' AND status_steril = 'Sudah'");

        // Adopter dianggap "overdue" bila update terakhirnya > 7 hari lalu (atau belum pernah ada update).
        $overdue = (int) Db::value(
            "SELECT COUNT(*) FROM serah_terima s
              WHERE COALESCE((SELECT MAX(p.tanggal_update) FROM pasca_adopsi p WHERE p.id_serah_terima = s.id_serah_terima), s.tanggal_serah_terima)
                    < CURDATE() - INTERVAL 7 DAY"
        );

        $stats = [
            'total'    => $total,
            'tersedia' => (int) Db::value("SELECT COUNT(*) FROM kucing WHERE status_kucing = 'Tersedia'"),
            'antrean'  => (int) Db::value("SELECT COUNT(*) FROM pengajuan_adopsi WHERE status_pengajuan IN ('MENUNGGU','SCREENING')"),
            'feeding'  => (int) Db::value('SELECT COUNT(*) FROM pemberian_makan WHERE tanggal = CURDATE()'),
            'steril'   => $total > 0 ? (int) round($steril / $total * 100) : 0,
            'overdue'  => $overdue,
        ];

        $antrean = Db::all(
            "SELECT p.id_pengajuan, p.nomor_pengajuan, p.nama_pemohon, p.status_pengajuan, p.tanggal_pengajuan, k.nama_kucing
               FROM pengajuan_adopsi p JOIN kucing k ON k.id_kucing = p.id_kucing
              WHERE p.status_pengajuan IN ('MENUNGGU','SCREENING')
              ORDER BY p.tanggal_pengajuan LIMIT 5"
        );

        $perKampus = Db::all(
            "SELECT ka.nama_kampus, COUNT(k.id_kucing) AS jumlah
               FROM kampus ka
               LEFT JOIN lokasi l ON l.id_kampus = ka.id_kampus
               LEFT JOIN kucing k ON k.id_lokasi = l.id_lokasi AND k.status_kucing <> 'Almarhum'
              GROUP BY ka.id_kampus, ka.nama_kampus ORDER BY ka.id_kampus"
        );

        View::render('admin/dashboard', compact('stats', 'antrean', 'perKampus') + ['title' => 'Dashboard', 'active' => 'dashboard'], 'layouts/admin');
    }
}
