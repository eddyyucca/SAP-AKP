<?php

use Illuminate\Support\Facades\Schedule;

// Penjadwal (jalankan `php artisan schedule:work`, atau kontainer terpisah di server):
// karyawan HRIS disegarkan tiap jam; pengingat tindakan dan ringkasan HSE tiap pagi.
Schedule::command('ssap:sync-hris')->hourly()->withoutOverlapping();
Schedule::command('ssap:remind')->dailyAt('07:00')->withoutOverlapping();
