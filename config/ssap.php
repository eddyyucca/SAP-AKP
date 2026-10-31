<?php

/*
 * STANDAR SAP (Safety Accountability Program): hazard report, inspeksi rutin, dan tindakan korektif.
 * Semua klasifikasi dan tenggat ada di sini supaya form, dashboard, laporan, dan halaman
 * "Standar" memakai sumber yang sama. Kode (kunci) disimpan di database: jangan diubah setelah ada data;
 * label dan warna boleh disesuaikan.
 *
 * Modul insiden/LPI/jam kerja yang lama diarsipkan di arsip/modul-insiden-lpi (tabel databasenya tidak dihapus).
 */
return [
    'company' => 'PT Adhi Kartiko Pratama Tbk',

    // Nama resmi aplikasi (kode modul tetap ssap-safety).
    'app_full_name' => 'Safety Accountability Program',
    'app_short_name' => 'SAP',

    // Tenggat penyelesaian hazard (hari sejak dilaporkan). HSE dapat mengubahnya saat menugaskan PIC.
    'hazard_sla_days' => 7,

    // Jenis hazard: TTA = Tindakan Tidak Aman (perilaku), KTA = Kondisi Tidak Aman (lingkungan/peralatan).
    'hazard_categories' => [
        'tta' => 'TTA (Tindakan Tidak Aman)',
        'kta' => 'KTA (Kondisi Tidak Aman)',
    ],
    'hazard_category_colors' => ['tta' => '#f59e0b', 'kta' => '#203868'],

    // Bidang/area yang diperiksa pada template inspeksi (bukan jenis hazard).
    'area_categories' => [
        'housekeeping' => 'Kebersihan & kerapihan (housekeeping)',
        'ppe' => 'APD',
        'vehicle' => 'Kendaraan & lalu lintas tambang',
        'equipment' => 'Peralatan / mesin',
        'electrical' => 'Kelistrikan',
        'height' => 'Bekerja di ketinggian',
        'slope' => 'Lereng / jalan / geoteknik',
        'chemical' => 'B3 / bahan kimia',
        'fire' => 'Potensi kebakaran',
        'environment' => 'Lingkungan',
        'ergonomic' => 'Ergonomi / kesehatan kerja',
        'other' => 'Lainnya',
    ],

    // Target pelaporan menurut grade HRIS (nilai awal; diubah admin di Pengaturan, dan grade lain dari HRIS dapat ditambahkan di sana).
    // period = week (per minggu, Senin-Minggu) atau month. hazard = laporan hazard yang dibuat sendiri (temuan otomatis dari inspeksi
    // tidak dihitung); inspection = inspeksi rutin yang diisi. Isi 0 + 0 bila grade tidak punya target.
    'grade_targets' => [
        3 => ['period' => 'week', 'hazard' => 2, 'inspection' => 0],
        4 => ['period' => 'week', 'hazard' => 2, 'inspection' => 0],
        5 => ['period' => 'week', 'hazard' => 2, 'inspection' => 0],
    ],

    'hazard_statuses' => [
        'open' => ['label' => 'Dilaporkan', 'color' => 'info', 'icon' => 'bi-send'],
        'in_progress' => ['label' => 'Dalam perbaikan', 'color' => 'warning', 'icon' => 'bi-tools'],
        'closed' => ['label' => 'Selesai', 'color' => 'success', 'icon' => 'bi-check2-circle'],
        'rejected' => ['label' => 'Ditolak', 'color' => 'secondary', 'icon' => 'bi-x-circle'],
    ],

    // Umur hazard terbuka (hari sejak dilaporkan) untuk grafik aging. [batas atas, label]; null = tanpa batas.
    'aging_buckets' => [[3, '0-3 hari'], [7, '4-7 hari'], [14, '8-14 hari'], [30, '15-30 hari'], [null, '> 30 hari']],

    'action_statuses' => [
        'open' => ['label' => 'Belum dikerjakan', 'color' => 'info', 'icon' => 'bi-circle'],
        'in_progress' => ['label' => 'Dikerjakan', 'color' => 'warning', 'icon' => 'bi-hourglass-split'],
        'done' => ['label' => 'Menunggu verifikasi', 'color' => 'primary', 'icon' => 'bi-hourglass-bottom'],
        'verified' => ['label' => 'Selesai & terverifikasi', 'color' => 'success', 'icon' => 'bi-patch-check'],
    ],

    'action_types' => [
        'immediate' => 'Perbaikan segera',
        'corrective' => 'Tindakan korektif',
        'preventive' => 'Tindakan pencegahan',
    ],

    // Skor inspeksi: >= good baik, >= fair cukup, selebihnya perlu perbaikan.
    'inspection_score' => ['good' => 90, 'fair' => 75],

    // Berkas lampiran (foto temuan, bukti perbaikan). Ukuran dalam KB.
    'attachments' => [
        'max_files' => 6,
        'max_kb' => 8192,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    ],

    // Pengaturan awal (dapat diubah admin di menu Pengaturan; disimpan di database).
    'defaults' => [
        'notify_hse' => env('SSAP_NOTIFY_HSE', ''),
        'notify_manager' => env('SSAP_NOTIFY_MANAGER', ''),
        'notify_severe' => env('SSAP_NOTIFY_SEVERE', ''),
        'notify_reporter' => '1',
        'remind_days_before' => '2',
    ],
];
