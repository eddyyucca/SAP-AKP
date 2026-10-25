<?php

return [
    // AKP Mail (layanan email terpusat). Token dibuat di AKP Mail > Aplikasi (berawalan akpm_).
    // Kosong = email memakai pengaturan MAIL_* Laravel (di lokal: log).
    'url' => env('MAILSVC_URL', 'http://localhost:8002'),
    'token' => env('MAILSVC_TOKEN'),
    'from_name' => env('MAILSVC_FROM_NAME', 'SSAP Safety AKP'),
    'timeout' => (int) env('MAILSVC_TIMEOUT', 10),
];
