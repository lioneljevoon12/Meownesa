-- ============================================================================
-- MeowNesa — Data awal (master + contoh). Dijalankan SETELAH schema.sql dan
-- setelah tabel users terisi (id 1 = superadmin, 2 = admin, 3 = relawan),
-- lihat uas/bin/install.php. Isinya setara dengan seeder Laravel di backend/.
-- ============================================================================

SET NAMES utf8mb4;
SET @now = NOW();

INSERT INTO kampus (id_kampus, nama_kampus, created_at, updated_at) VALUES
 (1, 'Kampus Ketintang',   @now, @now),
 (2, 'Kampus Lidah Wetan', @now, @now);

INSERT INTO lokasi (id_lokasi, id_kampus, nama_lokasi, koordinat_x, koordinat_y, created_at, updated_at) VALUES
 (1, 1, 'Gedung K10 & K4 — Fakultas Vokasi',    -7.31290000, 112.72750000, @now, @now),
 (2, 1, 'Gedung C — FMIPA',                      -7.31150000, 112.72800000, @now, @now),
 (3, 1, 'Gedung G — FEB',                        -7.31200000, 112.72900000, @now, @now),
 (4, 1, 'Kantin T2 & Gazebo Rektorat',           -7.31350000, 112.72700000, @now, @now),
 (5, 2, 'Kantin T2 & Selasar Lapangan',          -7.30150000, 112.67400000, @now, @now),
 (6, 2, 'Gedung T1 — FBS',                       -7.30250000, 112.67550000, @now, @now),
 (7, 2, 'Gedung Rektorat Liwet & Danau',         -7.30100000, 112.67350000, @now, @now);

INSERT INTO kucing (id_kucing, id_lokasi, kode_kucing, nama_kucing, jenis_kelamin, kelompok_usia, status_steril, status_kucing, deskripsi, created_at, updated_at) VALUES
 (1, 1, 'KTT-01', 'Mochi',       'Betina', 'Dewasa', 'Sudah', 'Tersedia',         'Kucing belang tiga (calico) paling ramah di selasar Fakultas Vokasi. Rutin feeding harian dan sudah tuntas sterilisasi di Klinik Nusantara Vet.', @now, @now),
 (2, 5, 'LWT-01', 'Milo',        'Jantan', 'Dewasa', 'Sudah', 'Tersedia',         'Kucing abu-abu corak tabby dengan kalung biru reflektif. Suka santai di area kantin dan sangat bersahabat dengan mahasiswa.', @now, @now),
 (3, 4, 'KTT-02', 'Oyen Barbar', 'Jantan', 'Dewasa', 'Sudah', 'Tersedia',         'Kucing oranye enerjik yang hobi menyapa pengunjung di selasar gedung rektorat. Sehat dan sangat aktif.', @now, @now),
 (4, 6, 'LWT-02', 'Kismis',      'Betina', 'Remaja', 'Sudah', 'Tersedia',         'Kucing hitam manis bercak putih di dada. Kalung kuning reflektif. Sangat tenang dan cocok untuk hunian keluarga.', @now, @now),
 (5, 2, 'KTT-03', 'Nori',        'Betina', 'Dewasa', 'Sudah', 'Proses Screening', 'Kucing kembang telon (tortoiseshell) lembut yang saat ini sedang dalam proses review calon adopter.', @now, @now),
 (6, 7, 'LWT-03', 'Luna',        'Betina', 'Dewasa', 'Sudah', 'Terdata',          'Kucing betina putih corak abu-abu di sekitar perpustakaan Liwet. Kalung ungu reflektif, sehat dan aktif.', @now, @now);

INSERT INTO foto_kucing (id_kucing, url_foto, is_utama, created_at, updated_at) VALUES
 (1, 'assets/img/cats/mochi-1.jpg',  1, @now, @now),
 (1, 'assets/img/cats/mochi-2.jpg',  0, @now, @now),
 (1, 'assets/img/cats/mochi-3.jpg',  0, @now, @now),
 (2, 'assets/img/cats/milo-1.jpg',   1, @now, @now),
 (2, 'assets/img/cats/milo-2.jpg',   0, @now, @now),
 (2, 'assets/img/cats/milo-3.jpg',   0, @now, @now),
 (3, 'assets/img/cats/oyen-1.jpg',   1, @now, @now),
 (3, 'assets/img/cats/oyen-2.jpg',   0, @now, @now),
 (3, 'assets/img/cats/oyen-3.jpg',   0, @now, @now),
 (4, 'assets/img/cats/kismis-1.jpg', 1, @now, @now),
 (4, 'assets/img/cats/kismis-2.jpg', 0, @now, @now),
 (5, 'assets/img/cats/nori-1.jpg',   1, @now, @now),
 (5, 'assets/img/cats/nori-2.jpg',   0, @now, @now),
 (6, 'assets/img/cats/luna-1.jpg',   1, @now, @now),
 (6, 'assets/img/cats/luna-2.jpg',   0, @now, @now),
 (6, 'assets/img/cats/luna-3.jpg',   0, @now, @now);

INSERT INTO riwayat_kesehatan (id_kucing, jenis_tindakan, tanggal, catatan, created_at, updated_at) VALUES
 (1, 'Sterilisasi',           '2026-01-15', 'Tuntas sterilisasi betina di Klinik Nusantara Vet. Pemulihan sempurna.', @now, @now),
 (1, 'Vaksinasi Tricat',      '2026-02-02', 'Vaksinasi tahunan lengkap.', @now, @now),
 (1, 'Pemberian Obat Cacing', '2026-09-10', 'Drontal Cat berkala 3 bulan.', @now, @now),
 (2, 'Sterilisasi',           '2026-02-10', 'Kastrasi jantan di Klinik Nusantara Vet.', @now, @now),
 (2, 'Vaksinasi Tricat',      '2026-03-01', 'Vaksinasi dasar tuntas.', @now, @now),
 (3, 'Sterilisasi',           '2026-03-12', 'Kastrasi tuntas.', @now, @now),
 (4, 'Sterilisasi',           '2026-04-05', 'Sterilisasi betina tuntas.', @now, @now),
 (5, 'Sterilisasi',           '2026-01-20', 'Sterilisasi betina tuntas.', @now, @now),
 (6, 'Sterilisasi',           '2026-05-18', 'Sterilisasi betina tuntas.', @now, @now);

-- Feeding hari ini per kucing + 2 hari sebelumnya per titik lokasi
INSERT INTO pemberian_makan (id_kucing, id_lokasi, tanggal, status_pemberian, catatan, created_at, updated_at)
SELECT id_kucing, id_lokasi, CURDATE(), 'Tuntas', 'Pakan kering 65gr + air bersih (Shift Pagi & Sore).', @now, @now FROM kucing;

INSERT INTO pemberian_makan (id_kucing, id_lokasi, tanggal, status_pemberian, catatan, created_at, updated_at) VALUES
 (NULL, 1, CURDATE() - INTERVAL 1 DAY, 'Tuntas', 'Feeding titik Vokasi, 3 kucing terlihat.', @now, @now),
 (NULL, 5, CURDATE() - INTERVAL 1 DAY, 'Tuntas', 'Feeding titik Kantin T2 Lidah Wetan.',     @now, @now),
 (NULL, 4, CURDATE() - INTERVAL 2 DAY, 'Tuntas', 'Pakan basah + kering, air diganti.',        @now, @now);

-- Artikel (id_user 2 = Admin Jaga Satwa)
INSERT INTO artikel (id_user, judul, slug, kategori, isi, thumbnail_url, status_publikasi, is_featured, is_pinned, tanggal_dibuat, tanggal_diterbitkan, created_at, updated_at) VALUES
 (2, 'Kenapa Kucing UNESA Pakai Kalung? Jangan Sembarangan Dilepas!', 'kenapa-kucing-unesa-pakai-kalung', 'Edukasi & Kampanye',
  'Ketahui alasan vital penggunaan kalung reflektif kucing kampus UNESA: tanda steril, identitas resmi, dan proteksi keselamatan malam hari dari kendaraan kampus.',
  'assets/img/blog/kalung-1.jpeg', 'PUBLISHED', 1, 1, '2026-09-30', '2026-09-30', @now, @now),
 (2, 'Program TNR Sterilisasi Massal Kampus Lidah Wetan 2026', 'program-tnr-sterilisasi-lidah-wetan-2026', 'Kesehatan Veteriner',
  'Jaga Satwa UNESA bekerja sama dengan Klinik Nusantara Vet menyelenggarakan program Trap-Neuter-Return (TNR) berkala untuk mengontrol populasi satwa kampus secara humanis.',
  'assets/img/blog/tnr.jpeg', 'PUBLISHED', 0, 0, '2026-10-04', '2026-10-04', @now, @now),
 (2, 'Panduan Mengadopsi Kucing Kampus: Hunian Indoor Safe', 'panduan-mengadopsi-kucing-kampus', 'Panduan Adopsi',
  'Sebelum mengajukan adopsi, pastikan Anda memahami komitmen pemeliharaan indoor penuh kasih sayang dan bersedia melaporkan kondisi pemantauan pasca-adopsi.',
  'assets/img/blog/hari-jadi.jpeg', 'PUBLISHED', 0, 0, '2026-10-06', '2026-10-06', @now, @now);

-- Contoh pengajuan untuk Nori (sedang di-screening) — dipakai untuk demo tracking
INSERT INTO pengajuan_adopsi (id_kucing, nomor_pengajuan, kode_tracking, nama_pemohon, nomor_whatsapp, email, alamat, status_pengajuan, tanggal_pengajuan, created_at, updated_at) VALUES
 (5, 'ADP-2026-001', 'MNDEMO-7K2Q9XPA', 'Dewi Anggraini', '081234567890', 'dewi.contoh@example.com', 'Jl. Ketintang Baru No. 12, Surabaya', 'SCREENING', CURDATE() - INTERVAL 3 DAY, @now, @now);
