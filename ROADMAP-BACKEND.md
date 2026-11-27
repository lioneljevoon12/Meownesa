# ROADMAP PENGEMBANGAN BACKEND MEOWNESA (MEOW-DEV-ROADMAP)

> **Dokumen Rujukan Resmi:** Proposal Pengembangan Website MeowNesa (Kelompok 7, Pemrograman Web, D4 Manajemen Informatika, Fakultas Vokasi, Universitas Negeri Surabaya, 2026).  
> **Teknologi Utama:** PHP 8.2+ / Laravel 11/12, Basis Data MySQL/PostgreSQL, RESTful API & Blade/JSON Responses, WhatsApp Notification Gateway, Mailer Service.  
> **Standar Penamaan Task:** Gunakan format tag `dev-backend-01`, `dev-backend-02`, dst. untuk memudahkan pembuatan GitHub Issues, Branching (`feature/dev-backend-xx`), commit message, dan Pull Request (PR).

---

## 📋 Daftar Modul Backend (Overview)

| Kode Task | Nama Modul | Rujukan Kebutuhan (Proposal) | Prioritas |
|---|---|---|:---:|
| **dev-backend-01** | Inisialisasi Database, Skema Migrasi 14 Tabel, & Seeder Master Data | Bab 2.5 (Struktur Tabel 2.5.2) | ✅ **SELESAI** |
| **dev-backend-02** | Autentikasi Pengguna Multi-Role, Manajemen Sesi & Middleware Audit Log | MEOW-F-41 s/d MEOW-F-44, MEOW-NF-01 s/d 03 | ✅ **SELESAI** |
| **dev-backend-03** | Modul Wilayah & Master Lokasi Persebaran Kucing (Ketintang & Lidah Wetan) | MEOW-F-05, MEOW-F-38, DFD 1.0 & 8.0 | ✅ **SELESAI** |
| **dev-backend-04** | Modul Manajemen Data Kucing & Galeri Multi-Foto (Publik & Admin) | MEOW-F-01 s/d 06, MEOW-BR-01 & 14 | ✅ **SELESAI** |
| **dev-backend-05** | Modul Rekam Medis & Riwayat Kesehatan Kucing (Vaksin, Steril, Vitamin) | MEOW-F-10 s/d 13, DFD 3.0 | ✅ **SELESAI** |
| **dev-backend-06** | Modul Feeding Log (Pencatatan Pemberian Makan Harian Sesuai Spreadsheet) | MEOW-F-07 s/d 09, DFD 2.0 | ✅ **SELESAI** |
| **dev-backend-07** | Modul Pengajuan Adopsi Publik & Generator Kode Tracking Unik (Tanpa Akun) | MEOW-F-14, 15, 20, MEOW-BR-03, 04 | ✅ **SELESAI** |
| **dev-backend-08** | Modul Screening & Pemeriksaan Calon Adopter (Internal Admin) | MEOW-F-16 s/d 19, MEOW-BR-05, 08 | ✅ **SELESAI** |
| **dev-backend-09** | Modul Serah Terima Adopsi (BAST) & Pembuatan Akun Adopter | MEOW-F-21 s/d 25, MEOW-BR-06, 07 | ✅ **SELESAI** |
| **dev-backend-10** | Modul Pemantauan Pasca-Adopsi & Riwayat Kondisi Berkala | MEOW-F-26 s/d 29, MEOW-BR-13 | ✅ **SELESAI** |
| **dev-backend-11** | Modul Notifikasi Otomatis & Pengingat Laporan (Email & WhatsApp Integration) | MEOW-F-30 s/d 32, MEOW-BR-12 | ✅ **SELESAI** |
| **dev-backend-12** | Modul CMS Artikel Edukasi, Kampanye Kalung, & Publikasi Informasi Publik | MEOW-F-33 s/d 37, DFD 6.0 | P2 (Content CMS) |
| **dev-backend-13** | Modul Agregasi Statistik Dashboard Admin & Data Titik Peta Persebaran | MEOW-F-38 s/d 40, DFD 8.0 | P2 (Analytics & Map) |
| **dev-backend-14** | Modul Super Admin (Manajemen Akun Pengurus, Hak Akses, & Log Aktivitas Audit) | MEOW-F-42 s/d 44, MEOW-NF-07 | P2 (Admin Governance) |

---

## 🛠️ Rincian Modul Pengembangan Backend

---

### dev-backend-01: Inisialisasi Database, Skema Migrasi 14 Tabel, & Seeder Master Data

- **Deskripsi & Tujuan:**  
  Membangun skema basis data relasional lengkap sesuai Bab 2.5 Proposal (Halaman 31–42). Mengimplementasikan 14 tabel basis data dengan *foreign key constraints*, *indexes*, dan *data integrity rules*. Menyiapkan *database seeder* untuk kampus (Ketintang & Lidah Wetan), lokasi spot kampus, akun default Super Admin & Admin Jaga Satwa, serta data awal dummy kucing binaan (Mochi, Milo, Oyen Barba, Kismis, Nori, Luna) yang identik dengan prototipe.
- **Tabel Basis Data Terkait:**  
  1. `kampus`
  2. `lokasi`
  3. `kucing`
  4. `foto_kucing`
  5. `pemberian_makan`
  6. `riwayat_kesehatan`
  7. `adopter`
  8. `pengajuan_adopsi`
  9. `screening_adopsi`
  10. `lampiran_pengajuan`
  11. `serah_terima`
  12. `pasca_adopsi`
  13. `user`
  14. `artikel`
  15. `notifikasi`
  16. `audit_log`
- **Fitur / Pekerjaan:**  
  - Buat migration Laravel berurutan memperhatikan ketergantungan relasi *foreign key*.
  - Atur tipe data spesifik (misal `DECIMAL(10,2)` untuk koordinat, `ENUM/VARCHAR` untuk status).
  - Buat Seeder: `KampusSeeder`, `LokasiSeeder`, `UserSeeder`, `KucingSeeder`, `ArtikelSeeder`.
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [x] Perintah `php artisan migrate:fresh --seed` berjalan sukses tanpa error relasi FK.
  - [x] Seluruh tabel memiliki kolom `created_at` dan `updated_at` yang terindeks baik.
  - [x] Data master awal langsung tersedia untuk pengujian modul selanjutnya (3 Users, 2 Kampus, 7 Lokasi, 6 Kucing, 7 Foto, 3 Artikel).
- **Target File / Komponen:**  
  - `database/migrations/*.php`
  - `database/seeders/DatabaseSeeder.php`
  - `app/Models/*.php` (Eloquent Model untuk seluruh 16 entitas)

---

### dev-backend-02: Autentikasi Pengguna Multi-Role, Manajemen Sesi & Middleware Audit Log

- **Deskripsi & Tujuan:**  
  Menerapkan mekanisme otentikasi aman untuk pengguna internal (Admin Jaga Satwa & Super Admin) serta Adopter setelah diaktivasi. Menyediakan perlindungan endpoint berbasis peran (*Role-Based Access Control*) dan middleware otomatis untuk mencatat setiap aktivitas mutasi data (*create/update/delete*) ke dalam tabel `audit_log`.
- **Aturan Bisnis & Batasan:**  
  - MEOW-BR-09: Dokter hewan atau klinik tidak memiliki akun pengguna sistem.
  - MEOW-NF-01 & MEOW-NF-03: Password pengguna disimpan dalam bentuk hash aman (`bcrypt`).
  - MEOW-NF-02: Pembatasan ketat otorisasi peran (Super Admin vs Admin Jaga Satwa vs Adopter).
- **Fitur & Endpoint API / Controller:**  
  - `POST /api/auth/login` (Otentikasi kredensial email & password).
  - `POST /api/auth/logout` (Pembersihan token/sesi).
  - `GET /api/auth/me` (Informasi profil pengguna aktif dan hak aksesnya).
  - Middleware `RoleMiddleware` (`role:superadmin`, `role:admin`, `role:adopter`).
  - Middleware `AuditLogMiddleware` (Merekam otomatis perubahan tabel kritis ke `audit_log`).
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [x] Pengguna tanpa hak akses ditolak dengan respon HTTP `401 Unauthorized` atau `403 Forbidden`.
  - [x] Password ter-hash kuat (minimal bcrypt cost 12).
  - [x] Setiap aksi admin (menambah/mengubah data) langsung tercatat di tabel `audit_log` lengkap dengan id_user, nama_tabel, dan waktu aktivitas.
- **Target File / Komponen:**  
  - `app/Http/Controllers/AuthController.php`
  - `app/Http/Middleware/CheckRole.php`
  - `app/Http/Middleware/RecordAuditLog.php`
  - `app/Models/User.php`, `app/Models/AuditLog.php`
  - `app/Services/AuditLogger.php`, `routes/api.php`, `bootstrap/app.php` (alias middleware & error JSON)
  - `tests/Feature/AuthApiTest.php` (16 tes; SQLite in-memory, tidak menyentuh MySQL dev)
- **Catatan implementasi:**  
  - Token Bearer via Laravel Sanctum, masa berlaku `SANCTUM_EXPIRATION` (menit, default 720).  
  - Login dibatasi 5x/menit per email+IP (429). Login gagal/ditolak juga masuk `audit_log`.  
  - `role:` bersifat eksak (Super Admin tidak otomatis lolos `role:admin`). Akun `nonaktif` langsung 403 walau token masih berlaku.  
  - Konvensi untuk modul berikutnya: `/api/admin/*` → `['auth:sanctum','role:admin','audit:<tabel>']`.

---

### dev-backend-03: Modul Wilayah & Master Lokasi Persebaran Kucing (Ketintang & Lidah Wetan)

- **Deskripsi & Tujuan:**  
  Menyediakan API pengelolaan data kampus (Kampus Ketintang dan Kampus Lidah Wetan) beserta titik-titik persebaran kucing (Gedung Vokasi K10, Kantin T2, FMIPA C, FEB G, Gazebo Rektorat, dll.) lengkap dengan data koordinat untuk peta digital.
- **Aturan Bisnis & Batasan:**  
  - MEOW-F-05: Lokasi kucing wajib terikat pada kampus resmi UNESA (Ketintang / Lidah Wetan).
  - MEOW-NF-06: Koordinat publik hanya menampilkan area fasilitas umum kampus tanpa menampilkan alamat privat adopter.
- **Fitur & Endpoint API / Controller:**  
  - `GET /api/kampus` (Daftar kampus).
  - `GET /api/lokasi` (Daftar spot per kampus dengan filter `?id_kampus=`).
  - `POST /api/admin/lokasi` (Admin menambah spot gedung baru beserta koordinat).
  - `PUT /api/admin/lokasi/{id}` & `DELETE /api/admin/lokasi/{id}`.
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [x] Endpoint publik menyajikan daftar titik lokasi umum kampus.
  - [x] Lokasi tervalidasi terhubung dengan `id_kampus` yang sah.
- **Target File / Komponen:**  
  - `app/Http/Controllers/KampusController.php`
  - `app/Http/Controllers/LokasiController.php`
  - `app/Models/Kampus.php`, `app/Models/Lokasi.php`
  - `app/Http/Requests/StoreLokasiRequest.php`, `UpdateLokasiRequest.php`, `app/Http/Resources/KampusResource.php`, `LokasiResource.php`
  - `database/seeders/LokasiSeeder.php`, `KucingSeeder.php`, `database/seeders/data/kucing_aktual.json`, `public/images/kucing/`
  - `backend/tools/import-presensi/` (skrip impor spreadsheet presensi)
  - `tests/Feature/LokasiApiTest.php` (17 tes), `tests/Feature/SeederDataAktualTest.php` (5 tes)
- **Catatan implementasi:**  
  - Tambahan endpoint `GET /api/lokasi/{id}`. Respons kampus & lokasi memuat `jumlah_kucing` (hanya status Terdata/Tersedia/Proses Screening).  
  - Nama lokasi unik per kampus; koordinat X = lintang (−90..90), Y = bujur (−180..180), maks. 8 desimal.  
  - `DELETE` ditolak **409** bila lokasi masih dipakai kucing atau feeding log (FK di DB bersifat CASCADE, jadi pengecekan ini mencegah data kucing ikut terhapus).  
  - **Data aktual:** seeder kini memakai spreadsheet presensi Jaga Satwa — 28 lokasi (22 Ketintang, 6 Lidah Wetan), 98 kucing, 67 foto. Data dummy (Mochi, Milo, dst.) dan riwayat medis/feeding dummy dihapus.  
  - Nilai baru: `status_kucing = Hilang`, `status_steril = Tidak Diketahui` (konstanta di `App\Models\Kucing`).  
  - **Koordinat semua lokasi masih titik tengah kampus** (spreadsheet tidak memuat koordinat) — perlu dikoreksi admin.

---

### dev-backend-04: Modul Manajemen Data Kucing & Galeri Multi-Foto (Publik & Admin)

- **Deskripsi & Tujuan:**  
  Menangani data populasi kucing resmi Jaga Satwa UNESA. Mengelola identitas kucing, nomor kalung unik (`kode_kucing`), ras, jenis kelamin, usia, status steril, status kucing (*TERSEDIA, SCREENING, DIADOPSI, ALMARHUM*), serta sistem upload multi-foto galeri dengan penanda foto utama (`is_utama`).
- **Aturan Bisnis & Batasan:**  
  - MEOW-BR-01: Hanya kucing yang telah diverifikasi resmi Jaga Satwa yang dapat masuk ke sistem.
  - MEOW-BR-14: Kucing hanya dapat berstatus "Tersedia Adopsi" jika sudah steril dan dinyatakan sehat oleh Admin.
  - MEOW-NF-05: Data internal kucing dipisahkan dari payload yang disajikan ke publik.
- **Fitur & Endpoint API / Controller:**  
  - `GET /api/kucing` (Katalog publik: filter kampus, status, gender, pencarian nama/ciri).
  - `GET /api/kucing/{id}` (Detail publik kucing: info umum, foto utama, galeri foto, status).
  - `POST /api/admin/kucing` (Admin tambah kucing baru).
  - `PUT /api/admin/kucing/{id}` (Admin update data & status kucing).
  - `DELETE /api/admin/kucing/{id}` (Admin arsipkan/hapus data kucing).
  - `POST /api/admin/kucing/{id}/foto` (Upload foto tambahan galeri).
  - `PUT /api/admin/kucing/{id}/foto/{foto_id}/utama` (Set foto profil utama).
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [x] Publik hanya melihat kucing yang statusnya publik (*Tersedia, Terdata*).
  - [x] File gambar tersimpan rapi di storage publik dengan kompresi/validasi mime type (`jpg, png, webp`).
  - [x] Setiap kucing memiliki tepat satu foto bertanda `is_utama = true`.
- **Target File / Komponen:**  
  - `app/Http/Controllers/KucingController.php`
  - `app/Http/Controllers/FotoKucingController.php`
  - `app/Models/Kucing.php`, `app/Models/FotoKucing.php`
  - `app/Http/Controllers/KucingAdminController.php` (admin: daftar semua status, tambah, ubah, hapus)
  - `app/Http/Requests/StoreKucingRequest.php`, `UpdateKucingRequest.php`, `UploadFotoKucingRequest.php`, `FilterKucingRequest.php`, `FilterKucingAdminRequest.php`
  - `app/Http/Resources/KucingResource.php` (publik), `KucingAdminResource.php`, `FotoKucingResource.php`
  - `app/Services/FotoKucingService.php`, `KodeKucingGenerator.php`
  - `tests/Feature/KucingApiTest.php`, `KucingAdminApiTest.php`, `FotoKucingApiTest.php` (41 tes)
- **Catatan implementasi:**  
  - Tambahan endpoint: `GET /api/admin/kucing` & `GET /api/admin/kucing/{id}` (admin melihat semua status), `DELETE /api/admin/kucing/{id}/foto/{foto_id}`.  
  - **Katalog publik:** paginasi (`per_page` maks. 50, respons memuat `meta`), filter `q`, `id_kampus`, `id_lokasi`, `status`, `jenis_kelamin`, `kelompok_usia`, `status_steril`, `sort` (`nama`/`terbaru`). Status non-publik (Proses Screening, Diadopsi, Hilang, Almarhum) tidak tampil di daftar maupun detail (404).  
  - **MEOW-BR-14:** status `Tersedia` hanya bila `status_steril = Sudah` **dan** admin mengirim `konfirmasi_sehat = true` saat menaikkan status (belum ada kolom kesehatan; konfirmasi ini bersifat pernyataan admin dan tercatat di audit log). Kucing Tersedia tidak boleh diubah jadi belum steril tanpa menurunkan statusnya dulu.  
  - **Status manual:** admin hanya bisa mengisi `Terdata/Tersedia/Hilang/Almarhum`. `Proses Screening` dan `Diadopsi` diatur otomatis oleh alur adopsi (modul 07-09, MEOW-BR-07).  
  - **Kode kucing** otomatis per kampus (`KTT-081`, `LWT-019`) bila tidak diisi; boleh manual (huruf besar, unik).  
  - **Hapus kucing** ditolak **409** bila sudah punya riwayat kesehatan, pengajuan adopsi, atau feeding (FK CASCADE/SET NULL akan menghapus riwayat); gunakan status Almarhum/Hilang.  
  - **Foto:** JPG/PNG/WebP maks. 5 MB, 200-6000 px, maks. 10 foto/kucing. Setiap unggahan di-encode ulang ke JPEG maks. 1280 px, orientasi EXIF diterapkan dan **seluruh metadata EXIF (termasuk GPS) dibuang**. Foto pertama otomatis jadi utama; hapus foto utama menaikkan foto tertua yang tersisa. Perubahan galeri memakai transaksi + kunci baris agar selalu tepat satu foto utama.  
  - Unggahan disimpan di `public/images/kucing/upload/` (tanpa `storage:link`), diabaikan git. Foto bawaan seeder tidak pernah dihapus dari disk.

---

### dev-backend-05: Modul Rekam Medis & Riwayat Kesehatan Kucing (Vaksin, Steril, Vitamin)

- **Deskripsi & Tujuan:**  
  Mencatat histori tindakan medis dan perawatan klinis setiap kucing (sterilisasi, vaksinasi tricat/rabies, obat cacing/kutu, pemberian vitamin, penanganan luka). Data bersumber dari rekam jejak Jaga Satwa bersama klinik mitra (Nusantara Vet).
- **Aturan Bisnis & Batasan:**  
  - MEOW-BR-09: Klinik/dokter hewan merupakan pihak eksternal dan tidak memiliki akun pengguna sistem; pencatatan dilakukan oleh Admin Jaga Satwa.
  - MEOW-F-11 & 12: Riwayat medis mencakup tanggal tindakan, jenis tindakan, dan catatan medis.
- **Fitur & Endpoint API / Controller:**  
  - `GET /api/kucing/{id}/kesehatan` (Daftar riwayat kesehatan publik kucing terkait).
  - `POST /api/admin/kucing/{id}/kesehatan` (Admin input tindakan medis baru).
  - `PUT /api/admin/kesehatan/{id}` & `DELETE /api/admin/kesehatan/{id}`.
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [x] Tindakan sterilisasi yang diinput otomatis menyinkronkan atribut `status_steril` pada data kucing.
  - [x] Riwayat medis tersusun urut secara kronologis (dari tindakan terbaru).
- **Target File / Komponen:**  
  - `app/Http/Controllers/RiwayatKesehatanController.php` (publik), `RiwayatKesehatanAdminController.php`
  - `app/Models/RiwayatKesehatan.php`
  - `app/Http/Requests/RiwayatKesehatanRequest.php`, `StoreRiwayatKesehatanRequest.php`, `UpdateRiwayatKesehatanRequest.php`
  - `app/Http/Resources/RiwayatKesehatanResource.php` (publik), `RiwayatKesehatanAdminResource.php`
  - `tests/Feature/KesehatanApiTest.php` (21 tes)
- **Catatan implementasi:**  
  - Tambahan endpoint `GET /api/admin/kucing/{id}/kesehatan` (admin melihat riwayat lengkap dengan `id_kesehatan` untuk ubah/hapus, termasuk kucing non-publik). Riwayat publik hanya untuk kucing yang tampil di katalog (selain itu 404) dan hanya memuat `tanggal`, `jenis_tindakan`, `catatan`.  
  - **Jenis tindakan** (daftar tetap): Sterilisasi, Vaksinasi, Pemeriksaan, Pemberian Vitamin, Obat Cacing, Obat Kutu, Penanganan Luka, Lainnya. Detail seperti "Tricat" atau nama klinik ditulis di `catatan`; `Lainnya` wajib berisi catatan.  
  - **Tanggal** format `YYYY-MM-DD`, tidak boleh di masa depan menurut **WIB** (bukan UTC bawaan aplikasi) dan tidak sebelum 2015.  
  - **Sinkronisasi steril satu arah:** mencatat atau mengubah menjadi `Sterilisasi` menetapkan `kucing.status_steril = Sudah` dan menulis audit `SINKRON_STERIL`. Menghapus atau mengubah catatan sterilisasi **tidak** mengembalikan status ke Belum, karena status "Sudah" juga berasal dari data presensi yang tidak punya catatan medis (kebenaran sebelumnya tidak diketahui sistem).  
  - Satu kucing hanya boleh satu catatan `Sterilisasi` (dicek ulang di dalam transaksi dengan kunci baris kucing).  
  - Kucing yang sudah punya riwayat kesehatan tidak dapat dihapus (409, lihat modul 04).  
  - Data seeder tidak berisi riwayat kesehatan (spreadsheet presensi hanya memuat status steril); riwayat diisi admin lewat API.

---

### dev-backend-06: Modul Feeding Log (Pencatatan Pemberian Makan Harian Sesuai Spreadsheet)

- **Deskripsi & Tujuan:**  
  Menggantikan sistem pencatatan spreadsheet manual dengan database terstruktur. Mencatat log pemberian makan harian per titik kampus/kucing oleh relawan feeder Jaga Satwa.
- **Aturan Bisnis & Batasan:**  
  - MEOW-F-07: Pencatatan berdasarkan tanggal, lokasi, kucing terkait, status pemberian makan, dan catatan.
  - Catatan Proposal (Hal. 18): Jam/waktu pemberian makan spesifik tidak menjadi kolom wajib, mengikuti pola praktis spreadsheet Jaga Satwa.
- **Fitur & Endpoint API / Controller:**  
  - `GET /api/admin/feeding` (Filter log feeding per tanggal, per kampus/lokasi, atau per kucing).
  - `POST /api/admin/feeding` (Catat feeding sesi baru).
  - `PUT /api/admin/feeding/{id}` & `DELETE /api/admin/feeding/{id}`.
  - `GET /api/admin/feeding/rekap` (Ringkasan statistik feeding mingguan/bulanan).
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [x] Admin dapat mencatat feeding untuk satu titik lokasi atau kucing spesifik.
  - [x] Menampilkan riwayat log feeding dengan filtering tanggal yang responsif.
- **Target File / Komponen:**  
  - `app/Http/Controllers/PemberianMakanController.php`
  - `app/Models/PemberianMakan.php`
  - `app/Services/FeedingService.php` (aturan bisnis + simpan idempoten, dipakai tunggal dan batch)
  - `app/Http/Requests/StorePemberianMakanRequest.php`, `UpdatePemberianMakanRequest.php`, `FilterPemberianMakanRequest.php`, `RekapPemberianMakanRequest.php`
  - `app/Http/Resources/PemberianMakanResource.php`
  - `database/migrations/2026_11_25_000001_add_client_uuid_to_pemberian_makan_table.php`
  - `tests/Feature/FeedingApiTest.php` (24 tes)
- **Catatan implementasi:**
  - Semua endpoint **admin saja** (data internal). Tambahan: `POST /api/admin/feeding/batch` (sinkronisasi massal dari HP).
  - **Satu catatan = satu kunci** (tanggal, lokasi, kucing). Kucing opsional (kosong = catatan untuk seluruh titik lokasi, pola spreadsheet). Bila hanya kucing yang dikirim, lokasi diambil dari lokasi tetap kucing; kucing boleh diberi makan di lokasi lain. Kunci yang sama ditolak 422 (pesan memuat `id_pemberian` yang sudah ada).
  - **Status:** `Tuntas` (= Done), `Belum` (= Not yet), `Terjadwal` (rencana). Tuntas/Belum tidak boleh bertanggal masa depan menurut **WIB**; Terjadwal harus hari ini atau setelahnya. Tanggal paling awal 2020-01-01. Waktu pemberian tidak dicatat (Bab 2.3.2).
  - **Kucing berstatus Almarhum/Hilang/Diadopsi tidak bisa dicatat** pemberian makannya. Saat mengubah catatan lama, hanya bagian yang diubah yang diperiksa, sehingga catatan historis kucing yang kini almarhum tetap bisa dikoreksi.
  - **Siap offline-first:** kolom baru `client_uuid` (nullable, unik). Pengiriman ulang UUID yang sama dibalas **200** dengan catatan asli (bukan duplikat/422) dan tidak masuk audit log sebagai CREATE. `batch` menerima maks. 100 catatan; tiap catatan diproses mandiri dengan hasil `dibuat | sudah_ada | ditolak` (disertai alasan). Baris lokasi dikunci selama menyimpan agar dua permintaan bersamaan tidak lolos pengecekan duplikat.
  - **Rekap** `GET /api/admin/feeding/rekap?periode=mingguan|bulanan&tanggal=` (bawaan: pekan berjalan WIB): pekan = Senin-Minggu, bulan = bulan kalender. Berisi ringkasan (`tuntas`, `belum`, `terjadwal`, `total`, `persen_tuntas` = tuntas/(tuntas+belum)), rincian per lokasi, dan per hari (semua hari ditampilkan, nol bila kosong; siap untuk grafik). Filter `id_kampus` dan `id_lokasi`.
  - Kolom `tanggal` memakai cast `date:Y-m-d` agar tersimpan identik di MySQL dan SQLite.
  - **Riwayat presensi di spreadsheet belum diimpor** (hanya API-nya yang tersedia); butuh skrip impor tersendiri bila ingin memindahkan data historis.

---

### dev-backend-07: Modul Pengajuan Adopsi Publik & Generator Kode Tracking Unik (Tanpa Akun)

- **Deskripsi & Tujuan:**  
  Menyediakan alur formulir pengajuan adopsi bagi masyarakat umum (calon adopter) tanpa mewajibkan pembuatan akun terlebih dahulu. Sistem secara otomatis men-generate nomor pengajuan unik (`ADP-YYYY-XXX`) dan `kode_tracking` acak untuk melacak progres verifikasi secara mandiri.
- **Aturan Bisnis & Batasan:**  
  - MEOW-BR-03: Calon adopter dapat mengajukan adopsi tanpa akun.
  - MEOW-BR-04: Setiap pengajuan wajib memiliki `nomor_pengajuan` dan `kode_tracking`.
  - MEOW-BR-14: Pengajuan hanya dapat dilakukan pada kucing berstatus "Tersedia".
  - MEOW-NF-04 & 08: Data pribadi pelamar (KTP, kontak) disimpan terisolasi dan aman.
- **Fitur & Endpoint API / Controller:**  
  - `POST /api/adopsi/ajukan` (Submit form pengajuan: nama, no_whatsapp, email, alamat, upload lampiran KTP/foto rumah).
  - `GET /api/adopsi/tracking/{kode_tracking}` (Publik mengecek progres pengajuan menggunakan kode tracking).
  - `POST /api/adopsi/upload-lampiran` (Penyimpanan file bukti syarat ke `lampiran_pengajuan`).
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [x] Sistem berhasil membuat record di `pengajuan_adopsi` dan relasi file di `lampiran_pengajuan`.
  - [x] Mengembalikan kode tracking unik yang langsung bisa dicek di halaman tracking.
  - [x] Status awal pengajuan otomatis `SUBMITTED / MENUNGGU_REVIEW` (di basis data: `MENUNGGU`).
- **Target File / Komponen:**  
  - `app/Http/Controllers/PengajuanAdopsiController.php`
  - `app/Models/PengajuanAdopsi.php`, `app/Models/LampiranPengajuan.php`
  - `app/Services/TrackingCodeGenerator.php`
  - `app/Services/LampiranService.php`
  - `app/Http/Requests/AjukanAdopsiRequest.php`, `UploadLampiranRequest.php`
  - `app/Http/Resources/PengajuanPublikResource.php`
  - `tests/Feature/PengajuanAdopsiApiTest.php` (26 tes)
- **Catatan implementasi:**
  - **Kode tracking** acak 12 karakter (`MN-XXXX-XXXX-XXXX`, alfabet tanpa I/O/0/1, dari `random_int`, ~60 bit) — inilah satu-satunya kunci akses ke sebuah pengajuan. **Nomor pengajuan** `ADP-YYYY-NNN` berurutan per tahun (tahun menurut WIB). Keunikan dijaga constraint UNIQUE; bila terjadi tabrakan balapan, pembuatan diulang.
  - **Status:** `MENUNGGU` (awal), `SCREENING`, `DISETUJUI`, `DITOLAK`, `SELESAI`; aktif = `MENUNGGU`/`SCREENING`. Respons publik memuat label, keterangan, dan 4 tahap kemajuan untuk linimasa UI.
  - **MEOW-BR-14:** pengajuan hanya untuk kucing berstatus `Tersedia`; dicek ulang di dalam transaksi dengan baris kucing terkunci. Pelamar yang sama (email **atau** WhatsApp) tidak boleh punya dua pengajuan aktif untuk kucing yang sama; setelah diputuskan boleh mengajukan lagi. Pesan duplikat tidak membocorkan nomor pengajuan.
  - **Formulir:** `nama_pemohon`, `nomor_whatsapp` (dinormalkan ke `628…`), `email` (huruf kecil), `alamat`, serta `persetujuan_data` (wajib dicentang; bukan kolom tersimpan, hanya gerbang persetujuan pengolahan data pribadi). Hanya kolom yang ada di tabel proposal; penilaian kelayakan oleh admin di modul 08.
  - **Lampiran** (opsional saat mengajukan: `ktp`, `foto_rumah`, `dokumen_pendukung`; atau ditambah lewat `POST /api/adopsi/upload-lampiran` dengan `kode_tracking`): JPG/PNG/WebP/PDF dicek dari **isi berkas** (skrip PHP berekstensi .jpg ditolak), maks. 5 MB, maks. 5 per pengajuan, hanya selagi pengajuan masih aktif. Disimpan di disk **privat** `lampiran` (`storage/app/private/lampiran/{id_pengajuan}/{40 karakter acak}.ext`), bukan di `public/`, dengan nama acak buatan server; tidak bisa dibuka lewat URL mana pun. Berkas disimpan apa adanya (KTP tetap terbaca, metadata foto rumah tetap bisa diperiksa admin). Bila ada lampiran gagal, seluruh pengajuan dibatalkan dan tidak ada berkas yatim.
  - **Pelacakan** `GET /api/adopsi/tracking/{kode}` (tidak peka huruf besar/kecil; `?nomor_pengajuan=` opsional untuk verifikasi tambahan sesuai proposal 2.1.2): hanya memuat status, kemajuan, **nama depan**, kucing, dan jenis lampiran. **Tidak pernah** memuat email, WhatsApp, alamat, nama lengkap, jalur berkas, hasil screening, atau catatan admin. Kode salah, kode tak dikenal, dan salah nomor menghasilkan 404 yang identik.
  - **Rate limit per IP:** pengajuan dan unggah lampiran 30/jam, pelacakan 30/menit (429 JSON berbahasa Indonesia).
  - Bug dari modul 02 ikut diperbaiki: respons 429 sebelumnya berbahasa Inggris karena nama kelas `ThrottleRequestsException` salah di `bootstrap/app.php`.
  - **Belum ada** (modul berikutnya): daftar/detail pengajuan dan unduh lampiran untuk admin (08), notifikasi konfirmasi email/WhatsApp berisi kode tracking (11). Sementara itu kode tracking hanya ditampilkan satu kali di respons pengajuan.

---

### dev-backend-08: Modul Screening & Pemeriksaan Calon Adopter (Internal Admin)

- **Deskripsi & Tujuan:**  
  Modul verifikasi internal bagi Admin Jaga Satwa untuk mengevaluasi data calon adopter berdasarkan 4 kriteria baku: kelayakan hunian indoor, kestabilan ekonomi/penghasilan, pemahaman perawatan anabul, dan kesediaan menggunakan layanan dokter hewan.
- **Aturan Bisnis & Batasan:**  
  - MEOW-BR-05: Keputusan persetujuan atau penolakan pengajuan sepenuhnya wewenang Admin Jaga Satwa.
  - MEOW-BR-08: Informasi screening dan identitas calon adopter tidak boleh bocor ke publik.
- **Fitur & Endpoint API / Controller:**  
  - `GET /api/admin/adopsi` (Daftar antrean pengajuan dengan filter status).
  - `GET /api/admin/adopsi/{id}` (Detail lengkap pengajuan & lampiran berkas).
  - `POST /api/admin/adopsi/{id}/screening` (Simpan evaluasi kriteria & catatan admin di tabel `screening_adopsi`).
  - `PUT /api/admin/adopsi/{id}/status` (Update status: `APPROVED`, `REJECTED`, atau `SCREENING`).
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [x] Setiap screening mencatat `id_user` admin penanggung jawab verifikasi.
  - [x] Data kriteria evaluasi tersimpan utuh di tabel `screening_adopsi`.
  - [x] Perubahan status memicu pembuatan record notifikasi untuk pelamar.
- **Target File / Komponen:**  
  - `app/Http/Controllers/ScreeningAdopsiController.php`
  - `app/Models/ScreeningAdopsi.php`
  - `app/Http/Requests/StoreScreeningRequest.php`
  - `app/Http/Controllers/PengajuanAdopsiAdminController.php` (antrean, detail, unduh lampiran, ubah status)
  - `app/Services/PengajuanStatusService.php` (mesin status), `NotifikasiPengajuanService.php`
  - `app/Http/Requests/FilterPengajuanAdminRequest.php`, `UbahStatusPengajuanRequest.php`
  - `app/Http/Resources/PengajuanAdminResource.php`, `PengajuanAdminDetailResource.php`, `ScreeningResource.php`
  - `tests/Feature/PengajuanAdminApiTest.php` (9 tes), `ScreeningStatusApiTest.php` (20 tes)
- **Catatan implementasi:**
  - **Semua endpoint `role:admin` saja**: Super Admin mendapat 403 (BR-05, keputusan adopsi bukan wewenang teknis).
  - **Antrean** `GET /api/admin/adopsi`: urut yang paling lama menunggu di atas (`sort=terbaru` tersedia); filter `status`, `id_kucing`, `q` (nomor, nama, email, WhatsApp, kode tracking), `dari`/`sampai`; paginasi; `meta.jumlah_per_status` (tidak terpengaruh filter) untuk lencana tab. **Detail** memuat alamat, kode tracking (membantu pemohon yang lupa), lampiran, screening lengkap beserta petugas, dan riwayat notifikasi.
  - **Unduh lampiran** `GET /api/admin/adopsi/{id}/lampiran/{lampiran}`: hanya admin; gambar `inline`, PDF selalu `attachment`; header `nosniff` dan `no-store`; nama berkas aman (`ADP-…-ktp.jpg`). **Setiap akses dicatat di audit log** (`LIHAT_LAMPIRAN`) karena berisi data pribadi.
  - **Screening** `POST /api/admin/adopsi/{pengajuan}/screening` (upsert: 201 membuat, 200 memperbarui; audit `CREATE`/`UPDATE`): hanya saat pengajuan `SCREENING`; hasil `Lolos|Ragu|Tidak Lolos`; **`Lolos` mewajibkan keempat kriteria terisi**; `id_user` = admin yang terakhir menyimpan; tanggal bawaan hari ini (WIB). Terkunci setelah diputuskan; untuk koreksi, kembalikan status ke `SCREENING`.
  - **Mesin status** `PUT /api/admin/adopsi/{id}/status` (`SCREENING|DISETUJUI|DITOLAK`): `MENUNGGU→SCREENING|DITOLAK`, `SCREENING→DISETUJUI|DITOLAK`, `DISETUJUI→SCREENING|DITOLAK`; `DITOLAK` final; `SELESAI` hanya oleh serah terima (modul 09). `DISETUJUI` butuh hasil screening dan bukan `Tidak Lolos`.
  - **Sinkron status kucing (NF-14):** mulai `SCREENING` → kucing `Tersedia` menjadi `Proses Screening` (hanya satu pengajuan yang boleh memegang satu kucing; yang lain tetap mengantre); `DITOLAK` setelah memegang → kucing kembali `Tersedia`; `DISETUJUI` → kucing tetap `Proses Screening` sampai serah terima (BR-07). Perubahan kucing dicatat audit `SINKRON_STATUS_KUCING`. Kucing yang sedang dipegang pengajuan **tidak bisa diubah statusnya dari edit data kucing** (guard baru di modul 04).
  - **Notifikasi:** tiap perubahan status membuat 2 catatan `PENDING` (WhatsApp + Email, `id_user` kosong karena pemohon belum punya akun) berjenis `Status Adopsi`. Saat pengajuan masuk (modul 07) kini dibuat juga `Konfirmasi Pengajuan` berisi nomor pengajuan, **kode tracking**, dan tautan pelacakan (`FRONTEND_URL`). Pesan tidak memuat alasan keputusan, hasil screening, email, WhatsApp, atau alamat. Pengiriman nyata: modul 11.
  - Mekanisme audit baru: `RecordAuditLog::AKTIVITAS_KHUSUS` untuk menimpa nama aktivitas pada endpoint upsert via POST.

---

### dev-backend-09: Modul Serah Terima Adopsi (BAST) & Pembuatan Akun Adopter

- **Deskripsi & Tujuan:**  
  Mencatat proses serah terima kucing yang telah disetujui adopsinya, mengunggah Berita Acara Serah Terima (BAST) atau surat perjanjian dan foto dokumentasi, memperbarui status kucing menjadi `diadopsi`, serta membuat data entitas `adopter` dan mengaktifkan akun pengguna (`user`).
- **Aturan Bisnis & Batasan:**  
  - MEOW-BR-06: Akun adopter dibuat setelah pengajuan adopsi disetujui, bukan saat pengajuan awal.
  - MEOW-BR-07: Status kucing resmi berubah menjadi `diadopsi` setelah proses serah terima selesai dicatat.
- **Fitur & Endpoint API / Controller:**  
  - `POST /api/admin/adopsi/{id}/serah-terima` (Catat serah terima, upload dokumen BAST & foto).
  - `POST /api/admin/adopsi/{id}/aktivasi-adopter` (Generate akun user dengan role `adopter` dan hubungkan ke tabel `adopter`).
  - `GET /api/admin/serah-terima/{id}` (Lihat detail legalitas serah terima).
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [x] Status kucing di tabel `kucing` otomatis terupdate menjadi `diadopsi`.
  - [x] Record baru terbentuk di tabel `serah_terima` dan tabel `adopter`.
  - [x] Akun `user` adopter terbuat dengan kredensial aman untuk menerima pemberitahuan.
- **Target File / Komponen:**  
  - `app/Http/Controllers/SerahTerimaController.php`
  - `app/Models/SerahTerima.php`, `app/Models/Adopter.php`
  - `app/Http/Controllers/AktivasiAkunController.php`
  - `app/Services/SerahTerimaService.php`, `AktivasiAkunService.php`, `SerahTerimaBerkasService.php`
  - `app/Http/Requests/StoreSerahTerimaRequest.php`, `UploadBerkasSerahTerimaRequest.php`, `AktivasiAkunRequest.php`
  - `app/Http/Resources/SerahTerimaResource.php`
  - `tests/Feature/SerahTerimaApiTest.php` (23 tes), `AktivasiAkunApiTest.php` (11 tes)
- **Catatan implementasi:**
  - **Keputusan desain (atas arahan pemilik proyek): akun adopter dibuat OTOMATIS di langkah serah terima**, bukan lewat endpoint `aktivasi-adopter` terpisah seperti di draf roadmap. Endpoint itu diganti oleh: `POST /api/auth/aktivasi` (adopter mengatur password lewat tautan) dan `POST /api/admin/adopsi/{id}/kirim-ulang-aktivasi` (tautan baru bila kedaluwarsa/hilang). Akun **tidak** dibuat saat mengajukan (BR-06).
  - **Serah terima** `POST /api/admin/adopsi/{pengajuan}/serah-terima` (hanya pengajuan `DISETUJUI` dan kucing `Proses Screening`; `tanggal_serah_terima` wajib, tidak di masa depan menurut WIB dan tidak sebelum tanggal pengajuan; `dokumen_perjanjian` dan `foto_serah_terima` opsional). Dalam **satu transaksi**: catatan serah terima, kucing -> `Diadopsi` (BR-07), pengajuan -> `SELESAI` + `id_adopter`, data/akun adopter, pengajuan lain yang masih `MENUNGGU` untuk kucing itu ditutup (`DITOLAK`, dengan notifikasi "sudah diadopsi oleh pemohon lain"), dan notifikasi. Gagal di mana pun = tidak ada yang berubah dan tidak ada berkas yatim.
  - **Akun adopter:** `role=adopter`, `status_akun=menunggu_aktivasi` (tidak bisa login), password acak 64 karakter yang tidak diketahui siapa pun. Bila email sudah milik adopter (mengadopsi lagi) akun dipakai ulang (satu `adopter`, banyak pengajuan); bila email milik akun internal, ditolak (422) agar akun admin tidak tertukar.
  - **Aktivasi:** token acak dari broker Laravel `aktivasi` (disimpan **ter-hash** di `password_reset_tokens`, sekali pakai, kedaluwarsa **7 hari**), dikirim hanya lewat pesan notifikasi (WhatsApp + Email, `PENDING`; pengiriman nyata di modul 11). **Token tidak pernah muncul di respons API admin**. Password min. 8 karakter berisi huruf dan angka, maks. 64, konfirmasi wajib. Semua kegagalan (token salah, kedaluwarsa, terpakai, email tak dikenal, akun internal/dinonaktifkan) menghasilkan pesan yang sama. Dibatasi `throttle:login` (5/menit per email+IP, dibagi dengan login).
  - **Berkas** (BAST: PDF/JPG/PNG/WebP; foto: JPG/PNG/WebP; maks. 5 MB, dicek dari isi) disimpan di disk **privat** `serah_terima` dengan nama acak. Unduh `GET /api/admin/serah-terima/{id}/berkas/{dokumen_perjanjian|foto_serah_terima}` (PDF selalu `attachment`, `nosniff`, `no-store`) **diaudit** (`LIHAT_BERKAS_SERAH_TERIMA`). Unggah/ganti belakangan: `POST /api/admin/serah-terima/{id}/berkas` (berkas lama dihapus dari disk).
  - `GET /api/admin/serah-terima/{id}` memuat pengajuan, kucing, adopter dan status akunnya; ringkasan serah terima juga muncul di detail pengajuan.
  - Audit baru: `BUAT_AKUN_ADOPTER`, `AKTIVASI_AKUN`, `KIRIM_ULANG_AKTIVASI`, `UNGGAH_BERKAS`.
  - Catatan: tautan aktivasi tersimpan di `notifikasi.isi_pesan` sampai terkirim; modul 11 sebaiknya menghapus/menyamarkan tokennya setelah status `TERKIRIM`.

---

### dev-backend-10: Modul Pemantauan Pasca-Adopsi & Riwayat Kondisi Berkala

- **Deskripsi & Tujuan:**  
  Mengelola pencatatan evaluasi kondisi kucing setelah diadopsi (monitoring mingguan / bulan ke-1, 3, dan 6). Admin mencatat status kondisi kucing (sehat, butuh perhatian, sakit, dsb.) dan catatan perkembangan berdasarkan laporan yang dikirimkan adopter.
- **Aturan Bisnis & Batasan:**  
  - MEOW-BR-13: Adopter mengirimkan pembaruan kondisi via WhatsApp/CP, dan hasil pemantauan dicatat secara terpusat oleh Admin Jaga Satwa.
  - MEOW-F-26 & 27: Menjaga histori perkembangan kucing teradopsi dari waktu ke waktu.
- **Fitur & Endpoint API / Controller:**  
  - `GET /api/admin/pasca-adopsi` (Daftar pemantauan aktif dan jadwal jatuh tempo).
  - `POST /api/admin/pasca-adopsi` (Admin mencatat laporan kondisi baru di `pasca_adopsi`).
  - `GET /api/admin/pasca-adopsi/{id_serah_terima}/riwayat` (Histori kondisi per adopsi).
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [x] Riwayat kondisi kucing pasca-adopsi tersimpan runtut berdasarkan `tanggal_update`.
  - [x] Sistem mampu mengidentifikasi status kondisi terkini kucing teradopsi.
- **Target File / Komponen:**  
  - `app/Http/Controllers/PascaAdopsiController.php`
  - `app/Models/PascaAdopsi.php`
  - `app/Services/PemantauanPascaAdopsiService.php` (jadwal & status pemantauan; dipakai ulang modul 11)
  - `app/Http/Requests/PascaAdopsiRequest.php`, `StorePascaAdopsiRequest.php`, `UpdatePascaAdopsiRequest.php`, `FilterPascaAdopsiRequest.php`
  - `app/Http/Resources/PascaAdopsiResource.php`
  - `tests/Feature/PascaAdopsiApiTest.php` (23 tes)
- **Catatan implementasi:**
  - **Hanya admin** (adopter memberi kabar lewat WhatsApp/CP, admin yang mencatat; BR-13). Tambahan endpoint: `PUT`/`DELETE /api/admin/pasca-adopsi/{id}` untuk koreksi salah catat.
  - **Kondisi:** `Sehat`, `Perawatan`, `Sakit`, `Hilang`, `Meninggal`. Selain `Sehat` **wajib ada catatan**. `Meninggal` mengakhiri pemantauan (pembaruan baru ditolak; salah catat dikoreksi/dihapus); `Hilang` tidak mengakhiri karena kucing bisa ditemukan kembali. Status `kucing` **tidak diubah** oleh modul ini (kucing yang sudah diadopsi bukan bagian populasi kampus).
  - **Tanggal pembaruan** `YYYY-MM-DD`: tidak boleh di masa depan (WIB) dan tidak sebelum tanggal serah terima; beberapa pembaruan pada hari yang sama diperbolehkan.
  - **Jadwal (semua WIB):** jatuh tempo = pembaruan terakhir (atau tanggal serah terima bila belum ada) **+ 7 hari** (proposal: kabar mingguan, MEOW-F-28); **masa pemantauan 6 bulan** sejak serah terima. `status_pemantauan`: `berakhir` > `selesai` (masa lewat) > `terlambat` (lewat jatuh tempo; hari jatuh tempo itu sendiri belum terlambat) > `aktif`. Konstanta ada di `App\Models\PascaAdopsi`.
  - **`GET /api/admin/pasca-adopsi`**: tiap baris = satu adopsi (kucing, kontak adopter untuk admin, pembaruan terakhir, `kondisi_terkini`, `jumlah_update`, `jatuh_tempo`, `terlambat_hari`, `masa_pemantauan_sampai`, `status_pemantauan`). Urutan bawaan `prioritas` (paling terlambat dulu, lalu yang segera jatuh tempo, lalu selesai/berakhir); `sort=terbaru|terlama`; filter `status`, `kondisi`, `q`; paginasi; `meta.jumlah_per_status` untuk lencana tab. Dihitung di aplikasi dari satu kueri (jumlah adopsi kecil).
  - **`GET /api/admin/pasca-adopsi/{id_serah_terima}/riwayat`**: ringkasan pemantauan + riwayat terbaru di atas (tanggal sama: yang dicatat belakangan di atas). Respons `POST`/`PUT` menyertakan ringkasan terbaru (jatuh tempo berikutnya) untuk UI.
  - **Bahan modul 11:** `PemantauanPascaAdopsiService::terlambat()` mengembalikan adopsi yang perlu diingatkan.
  - Cast `tanggal_update` dan `tanggal_serah_terima` diseragamkan ke `date:Y-m-d`.

---

### dev-backend-11: Modul Notifikasi Otomatis & Pengingat Laporan (Email & WhatsApp Integration)

- **Deskripsi & Tujuan:**  
  Mengelola pencatatan dan pengiriman notifikasi otomatis saat terjadi perubahan status pengajuan serta pengingat (*reminder*) berkala kepada adopter untuk mengirim pembaruan kondisi anabul.
- **Aturan Bisnis & Batasan:**  
  - MEOW-BR-12 & MEOW-F-32: Integrasi WhatsApp dibatasi hanya untuk notifikasi dan pengingat satu arah, bukan sistem chat/CRM percakapan penuh.
  - Tabel `notifikasi` menyimpan kanal (Email/WhatsApp), isi pesan, status pengiriman, dan tanggal kirim.
- **Fitur & Endpoint API / Controller:**  
  - `GET /api/admin/notifikasi` (Histori notifikasi; filter `status`, `kanal`, `jenis`, `id_pengajuan`).
  - `POST /api/admin/notifikasi/kirim` (Kirim email yang `PENDING`; dengan `id_notifikasi` untuk satu pesan atau mencoba ulang yang `GAGAL`).
  - `PUT /api/admin/notifikasi/{id}/status` (Admin menandai hasil kirim WhatsApp manual: `TERKIRIM`/`GAGAL`).
  - `POST /api/admin/notifikasi/pengingat` (Picu pengingat kabar kondisi kucing; sama dengan perintah terjadwal).
  - WhatsApp Notification Service (pembentuk tautan terformat `https://wa.me/<nomor>?text=<pesan>`).
  - Laravel Mailable untuk email notifikasi.
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [x] Record notifikasi tersimpan di database dengan status `TERKIRIM` atau `GAGAL`.
  - [x] Tautan WhatsApp ter-generate dengan teks template yang rapi dan memuat nomor registrasi/kode tracking.
- **Target File / Komponen:**  
  - `app/Http/Controllers/NotifikasiController.php`
  - `app/Services/WhatsAppNotificationService.php`, `PengirimanNotifikasiService.php`, `PengingatPascaAdopsiService.php`
  - `app/Mail/NotifikasiMail.php` + `resources/views/emails/notifikasi.blade.php` (nama umum karena dipakai semua jenis notifikasi, bukan hanya status adopsi)
  - `app/Console/Commands/KirimNotifikasi.php` (`notifikasi:kirim`), `IngatkanPascaAdopsi.php` (`notifikasi:ingatkan`); jadwal di `routes/console.php`
  - `app/Models/Notifikasi.php`, `app/Http/Resources/NotifikasiResource.php`, `app/Http/Requests/{FilterNotifikasi,KirimNotifikasi,TandaiNotifikasi}Request.php`
  - Migrasi `2026_11_27_000001_add_pengiriman_columns_to_notifikasi_table.php`
  - `tests/Feature/NotifikasiApiTest.php` (21 tes)
- **Catatan implementasi:**  
  - **Keputusan WhatsApp: tautan `wa.me` manual** (gratis, tanpa nomor khusus, sesuai BR-12 satu arah). Sistem menyiapkan pesan + tautan, admin membukanya, menekan kirim dari WhatsApp-nya sendiri, lalu menandai hasilnya. Bila kelak memakai gateway resmi, cukup tambah pengirim otomatis yang memanggil `PengirimanNotifikasiService::tandaiWhatsApp()`; kontrak API tidak berubah.
  - **Email dikirim sistem**, terpisah dari proses pengajuan (agar pelamar tidak menunggu SMTP dan kegagalan email tidak menggagalkan pengajuan). Modul 07-10 tetap hanya mengantrekan `PENDING`; `notifikasi:kirim` mengirimnya tiap 5 menit. Hasil `TERKIRIM`, atau `GAGAL` dengan `catatan_galat` dan jumlah `percobaan`, dan bisa dicoba ulang. Endpoint kirim tidak pernah 500 karena SMTP bermasalah.
  - **Butuh penjadwal di server:** cron `* * * * * php artisan schedule:run` (jadwal: `notifikasi:ingatkan` 08.00 WIB, `notifikasi:kirim` tiap 5 menit). Tanpa cron, admin tetap bisa memakai `POST /notifikasi/kirim` dan `/notifikasi/pengingat` secara manual. Email memakai pengaturan `MAIL_*` di `.env` (bawaan `log`, tertulis ke `storage/logs/laravel.log`).
  - **Pengingat kabar kondisi** (F-31): adopsi yang masih dipantau diingatkan sejak hari jatuh tempo (pembaruan terakhir + 7 hari), diulang tiap 7 hari, maksimal 4 kali per siklus; setelah itu admin menghubungi adopter langsung. Kunci unik `pengingat:{adopsi}:{jatuh tempo}:{minggu ke-n}` + `kanal` membuat proses aman dijalankan berulang. Setelah admin mencatat kabar baru, jatuh tempo bergeser dan siklus baru dimulai. Adopsi `berakhir`/`selesai` tidak diingatkan.
  - **Token aktivasi akun** pada isi pesan disamarkan (`token=[disamarkan]`) begitu pesan `TERKIRIM`, supaya tautan sekali pakai tidak tersimpan di basis data setelah sampai. Selama belum terkirim, token tetap utuh agar bisa dikirim.
  - `GET /notifikasi` memuat `penerima` dan `tautan_whatsapp` (hanya untuk WhatsApp yang belum terkirim) serta `meta.jumlah_per_status`. Nomor WhatsApp dinormalkan ke `628...`.
  - Pengiriman lewat perintah konsol tidak mencatat audit log (bukan aksi pengguna); aksi lewat API tercatat sebagai `notifikasi`.

---

### dev-backend-12: Modul CMS Artikel Edukasi, Kampanye Kalung, & Publikasi Informasi Publik

- **Deskripsi & Tujuan:**  
  Menyediakan Content Management System (CMS) bagi Admin Jaga Satwa untuk mengelola artikel edukasi satwa, informasi kegiatan, kampanye kalung reflektif, serta penyajian informasi donasi dan tautan Google Form pelaporan resmi.
- **Aturan Bisnis & Batasan:**  
  - MEOW-BR-10: Sistem tidak memproses transaksi pembayaran donasi (hanya menyajikan rekening resmi / QRIS Jaga Satwa).
  - MEOW-BR-11: Sistem tidak membuat ulang form pelaporan kucing liar (mengarahkan langsung ke Google Form eksternal).
  - MEOW-F-34 & 35: Artikel mendukung status publikasi, unggulan (`is_featured`), sematan (`is_pinned`), dan thumbnail.
- **Fitur & Endpoint API / Controller:**  
  - `GET /api/artikel` (Publik: daftar artikel terbit dengan filter kategori).
  - `GET /api/artikel/{slug}` (Publik: baca artikel lengkap).
  - `GET /api/artikel/featured` (Artikel utama untuk banner landing page).
  - `POST /api/admin/artikel` (Admin tulis artikel baru & upload cover/thumbnail).
  - `PUT /api/admin/artikel/{id}` & `DELETE /api/admin/artikel/{id}`.
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [ ] Publik hanya dapat melihat artikel dengan `status_publikasi = PUBLISHED`.
  - [ ] Flag `is_featured` dan `is_pinned` berfungsi benar untuk menyaring artikel di landing page.
- **Target File / Komponen:**  
  - `app/Http/Controllers/ArtikelController.php`
  - `app/Models/Artikel.php`
  - `app/Http/Requests/StoreArtikelRequest.php`

---

### dev-backend-13: Modul Agregasi Statistik Dashboard Admin & Data Titik Peta Persebaran

- **Deskripsi & Tujuan:**  
  Menyediakan endpoint data analitik dan statistik ringkasan untuk Dashboard Admin (Total Kucing, Siap Adopsi, Antrean Review, Feeding Hari Ini, Persentase Sterilisasi, Overdue Reminder Tempo) serta data sebaran geografis kucing di Kampus Ketintang dan Lidah Wetan untuk peta interaktif.
- **Aturan Bisnis & Batasan:**  
  - MEOW-F-38 s/d 40: Menampilkan peta lokasi kucing pada area kampus tanpa mengekspos data pribadi adopter.
  - Optimasi query agregasi database agar waktu respon cepat (MEOW-NF-09).
- **Fitur & Endpoint API / Controller:**  
  - `GET /api/admin/dashboard/stats` (6 metrik statistik utama + status alert operasional).
  - `GET /api/peta/persebaran` (Geo-JSON / titik koordinat persebaran kucing per gedung kampus untuk Leaflet Map).
  - `GET /api/admin/dashboard/antrean-terbaru` (Widget daftar antrean adopsi yang butuh tindakan cepat).
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [ ] Perhitungan metrik akurat secara real-time dari data tabel `kucing`, `pemberian_makan`, dan `pengajuan_adopsi`.
  - [ ] Endpoint peta menyajikan data koordinat `koordinat_x` dan `koordinat_y` dengan format yang siap dirender di frontend.
- **Target File / Komponen:**  
  - `app/Http/Controllers/DashboardController.php`
  - `app/Http/Controllers/PetaController.php`

---

### dev-backend-14: Modul Super Admin (Manajemen Akun Pengurus, Hak Akses, & Log Aktivitas Audit)

- **Deskripsi & Tujuan:**  
  Menyediakan fungsi tata kelola sistem tingkat lanjut khusus bagi Super Admin: pembuatan akun Admin Jaga Satwa baru, reset kredensial, penonaktifan akun, manajemen hak akses, serta penelusuran rekam jejak audit (*Audit Trail Viewer*).
- **Aturan Bisnis & Batasan:**  
  - Dokumen Proposal (Hal. 16, 43): Super Admin menangani aspek teknis sistem dan tidak berwenang mengambil keputusan screening adopsi.
  - MEOW-NF-07: Seluruh histori modifikasi data tersimpan dalam `audit_log` untuk kebutuhan akuntabilitas.
- **Fitur & Endpoint API / Controller:**  
  - `GET /api/superadmin/users` (Daftar seluruh akun pengurus & adopter).
  - `POST /api/superadmin/users` (Super Admin mendaftarkan admin baru).
  - `PUT /api/superadmin/users/{id}/status` (Aktivasi / suspend akun).
  - `GET /api/superadmin/audit-logs` (Viewer rekam jejak audit dengan filter waktu, user, dan nama tabel).
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [ ] Endpoint hanya bisa diakses oleh user dengan role `superadmin`.
  - [ ] Log audit dapat difilter berdasarkan nama tabel dan rentang tanggal dengan performa optimal.
- **Target File / Komponen:**  
  - `app/Http/Controllers/SuperAdminController.php`
  - `app/Http/Controllers/AuditLogController.php`
  - `app/Models/AuditLog.php`

---

## 🚀 Panduan Workflow Git & GitHub

Untuk memudahkan tracking pekerjaan kelompok pada repository:

1. **Membuat Issue GitHub:**
   - Judul Issue: `[dev-backend-XX] Nama Modul` (Contoh: `[dev-backend-01] Inisialisasi Database, Skema Migrasi 14 Tabel, & Seeder Master Data`)
   - Deskripsi: Salin bagian *Deskripsi*, *Tabel Terkait*, *Endpoint*, dan *Acceptance Criteria* dari modul di atas.
2. **Penamaan Branch Git:**
   - Format: `feature/dev-backend-xx-nama-modul`
   - Contoh: `git checkout -b feature/dev-backend-01-database-migrations`
3. **Format Pesan Commit:**
   - Format: `feat(backend): [dev-backend-xx] deskripsi singkat perubahan`
   - Contoh: `git commit -m "feat(backend): [dev-backend-01] create 14 migrations and initial seeders"`
4. **Pull Request (PR):**
   - Judul PR: `feat: [dev-backend-xx] Nama Modul`
   - Hubungkan ke issue terkait: `Closes #1`
