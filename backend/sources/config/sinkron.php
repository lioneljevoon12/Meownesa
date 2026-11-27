<?php

/*
| Sinkronisasi offline untuk PWA (dev-backend-15). Nilai bawaan cocok untuk skala Jaga Satwa (ratusan kucing).
*/
return [
    // Jumlah baris maksimal per koleksi dalam satu penarikan. Bila lebih, respons menandai lengkap = false
    // dan HP menarik lagi dari kursor yang diberikan.
    'batas_per_koleksi' => (int) env('SINKRON_BATAS', 1000),

    // Kursor dimundurkan sekian detik agar baris yang baru di-commit (tetapi bertanda waktu sedikit lebih awal)
    // tidak terlewat. HP menggabungkan hasil berdasarkan id sehingga baris yang terbaca dua kali tidak masalah.
    'tumpang_tindih_detik' => (int) env('SINKRON_TUMPANG_TINDIH', 5),

    // Lama catatan penghapusan disimpan. HP yang terakhir sinkron lebih lama dari ini harus menarik ulang semuanya.
    'masa_penghapusan_hari' => (int) env('SINKRON_MASA_PENGHAPUSAN', 60),

    // Jendela hari feeding yang ikut pada penarikan penuh.
    'hari_feeding_bawaan' => 14,
    'hari_feeding_maks'   => 60,
];
