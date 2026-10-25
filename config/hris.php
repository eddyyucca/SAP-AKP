<?php

return [
    // Alamat dasar HRIS dan token layanannya (HRIS_API_TOKEN di .env HRIS). Kosong = pencarian karyawan dimatikan,
    // formulir tetap bisa diisi manual.
    'url' => env('HRIS_API_URL', 'http://localhost:8001'),
    'token' => env('HRIS_API_TOKEN'),

    // Lama daftar karyawan disimpan sebelum diambil ulang dari HRIS (detik).
    'cache_seconds' => (int) env('HRIS_CACHE_SECONDS', 600),
];
