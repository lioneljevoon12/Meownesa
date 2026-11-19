<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Router;
use App\Core\View;

/** Katalog & profil kucing untuk publik (MEOW-F-01, 02, 06). Hanya data publik yang diekspos. */
final class KucingController
{
    /** Status yang boleh tampil di halaman publik (MEOW-NF-05). */
    public const PUBLIC_STATUS = ['Tersedia', 'Terdata'];

    public const BASE_SELECT = <<<SQL
        SELECT k.id_kucing, k.kode_kucing, k.nama_kucing, k.jenis_kelamin, k.kelompok_usia,
               k.status_steril, k.status_kucing, k.deskripsi,
               l.nama_lokasi, ka.id_kampus, ka.nama_kampus,
               (SELECT f.url_foto FROM foto_kucing f WHERE f.id_kucing = k.id_kucing
                 ORDER BY f.is_utama DESC, f.id_foto_kucing LIMIT 1) AS foto
          FROM kucing k
          JOIN lokasi l  ON l.id_lokasi = k.id_lokasi
          JOIN kampus ka ON ka.id_kampus = l.id_kampus
    SQL;

    public function index(): void
    {
        $kampus = Db::all('SELECT id_kampus, nama_kampus FROM kampus ORDER BY id_kampus');
        $cats = $this->search($_GET);
        View::render('kucing/index', compact('kampus', 'cats') + ['title' => 'Katalog Kucing', 'filters' => $_GET]);
    }

    /** Endpoint Ajax: mengembalikan potongan HTML kartu sesuai filter. */
    public function data(): void
    {
        $cats = $this->search($_GET);
        header('Content-Type: text/html; charset=utf-8');
        View::partial('kucing/_cards', ['cats' => $cats]);
    }

    public function show(string $id): void
    {
        if (!ctype_digit($id)) {
            (new Router())->abort(404, 'Profil kucing tidak ditemukan.');
        }
        $in =implode(',', array_fill(0, count(self::PUBLIC_STATUS), '?'));
        $cat = Db::one(self::BASE_SELECT . " WHERE k.id_kucing = ? AND k.status_kucing IN ($in)", [(int) $id, ...self::PUBLIC_STATUS]);
        if ($cat === null) {
            (new Router())->abort(404, 'Profil kucing tidak ditemukan.');
        }

        $fotos = Db::all('SELECT url_foto, is_utama FROM foto_kucing WHERE id_kucing = ? ORDER BY is_utama DESC, id_foto_kucing', [$cat['id_kucing']]);
        $riwayat = Db::all('SELECT jenis_tindakan, tanggal, catatan FROM riwayat_kesehatan WHERE id_kucing = ? ORDER BY tanggal DESC', [$cat['id_kucing']]);

        View::render('kucing/show', compact('cat', 'fotos', 'riwayat') + ['title' => $cat['nama_kucing']]);
    }

    /** @return array<int, array<string, mixed>> */
    private function search(array $f): array
    {
        $where = ['k.status_kucing IN (' . implode(',', array_fill(0, count(self::PUBLIC_STATUS), '?')) . ')'];
        $params = self::PUBLIC_STATUS;

        if (!empty($f['kampus']) && ctype_digit((string) $f['kampus'])) {
            $where[] = 'ka.id_kampus = ?';
            $params[] = (int) $f['kampus'];
        }
        if (!empty($f['gender']) && in_array($f['gender'], ['Jantan', 'Betina'], true)) {
            $where[] = 'k.jenis_kelamin = ?';
            $params[] = $f['gender'];
        }
        if (!empty($f['status']) && in_array($f['status'], self::PUBLIC_STATUS, true)) {
            $where[] = 'k.status_kucing = ?';
            $params[] = $f['status'];
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(k.nama_kucing LIKE ? OR k.kode_kucing LIKE ? OR k.deskripsi LIKE ? OR l.nama_lokasi LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        return Db::all(self::BASE_SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY k.nama_kucing', $params);
    }
}
