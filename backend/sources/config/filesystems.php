<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Foto kucing: berkas bawaan seeder ada di public/images/kucing, unggahan admin di subfolder upload/.
        // Disimpan langsung di folder public agar tidak butuh `php artisan storage:link` (bermasalah di Windows).
        'kucing_foto' => [
            'driver' => 'local',
            'root' => public_path('images/kucing'),
            'url' => env('APP_URL').'/images/kucing',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Lampiran pengajuan adopsi (KTP, foto rumah): data pribadi, SELALU privat. Tidak ada `url` dan
        // tidak di bawah public/, jadi tidak dapat dibuka lewat alamat web; hanya dibaca server untuk admin.
        // dev-backend-12: thumbnail artikel. Publik (tampil di halaman artikel); unggahan admin ada di upload/.
        'artikel_thumbnail' => [
            'driver' => 'local',
            'root' => public_path('images/artikel'),
            'url' => env('APP_URL').'/images/artikel',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'lampiran' => [
            'driver' => 'local',
            'root' => storage_path('app/private/lampiran'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        // Berkas serah terima (BAST/surat perjanjian dan foto penyerahan): memuat identitas adopter, SELALU privat.
        'serah_terima' => [
            'driver' => 'local',
            'root' => storage_path('app/private/serah_terima'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
