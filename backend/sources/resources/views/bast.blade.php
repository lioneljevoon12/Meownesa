<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title>Berita Acara Serah Terima {{ $nomor }}</title>
    <style>
        @page { size: A4; margin: 22mm 20mm; }
        body { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.5; color: #000; margin: 0 auto; max-width: 170mm; }
        h1 { text-align: center; font-size: 15pt; margin: 0; text-transform: uppercase; }
        .sub { text-align: center; margin: 2px 0 18px; }
        table.data { border-collapse: collapse; width: 100%; margin: 6px 0 14px; }
        table.data td { padding: 2px 6px; vertical-align: top; }
        table.data td:first-child { width: 38%; }
        ol { margin: 6px 0 14px; padding-left: 20px; }
        .tanda-tangan { display: flex; justify-content: space-between; margin-top: 36px; text-align: center; }
        .tanda-tangan div { width: 45%; }
        .garis { margin-top: 70px; border-top: 1px solid #000; padding-top: 2px; }
        .catatan { margin-top: 28px; font-size: 9.5pt; color: #444; border-top: 1px dashed #999; padding-top: 6px; }
        @media screen { body { padding: 20px; } }
    </style>
</head>
<body>
    <h1>Berita Acara Serah Terima Kucing</h1>
    <p class="sub">Jaga Satwa Universitas Negeri Surabaya &middot; Nomor Pengajuan {{ $nomor }}</p>

    <p>Pada hari ini, {{ $tanggal }}, telah dilakukan serah terima seekor kucing dari pengurus Jaga Satwa UNESA kepada calon pemelihara dengan data sebagai berikut.</p>

    <strong>A. Kucing</strong>
    <table class="data">
        <tr><td>Kode / Nama</td><td>: {{ $kucing->kode_kucing }} / {{ $kucing->nama_kucing }}</td></tr>
        <tr><td>Jenis kelamin</td><td>: {{ $kucing->jenis_kelamin }}</td></tr>
        <tr><td>Kelompok usia</td><td>: {{ $kucing->kelompok_usia ?? '-' }}</td></tr>
        <tr><td>Status sterilisasi</td><td>: {{ $kucing->status_steril }}</td></tr>
        <tr><td>Lokasi asal</td><td>: {{ $lokasi }}</td></tr>
        <tr><td>Ciri-ciri</td><td>: {{ $kucing->deskripsi ?: '-' }}</td></tr>
    </table>

    <strong>B. Pemelihara (adopter)</strong>
    <table class="data">
        <tr><td>Nama</td><td>: {{ $pengajuan->nama_pemohon }}</td></tr>
        <tr><td>WhatsApp</td><td>: {{ $pengajuan->nomor_whatsapp }}</td></tr>
        <tr><td>Email</td><td>: {{ $pengajuan->email }}</td></tr>
        <tr><td>Alamat</td><td>: {{ $pengajuan->alamat }}</td></tr>
    </table>

    <strong>C. Kesediaan pemelihara</strong>
    <ol>
        <li>Merawat kucing dengan penuh kasih sayang, menyediakan makanan, air bersih, dan tempat tinggal yang aman (dipelihara di dalam rumah).</li>
        <li>Membawa kucing ke dokter hewan bila sakit dan menjaga kesehatannya, termasuk vaksinasi dan sterilisasi sesuai anjuran.</li>
        <li>Memberi kabar kondisi kucing kepada pengurus Jaga Satwa secara berkala (mingguan) selama masa pemantauan 6 bulan sejak serah terima.</li>
        <li>Tidak menjual, memindahtangankan, menelantarkan, atau melepasliarkan kucing.</li>
        <li>Bersedia dihubungi dan dikunjungi pengurus untuk memeriksa kondisi kucing selama masa pemantauan.</li>
        <li>Menghubungi pengurus Jaga Satwa untuk mengembalikan kucing apabila di kemudian hari tidak sanggup merawatnya.</li>
    </ol>

    <p>Demikian berita acara ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>

    <div class="tanda-tangan">
        <div>Pemelihara,<div class="garis">{{ $pengajuan->nama_pemohon }}</div></div>
        <div>Pengurus Jaga Satwa UNESA,<div class="garis">{{ $petugas }}</div></div>
    </div>

    <p class="catatan">
        Dokumen ini dibuat otomatis oleh MeowNesa dari data serah terima #{{ $serahTerima->id_serah_terima }} dan memuat data pribadi adopter:
        simpan dan bagikan seperlunya. Isi butir kesediaan di atas adalah templat; sesuaikan dengan ketentuan resmi Jaga Satwa UNESA sebelum dipakai.
    </p>
</body>
</html>
