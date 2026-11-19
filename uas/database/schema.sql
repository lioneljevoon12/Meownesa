-- ============================================================================
-- MeowNesa — Skema Basis Data (16 tabel, Bab 2.5.2 Proposal)
-- Khusus versi UAS (PHP native). Versi product-ready memakai migrasi Laravel
-- sendiri di backend/sources/database/migrations (struktur tabel dibuat setara).
-- Target: MySQL 8 / MariaDB 10.4+, InnoDB, utf8mb4.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log, notifikasi, artikel, pasca_adopsi, serah_terima,
    lampiran_pengajuan, screening_adopsi, pengajuan_adopsi, adopter, riwayat_kesehatan,
    pemberian_makan, foto_kucing, kucing, lokasi, kampus, users;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. users (proposal: tabel "user") ------------------------------------------
CREATE TABLE users (
    id_user        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama           VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    role           VARCHAR(30)  NOT NULL DEFAULT 'admin',   -- superadmin | admin | adopter
    status_akun    VARCHAR(20)  NOT NULL DEFAULT 'aktif',   -- aktif | nonaktif
    remember_token VARCHAR(100) NULL,
    created_at     TIMESTAMP NULL DEFAULT NULL,
    updated_at     TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_user),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. kampus -------------------------------------------------------------------
CREATE TABLE kampus (
    id_kampus   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama_kampus VARCHAR(100) NOT NULL,
    created_at  TIMESTAMP NULL DEFAULT NULL,
    updated_at  TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_kampus),
    UNIQUE KEY kampus_nama_unique (nama_kampus)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. lokasi (koordinat_x = latitude, koordinat_y = longitude) -----------------
CREATE TABLE lokasi (
    id_lokasi   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_kampus   BIGINT UNSIGNED NOT NULL,
    nama_lokasi VARCHAR(100) NOT NULL,
    koordinat_x DECIMAL(11,8) NOT NULL DEFAULT 0,
    koordinat_y DECIMAL(11,8) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NULL DEFAULT NULL,
    updated_at  TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_lokasi),
    KEY lokasi_kampus_idx (id_kampus),
    CONSTRAINT lokasi_kampus_fk FOREIGN KEY (id_kampus) REFERENCES kampus (id_kampus) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. kucing -------------------------------------------------------------------
CREATE TABLE kucing (
    id_kucing     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_lokasi     BIGINT UNSIGNED NOT NULL,
    kode_kucing   VARCHAR(30)  NOT NULL,
    nama_kucing   VARCHAR(100) NOT NULL,
    jenis_kelamin VARCHAR(20)  NOT NULL,                       -- Jantan | Betina
    kelompok_usia VARCHAR(30)  NULL,                           -- Kitten | Remaja | Dewasa
    status_steril VARCHAR(20)  NOT NULL DEFAULT 'Belum',       -- Sudah | Belum
    status_kucing VARCHAR(30)  NOT NULL DEFAULT 'Terdata',     -- Terdata | Tersedia | Proses Screening | Diadopsi | Almarhum
    deskripsi     TEXT NULL,
    created_at    TIMESTAMP NULL DEFAULT NULL,
    updated_at    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_kucing),
    UNIQUE KEY kucing_kode_unique (kode_kucing),
    KEY kucing_lokasi_idx (id_lokasi),
    KEY kucing_status_idx (status_kucing),
    CONSTRAINT kucing_lokasi_fk FOREIGN KEY (id_lokasi) REFERENCES lokasi (id_lokasi) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. foto_kucing --------------------------------------------------------------
CREATE TABLE foto_kucing (
    id_foto_kucing BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_kucing      BIGINT UNSIGNED NOT NULL,
    url_foto       VARCHAR(255) NOT NULL,
    is_utama       TINYINT(1) NOT NULL DEFAULT 0,
    created_at     TIMESTAMP NULL DEFAULT NULL,
    updated_at     TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_foto_kucing),
    KEY foto_kucing_idx (id_kucing),
    CONSTRAINT foto_kucing_fk FOREIGN KEY (id_kucing) REFERENCES kucing (id_kucing) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. pemberian_makan (id_kucing boleh NULL: feeding per titik lokasi) ---------
CREATE TABLE pemberian_makan (
    id_pemberian     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_kucing        BIGINT UNSIGNED NULL,
    id_lokasi        BIGINT UNSIGNED NOT NULL,
    tanggal          DATE NOT NULL,
    status_pemberian VARCHAR(20) NOT NULL DEFAULT 'Tuntas',    -- Tuntas | Terjadwal
    catatan          TEXT NULL,
    created_at       TIMESTAMP NULL DEFAULT NULL,
    updated_at       TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_pemberian),
    KEY makan_tanggal_idx (tanggal),
    KEY makan_kucing_idx (id_kucing),
    KEY makan_lokasi_idx (id_lokasi),
    CONSTRAINT makan_kucing_fk FOREIGN KEY (id_kucing) REFERENCES kucing (id_kucing) ON DELETE SET NULL,
    CONSTRAINT makan_lokasi_fk FOREIGN KEY (id_lokasi) REFERENCES lokasi (id_lokasi) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. riwayat_kesehatan --------------------------------------------------------
CREATE TABLE riwayat_kesehatan (
    id_kesehatan   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_kucing      BIGINT UNSIGNED NOT NULL,
    jenis_tindakan VARCHAR(50) NOT NULL,
    tanggal        DATE NOT NULL,
    catatan        TEXT NULL,
    created_at     TIMESTAMP NULL DEFAULT NULL,
    updated_at     TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_kesehatan),
    KEY kesehatan_kucing_idx (id_kucing, tanggal),
    CONSTRAINT kesehatan_kucing_fk FOREIGN KEY (id_kucing) REFERENCES kucing (id_kucing) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. adopter ------------------------------------------------------------------
CREATE TABLE adopter (
    id_adopter              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_user                 BIGINT UNSIGNED NOT NULL,
    tanggal_menjadi_adopter DATE NOT NULL,
    status_adopter          VARCHAR(20) NOT NULL DEFAULT 'aktif',
    created_at              TIMESTAMP NULL DEFAULT NULL,
    updated_at              TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_adopter),
    UNIQUE KEY adopter_user_unique (id_user),
    CONSTRAINT adopter_user_fk FOREIGN KEY (id_user) REFERENCES users (id_user) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. pengajuan_adopsi ---------------------------------------------------------
CREATE TABLE pengajuan_adopsi (
    id_pengajuan      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_kucing         BIGINT UNSIGNED NOT NULL,
    id_adopter        BIGINT UNSIGNED NULL,
    nomor_pengajuan   VARCHAR(30)  NOT NULL,
    kode_tracking     VARCHAR(50)  NOT NULL,
    nama_pemohon      VARCHAR(100) NOT NULL,
    nomor_whatsapp    VARCHAR(20)  NOT NULL,
    email             VARCHAR(150) NOT NULL,
    alamat            TEXT NOT NULL,
    status_pengajuan  VARCHAR(30)  NOT NULL DEFAULT 'MENUNGGU', -- MENUNGGU | SCREENING | DISETUJUI | DITOLAK | SELESAI
    tanggal_pengajuan DATE NOT NULL,
    created_at        TIMESTAMP NULL DEFAULT NULL,
    updated_at        TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_pengajuan),
    UNIQUE KEY pengajuan_nomor_unique (nomor_pengajuan),
    UNIQUE KEY pengajuan_tracking_unique (kode_tracking),
    KEY pengajuan_kucing_idx (id_kucing),
    KEY pengajuan_status_idx (status_pengajuan),
    CONSTRAINT pengajuan_kucing_fk  FOREIGN KEY (id_kucing)  REFERENCES kucing (id_kucing)   ON DELETE CASCADE,
    CONSTRAINT pengajuan_adopter_fk FOREIGN KEY (id_adopter) REFERENCES adopter (id_adopter) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. screening_adopsi --------------------------------------------------------
CREATE TABLE screening_adopsi (
    id_screening        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pengajuan        BIGINT UNSIGNED NOT NULL,
    id_user             BIGINT UNSIGNED NOT NULL,
    kelayakan_hunian    TEXT NULL,
    kestabilan_ekonomi  TEXT NULL,
    pemahaman_perawatan TEXT NULL,
    sikap_proklinik     TEXT NULL,
    hasil_screening     VARCHAR(30) NOT NULL,                  -- Lolos | Ragu | Tidak Lolos
    tanggal_screening   DATE NOT NULL,
    created_at          TIMESTAMP NULL DEFAULT NULL,
    updated_at          TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_screening),
    UNIQUE KEY screening_pengajuan_unique (id_pengajuan),
    KEY screening_user_idx (id_user),
    CONSTRAINT screening_pengajuan_fk FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_adopsi (id_pengajuan) ON DELETE CASCADE,
    CONSTRAINT screening_user_fk      FOREIGN KEY (id_user)      REFERENCES users (id_user)                  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. lampiran_pengajuan ------------------------------------------------------
CREATE TABLE lampiran_pengajuan (
    id_lampiran    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pengajuan   BIGINT UNSIGNED NOT NULL,
    jenis_lampiran VARCHAR(30)  NOT NULL,                      -- KTP | Foto Rumah | Dokumen Pendukung
    url_file       VARCHAR(255) NOT NULL,
    created_at     TIMESTAMP NULL DEFAULT NULL,
    updated_at     TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_lampiran),
    KEY lampiran_pengajuan_idx (id_pengajuan),
    CONSTRAINT lampiran_pengajuan_fk FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_adopsi (id_pengajuan) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. serah_terima ------------------------------------------------------------
CREATE TABLE serah_terima (
    id_serah_terima      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pengajuan         BIGINT UNSIGNED NOT NULL,
    tanggal_serah_terima DATE NOT NULL,
    status_serah_terima  VARCHAR(30) NOT NULL DEFAULT 'Selesai',
    dokumen_perjanjian   VARCHAR(255) NULL,
    foto_serah_terima    VARCHAR(255) NULL,
    created_at           TIMESTAMP NULL DEFAULT NULL,
    updated_at           TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_serah_terima),
    UNIQUE KEY serah_terima_pengajuan_unique (id_pengajuan),
    CONSTRAINT serah_terima_pengajuan_fk FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_adopsi (id_pengajuan) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. pasca_adopsi ------------------------------------------------------------
CREATE TABLE pasca_adopsi (
    id_pasca_adopsi BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_serah_terima BIGINT UNSIGNED NOT NULL,
    tanggal_update  DATE NOT NULL,
    status_kondisi  VARCHAR(30) NOT NULL,                      -- Sehat | Perawatan | Sakit
    catatan         TEXT NULL,
    created_at      TIMESTAMP NULL DEFAULT NULL,
    updated_at      TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_pasca_adopsi),
    KEY pasca_serah_terima_idx (id_serah_terima, tanggal_update),
    CONSTRAINT pasca_serah_terima_fk FOREIGN KEY (id_serah_terima) REFERENCES serah_terima (id_serah_terima) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. artikel -----------------------------------------------------------------
CREATE TABLE artikel (
    id_artikel          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_user             BIGINT UNSIGNED NOT NULL,
    judul               VARCHAR(200) NOT NULL,
    slug                VARCHAR(220) NOT NULL,
    kategori            VARCHAR(50)  NOT NULL,
    isi                 LONGTEXT NOT NULL,
    thumbnail_url       VARCHAR(255) NULL,
    status_publikasi    VARCHAR(20) NOT NULL DEFAULT 'DRAFT',  -- DRAFT | PUBLISHED | ARCHIVED
    is_featured         TINYINT(1) NOT NULL DEFAULT 0,
    is_pinned           TINYINT(1) NOT NULL DEFAULT 0,
    tanggal_dibuat      DATE NOT NULL,
    tanggal_diterbitkan DATE NULL,
    created_at          TIMESTAMP NULL DEFAULT NULL,
    updated_at          TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_artikel),
    UNIQUE KEY artikel_slug_unique (slug),
    KEY artikel_status_idx (status_publikasi),
    KEY artikel_user_idx (id_user),
    CONSTRAINT artikel_user_fk FOREIGN KEY (id_user) REFERENCES users (id_user) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. notifikasi --------------------------------------------------------------
CREATE TABLE notifikasi (
    id_notifikasi     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pengajuan      BIGINT UNSIGNED NULL,
    id_user           BIGINT UNSIGNED NULL,
    jenis_notifikasi  VARCHAR(50) NOT NULL,
    kanal             VARCHAR(20) NOT NULL,                    -- WhatsApp | Email
    isi_pesan         TEXT NOT NULL,
    status_pengiriman VARCHAR(20) NOT NULL DEFAULT 'PENDING',  -- PENDING | TERKIRIM | GAGAL
    tanggal_dikirim   DATE NULL,
    created_at        TIMESTAMP NULL DEFAULT NULL,
    updated_at        TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_notifikasi),
    KEY notifikasi_pengajuan_idx (id_pengajuan),
    KEY notifikasi_user_idx (id_user),
    CONSTRAINT notifikasi_pengajuan_fk FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_adopsi (id_pengajuan) ON DELETE SET NULL,
    CONSTRAINT notifikasi_user_fk      FOREIGN KEY (id_user)      REFERENCES users (id_user)                  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. audit_log ---------------------------------------------------------------
CREATE TABLE audit_log (
    id_audit        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_user         BIGINT UNSIGNED NULL,
    aktivitas       VARCHAR(100) NOT NULL,
    nama_tabel      VARCHAR(50)  NULL,
    id_data         BIGINT UNSIGNED NULL,
    waktu_aktivitas DATETIME NOT NULL,
    created_at      TIMESTAMP NULL DEFAULT NULL,
    updated_at      TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_audit),
    KEY audit_user_idx (id_user),
    KEY audit_waktu_idx (waktu_aktivitas),
    KEY audit_tabel_idx (nama_tabel),
    CONSTRAINT audit_user_fk FOREIGN KEY (id_user) REFERENCES users (id_user) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
