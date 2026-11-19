<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\View;

final class HomeController
{
    public function index(): void
    {
        $publik = KucingController::PUBLIC_STATUS;
        $in = implode(',', array_fill(0, count($publik), '?'));

        $stats = [
            'total'   => (int) Db::value("SELECT COUNT(*) FROM kucing WHERE status_kucing IN ($in)", $publik),
            'tersedia' => (int) Db::value("SELECT COUNT(*) FROM kucing WHERE status_kucing = 'Tersedia'"),
            'steril'  => (int) Db::value("SELECT COUNT(*) FROM kucing WHERE status_steril = 'Sudah' AND status_kucing IN ($in)", $publik),
            'kampus'  => (int) Db::value('SELECT COUNT(*) FROM kampus'),
        ];

        $kucing = Db::all(KucingController::BASE_SELECT . " WHERE k.status_kucing = 'Tersedia' ORDER BY k.id_kucing LIMIT 3");

        $artikel = Db::all(
            "SELECT judul, slug, kategori, thumbnail_url, tanggal_diterbitkan, is_featured
               FROM artikel WHERE status_publikasi = 'PUBLISHED'
              ORDER BY is_pinned DESC, tanggal_diterbitkan DESC LIMIT 3"
        );

        View::render('home', compact('stats', 'kucing', 'artikel') + ['title' => 'Beranda']);
    }
}
