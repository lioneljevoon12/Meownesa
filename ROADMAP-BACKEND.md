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
| **dev-backend-06** | Modul Feeding Log (Pencatatan Pemberian Makan Harian Sesuai Spreadsheet) | MEOW-F-07 s/d 09, DFD 2.0 | P1 (Daily Operation) |
| **dev-backend-07** | Modul Pengajuan Adopsi Publik & Generator Kode Tracking Unik (Tanpa Akun) | MEOW-F-14, 15, 20, MEOW-BR-03, 04 | P1 (Adoption Flow) |
| **dev-backend-08** | Modul Screening & Pemeriksaan Calon Adopter (Internal Admin) | MEOW-F-16 s/d 19, MEOW-BR-05, 08 | P1 (Screening Review) |
| **dev-backend-09** | Modul Serah Terima Adopsi (BAST) & Pembuatan Akun Adopter | MEOW-F-21 s/d 25, MEOW-BR-06, 07 | P1 (Handover Legal) |
| **dev-backend-10** | Modul Pemantauan Pasca-Adopsi & Riwayat Kondisi Berkala | MEOW-F-26 s/d 29, MEOW-BR-13 | P2 (Monitoring) |
| **dev-backend-11** | Modul Notifikasi Otomatis & Pengingat Laporan (Email & WhatsApp Integration) | MEOW-F-30 s/d 32, MEOW-BR-12 | P2 (Notification) |
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
  - [ ] Admin dapat mencatat feeding untuk satu titik lokasi atau kucing spesifik.
  - [ ] Menampilkan riwayat log feeding dengan filtering tanggal yang responsif.
- **Target File / Komponen:**  
  - `app/Http/Controllers/PemberianMakanController.php`
  - `app/Models/PemberianMakan.php`

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
  - [ ] Sistem berhasil membuat record di `pengajuan_adopsi` dan relasi file di `lampiran_pengajuan`.
  - [ ] Mengembalikan kode tracking unik yang langsung bisa dicek di halaman tracking.
  - [ ] Status awal pengajuan otomatis `SUBMITTED / MENUNGGU_REVIEW`.
- **Target File / Komponen:**  
  - `app/Http/Controllers/PengajuanAdopsiController.php`
  - `app/Models/PengajuanAdopsi.php`, `app/Models/LampiranPengajuan.php`
  - `app/Services/TrackingCodeGenerator.php`

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
  - [ ] Setiap screening mencatat `id_user` admin penanggung jawab verifikasi.
  - [ ] Data kriteria evaluasi tersimpan utuh di tabel `screening_adopsi`.
  - [ ] Perubahan status memicu pembuatan record notifikasi untuk pelamar.
- **Target File / Komponen:**  
  - `app/Http/Controllers/ScreeningAdopsiController.php`
  - `app/Models/ScreeningAdopsi.php`
  - `app/Http/Requests/StoreScreeningRequest.php`

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
  - [ ] Status kucing di tabel `kucing` otomatis terupdate menjadi `diadopsi`.
  - [ ] Record baru terbentuk di tabel `serah_terima` dan tabel `adopter`.
  - [ ] Akun `user` adopter terbuat dengan kredensial aman untuk menerima pemberitahuan.
- **Target File / Komponen:**  
  - `app/Http/Controllers/SerahTerimaController.php`
  - `app/Models/SerahTerima.php`, `app/Models/Adopter.php`

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
  - [ ] Riwayat kondisi kucing pasca-adopsi tersimpan runtut berdasarkan `tanggal_update`.
  - [ ] Sistem mampu mengidentifikasi status kondisi terkini kucing teradopsi.
- **Target File / Komponen:**  
  - `app/Http/Controllers/PascaAdopsiController.php`
  - `app/Models/PascaAdopsi.php`

---

### dev-backend-11: Modul Notifikasi Otomatis & Pengingat Laporan (Email & WhatsApp Integration)

- **Deskripsi & Tujuan:**  
  Mengelola pencatatan dan pengiriman notifikasi otomatis saat terjadi perubahan status pengajuan serta pengingat (*reminder*) berkala kepada adopter untuk mengirim pembaruan kondisi anabul.
- **Aturan Bisnis & Batasan:**  
  - MEOW-BR-12 & MEOW-F-32: Integrasi WhatsApp dibatasi hanya untuk notifikasi dan pengingat satu arah, bukan sistem chat/CRM percakapan penuh.
  - Tabel `notifikasi` menyimpan kanal (Email/WhatsApp), isi pesan, status pengiriman, dan tanggal kirim.
- **Fitur & Endpoint API / Controller:**  
  - `POST /api/admin/notifikasi/send` (Kirim pesan notifikasi / trigger reminder).
  - `GET /api/admin/notifikasi` (Histori log notifikasi terkirim).
  - WhatsApp Notification Service (Helper pembentuk URL pesan terformat `https://wa.me/` atau integrasi gateway API).
  - Laravel Mail Mailable untuk notifikasi email konfirmasi adopsi.
- **Kriteria Keberhasilan (Acceptance Criteria):**  
  - [ ] Record notifikasi tersimpan di database dengan status `TERKIRIM` atau `GAGAL`.
  - [ ] Tautan WhatsApp ter-generate dengan teks template yang rapi dan memuat nomor registrasi/kode tracking.
- **Target File / Komponen:**  
  - `app/Http/Controllers/NotifikasiController.php`
  - `app/Services/WhatsAppNotificationService.php`
  - `app/Mail/AdoptionStatusMail.php`
  - `app/Models/Notifikasi.php`

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
