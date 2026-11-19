# UTS Pemrograman Web: Portal Admin MeowNesa

## Identitas
- Nama: Lionel Jevon Chrismana Putra
- NIM: 25091397019
- Program Studi: D4 Manajemen Informatika, Universitas Negeri Surabaya
- Mata Kuliah: Pemrograman Web 
- Dosen Pengampu: Ari Kurniawan, S.Kom., M.T.
- Kelompok: A07
- Folder repository: PWUTS_A07_25091397019

## Tentang Proyek
MeowNesa adalah sistem informasi untuk membantu komunitas Jaga Satwa UNESA mengelola data kucing kampus dan proses adopsinya. Topik ini berasal dari proposal kelompok kami.

Di UTS ini saya mengerjakan **modul admin**, yaitu halaman yang dipakai pengurus dan relawan untuk memantau populasi kucing, mencatat pemberian makan, mengelola rekam medis, memproses pengajuan adopsi, memantau kucing setelah diadopsi, dan menulis artikel edukasi. Anggota kelompok lain mengerjakan modul yang berbeda, jadi tidak ada halaman yang sama (sesuai ketentuan 2.d).

## Pemenuhan Ketentuan UTS

**2.a Homepage / layout utama (`index.html`)**
`index.html` adalah layout utama yang berisi sidebar, topbar, dan area konten. Tampilannya responsive memakai CSS Flexbox, Grid, dan media query, sehingga tetap nyaman di layar desktop maupun HP. Klik menu di sidebar memuat halaman ke area konten menggunakan Ajax jQuery, jadi halaman tidak di-refresh penuh.

**2.b Halaman konten interaktif**
Halaman **Data Populasi Kucing** (`cats.html`). Fitur interaktifnya:
- Filter berdasarkan kampus, status adopsi, dan jenis kelamin.
- Pencarian langsung (real-time) berdasarkan nama kucing atau kode kalung.
- Modal detail profil kucing.

Halaman ini dimuat ke dalam layout utama lewat Ajax.

**2.c Halaman form dengan validasi**
Halaman **Rekam Medis** (`health.html`) berisi form pencatatan tindakan medis baru. Sebelum data disimpan, jQuery memeriksa kolom wajib, panjang minimal, dan format isian. Kalau ada yang salah, pesan error muncul di bawah kolom yang bermasalah dan data tidak diproses. Halaman ini juga dimuat ke layout utama lewat Ajax.



## Daftar Halaman
- **Dashboard**: ringkasan angka penting (total kucing, siap adopsi, antrean adopsi, feeding hari ini, status sterilisasi) dan pengingat yang perlu ditindaklanjuti.
- **Data Populasi Kucing** (`cats.html`): tabel kucing dari kampus Ketintang dan Lidah Wetan, lengkap dengan filter, pencarian, edit data, dan detail profil.
- **Feeding Log** (`feeding.html`): tabel jadwal pemberian makan per zona fakultas dan per tanggal, dengan status Done atau Belum.
- **Rekam Medis** (`health.html`): riwayat sterilisasi, vaksinasi, dan perawatan, plus form pencatatan tindakan baru.
- **Screening Adopsi** (`adoptions.html`): antrean pengajuan adopsi dengan tahapan Review Berkas, Wawancara, Disetujui, dan Siap Antar.
- **Monitoring Pasca-Adopsi** (`monitoring.html`): memantau laporan kondisi mingguan dari adopter dan menandai yang terlambat.
- **CMS Artikel** (`cms.html`): tambah, edit, lihat, dan hapus artikel edukasi.

## Teknologi
- HTML5, CSS3, JavaScript, dan jQuery 3.7.1.
- Font: Baloo 2 dan Plus Jakarta