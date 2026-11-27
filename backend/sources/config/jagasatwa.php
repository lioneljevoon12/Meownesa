<?php

/*
| Informasi resmi Jaga Satwa UNESA yang ditampilkan di situs publik (dev-backend-12).
| Sengaja berada di .env, bukan di basis data/CMS: rekening dan QRIS donasi tidak boleh bisa diubah
| lewat akun admin yang bocor (MEOW-BR-10). Kosongkan nilainya bila belum ada.
*/
return [
    'donasi' => [
        'bank'           => env('DONASI_BANK'),
        'nomor_rekening' => env('DONASI_NOMOR_REKENING'),
        'atas_nama'      => env('DONASI_ATAS_NAMA'),
        'qris_url'       => env('DONASI_QRIS_URL'),
    ],

    'form_pelaporan_url' => env('LAPOR_FORM_URL'),

    'kontak' => [
        'whatsapp'  => env('KONTAK_WHATSAPP'),
        'instagram' => env('KONTAK_INSTAGRAM'),
    ],
];
