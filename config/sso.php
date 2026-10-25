<?php

/*
 * Login terpusat lewat AKPSatu (Portal). Nilai PORTAL_* diisi di .env.
 *
 * Aplikasi ini tidak punya akun sendiri: identitas dan level datang dari token Portal.
 * Kode level (kiri) dipakai di routes/web.php: ->middleware('portal.role:admin,user').
 * Jangan ubah kode setelah ada pengguna yang memakainya. Portal membacanya otomatis (Modul > Atur > Deteksi level).
 *
 * Urutan: paling berwenang dulu. Hak tiap level dijelaskan di README dan halaman Standar Pelaporan.
 */
return [
    'portal_url' => env('PORTAL_URL', 'http://localhost:8000'),

    'app_code' => env('PORTAL_APP_CODE'),

    'secret' => env('PORTAL_SSO_SECRET'),

    'leeway' => 5,

    'max_session_minutes' => (int) env('PORTAL_MAX_SESSION_MINUTES', 480),

    // Semua halaman wajib login Portal; hanya /up (cek kesehatan Docker) yang terbuka.
    'protect_all' => true,

    'except' => ['up'],

    'roles' => [
        'admin' => ['Administrator', 'Mengelola semua data, lokasi, jam kerja, dan pengaturan SSAP Safety.'],
        'hse' => ['Petugas HSE', 'Menerima, menginvestigasi, dan menutup laporan; menetapkan tindakan korektif dan memverifikasinya.'],
        'manager' => ['Manajer', 'Melihat dashboard, laporan, dan seluruh data safety (tanpa mengubah).'],
        'user' => ['Pelapor', 'Membuat laporan insiden dan hazard, serta mengerjakan tindakan yang ditugaskan kepadanya.'],
    ],

    'home' => '/ssap',
];
