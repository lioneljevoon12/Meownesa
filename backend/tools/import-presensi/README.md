# Impor data aktual dari spreadsheet presensi Jaga Satwa

Skrip ini membangkitkan ulang `backend/sources/database/seeders/data/kucing_aktual.json`
dan foto `backend/sources/public/images/kucing/*.jpg` dari spreadsheet presensi.
Jalankan lagi bila spreadsheet diperbarui (kucing baru, status berubah, foto baru).

## Struktur spreadsheet yang dibaca
- 1 sheet = 1 titik lokasi feeding (sheet berawalan `Copy of` diabaikan).
- Baris 1 = nama kucing (kolom B, D, F, ...). Penanda dalam kurung: `(HILANG)`, `(ADOPTED)`; emoji 🕊️ = almarhum.
- Baris 2, kolom kanan nama = deskripsi bernomor: `1. Betina/Jantan (catatan)`, `2. Steril/Belum Steril`, `3...` ciri.
- Gambar tertanam di kolom kucing dipakai sebagai foto bila belum ada di `documentations/sources-images/foto-kucing`.

## Menjalankan
Butuh Python 3 dengan `openpyxl` dan `Pillow`. Taruh salinan spreadsheet di folder terpisah.

```powershell
python -I extract.py <Presensi Ketintang.xlsx> <PRESENSI LIDAH WETAN.xlsx> ..\..\..\documentations\sources-images\foto-kucing roster.json
python -I gen_seed.py roster.json <Presensi Ketintang.xlsx> <PRESENSI LIDAH WETAN.xlsx> ..\..\..\documentations\sources-images\foto-kucing ..\..\sources
```

`extract.py` mencetak ringkasan per lokasi (cek kolom `STERIL?`/`GENDER?` dan pencocokan foto).
Foto `foto-kucing/KTT-xx.jpg` dicocokkan ke kucing secara visual (thumbnail 32×32), bukan berdasarkan nama file.

## Aturan pembersihan
- Entri tanpa deskripsi (mis. `MESIN 1`, `ELEKTRO 6`, angka `1.0`) **tidak** dimasukkan — dilaporkan di `dikecualikan`.
- Steril tidak tercantum → `Tidak Diketahui` (tidak ditebak).
- Catatan dalam kurung yang menunjuk tempat tinggal orang (mis. kos) **dibuang** dari data publik.
- Kode kucing: `KTT-001`… / `LWT-001`… berurutan per kampus (urutan sheet lalu kolom).
- Koordinat lokasi = titik tengah kampus (spreadsheet tidak memuat koordinat) — koreksi lewat API admin.
