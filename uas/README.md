# MeowNesa — Versi UAS (PHP native + jQuery + Bootstrap)

Versi untuk mata kuliah Pemrograman Web. Tanpa framework PHP, tanpa Composer.
Semua file versi UAS ada di dalam folder `uas/` ini (termasuk database).
Versi product-ready untuk Jaga Satwa (Laravel + Next.js) memakai struktur repo biasa: `../backend` dan `../frontend`.

## Stack
PHP 8.2 (PDO) · MySQL/MariaDB · jQuery 3.7 + Ajax · Bootstrap 5.3 · Bootstrap Icons

## Menjalankan (XAMPP, Windows)
1. Nyalakan **MySQL** di XAMPP.
2. `copy .env.example .env` (ubah kredensial DB bila perlu).
3. Pasang database + data contoh:
   ```powershell
   php bin/install.php --fresh
   ```
4. Jalankan server:
   ```powershell
   php -S localhost:8080 -t public public/index.php
   ```
5. Buka http://localhost:8080 — login admin di `/login`.

Akun seed (password dari `SEED_PASSWORD`, default `admin123`, **wajib diganti di production**):
`superadmin@unesa.ac.id` · `admin@jagasatwa.unesa.ac.id`

Deploy ke hosting PHP biasa: arahkan document root ke folder `public/`
(atau upload isi `public/` ke `htdocs` dan `app/`, `config/`, `database` di luar web root).

## Struktur
```
public/      document root: index.php (front controller), assets/, uploads/
app/Core/    Router, Db (PDO), Auth, Csrf, Audit, View, Guard
app/Controllers/  controller per modul
app/views/   template PHP + layouts (public, admin, plain)
config/      konfigurasi (.env)
bin/install.php   pasang skema + seed
database/schema.sql, seed.sql   skema 16 tabel + data contoh (mandiri di dalam uas/)
```

## Keamanan yang sudah ada
PDO prepared statements · `password_hash` bcrypt cost 12 · session HttpOnly+SameSite ·
CSRF token di semua POST (form & Ajax) · output di-escape (`e()`) · role guard ·
penguncian login 60 dtk setelah 5 gagal · audit log (login/logout) ·
data non-publik tidak diekspos di katalog (hanya status Tersedia/Terdata).

## Status modul
- [x] Fondasi: router, DB, auth + role, layout Bootstrap, schema + seed
- [x] Publik: beranda, katalog (filter Ajax), profil kucing + riwayat kesehatan
- [x] Admin: dashboard statistik
- [ ] Form pengajuan adopsi + tracking
- [ ] Admin: kucing, feeding, kesehatan, screening, serah terima, pasca-adopsi, artikel
- [ ] Super Admin: akun, audit log
