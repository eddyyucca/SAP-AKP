<?php

/*
 * STANDAR PELAPORAN SAFETY AKP. Semua klasifikasi, matriks risiko, dan tenggat ada di sini supaya form, dashboard,
 * laporan, dan halaman "Standar Pelaporan" memakai sumber yang sama. Kode (kunci) disimpan di database: jangan diubah
 * setelah ada data; label dan warna boleh disesuaikan.
 */
return [
    'company' => 'PT Adhi Kartiko Pratama Tbk',

    // Pengali laju kecelakaan: LTIFR = kasus LTI x 1.000.000 / jam kerja.
    'rate_base' => 1_000_000,

    /*
     * Jenis kejadian (urutan dari paling berat). group = pengelompokan grafik; lti = menghitung LTI;
     * recordable = termasuk "recordable injury" (untuk TRIR); injury = ada korban orang.
     */
    'incident_types' => [
        'fatality' => ['label' => 'Fatality (Meninggal Dunia)', 'short' => 'Fatal', 'group' => 'lti', 'lti' => true, 'recordable' => true, 'injury' => true, 'color' => '#7f1d1d', 'default_severity' => 5],
        'lost_time' => ['label' => 'Lost Time Injury (LTI)', 'short' => 'LTI', 'group' => 'lti', 'lti' => true, 'recordable' => true, 'injury' => true, 'color' => '#dc2626', 'default_severity' => 4],
        'restricted_work' => ['label' => 'Restricted Work Case (RWC)', 'short' => 'RWC', 'group' => 'recordable', 'lti' => false, 'recordable' => true, 'injury' => true, 'color' => '#ea580c', 'default_severity' => 3],
        'medical_treatment' => ['label' => 'Medical Treatment Case (MTC)', 'short' => 'MTC', 'group' => 'recordable', 'lti' => false, 'recordable' => true, 'injury' => true, 'color' => '#f59e0b', 'default_severity' => 3],
        'first_aid' => ['label' => 'First Aid Case (P3K)', 'short' => 'FAC', 'group' => 'first_aid', 'lti' => false, 'recordable' => false, 'injury' => true, 'color' => '#eab308', 'default_severity' => 2],
        'fire' => ['label' => 'Kebakaran / Peledakan', 'short' => 'Api', 'group' => 'asset', 'lti' => false, 'recordable' => false, 'injury' => false, 'color' => '#7c3aed', 'default_severity' => 3],
        'vehicle' => ['label' => 'Kecelakaan Kendaraan / Alat Berat', 'short' => 'Kendaraan', 'group' => 'asset', 'lti' => false, 'recordable' => false, 'injury' => false, 'color' => '#0891b2', 'default_severity' => 3],
        'property_damage' => ['label' => 'Kerusakan Properti / Peralatan', 'short' => 'Properti', 'group' => 'asset', 'lti' => false, 'recordable' => false, 'injury' => false, 'color' => '#2563eb', 'default_severity' => 2],
        'environmental' => ['label' => 'Insiden Lingkungan (tumpahan, pencemaran)', 'short' => 'Lingkungan', 'group' => 'asset', 'lti' => false, 'recordable' => false, 'injury' => false, 'color' => '#16a34a', 'default_severity' => 2],
        'near_miss' => ['label' => 'Nyaris Celaka (Near Miss)', 'short' => 'Near Miss', 'group' => 'near_miss', 'lti' => false, 'recordable' => false, 'injury' => false, 'color' => '#38c0b0', 'default_severity' => 1],
    ],

    // Kelompok untuk grafik tren (urutan susun dari bawah ke atas) dan warnanya.
    'incident_groups' => [
        'near_miss' => ['label' => 'Nyaris celaka', 'color' => '#38c0b0'],
        'first_aid' => ['label' => 'Cedera ringan (P3K)', 'color' => '#eab308'],
        'recordable' => ['label' => 'Cedera tercatat (MTC/RWC)', 'color' => '#ea580c'],
        'lti' => ['label' => 'LTI & Fatal', 'color' => '#b91c1c'],
        'asset' => ['label' => 'Aset / Lingkungan / Api', 'color' => '#6366f1'],
    ],

    // Keparahan aktual (dampak yang benar-benar terjadi).
    'severity' => [
        1 => ['label' => 'Sangat ringan', 'hint' => 'Tanpa cedera / kerugian dapat diabaikan', 'color' => '#38c0b0'],
        2 => ['label' => 'Ringan', 'hint' => 'Cedera P3K, kerugian kecil', 'color' => '#eab308'],
        3 => ['label' => 'Sedang', 'hint' => 'Perawatan medis / pembatasan kerja, kerugian signifikan', 'color' => '#f97316'],
        4 => ['label' => 'Berat', 'hint' => 'Hilang hari kerja / cacat, kerugian besar', 'color' => '#dc2626'],
        5 => ['label' => 'Katastropik', 'hint' => 'Korban jiwa / kerusakan masif', 'color' => '#7f1d1d'],
    ],

    // Matriks risiko 5x5: skor = kemungkinan x konsekuensi.
    'likelihood' => [
        1 => ['label' => 'Sangat jarang', 'hint' => 'Hampir tidak pernah terjadi'],
        2 => ['label' => 'Jarang', 'hint' => 'Bisa terjadi sekali dalam beberapa tahun'],
        3 => ['label' => 'Mungkin', 'hint' => 'Bisa terjadi sekali setahun'],
        4 => ['label' => 'Sering', 'hint' => 'Bisa terjadi beberapa kali setahun'],
        5 => ['label' => 'Hampir pasti', 'hint' => 'Terjadi hampir setiap bulan'],
    ],
    'consequence' => [
        1 => ['label' => 'Tidak signifikan', 'hint' => 'Tanpa cedera'],
        2 => ['label' => 'Kecil', 'hint' => 'P3K'],
        3 => ['label' => 'Sedang', 'hint' => 'Perawatan medis / pembatasan kerja'],
        4 => ['label' => 'Besar', 'hint' => 'Hilang hari kerja / cacat permanen'],
        5 => ['label' => 'Sangat besar', 'hint' => 'Fatal / multi korban'],
    ],

    // Tingkat risiko menurut skor (batas atas, urut naik). sla = tenggat penutupan hazard (hari).
    'risk_levels' => [
        'low' => ['label' => 'Rendah', 'max' => 4, 'color' => '#16a34a', 'bg' => '#dcfce7', 'sla_days' => 14, 'action' => 'Pantau, perbaiki dalam rutinitas kerja.'],
        'medium' => ['label' => 'Sedang', 'max' => 9, 'color' => '#ca8a04', 'bg' => '#fef9c3', 'sla_days' => 7, 'action' => 'Rencanakan perbaikan oleh PIC area.'],
        'high' => ['label' => 'Tinggi', 'max' => 16, 'color' => '#ea580c', 'bg' => '#ffedd5', 'sla_days' => 3, 'action' => 'Perhatian manajemen, kendali segera.'],
        'extreme' => ['label' => 'Ekstrem', 'max' => 25, 'color' => '#dc2626', 'bg' => '#fee2e2', 'sla_days' => 1, 'action' => 'Hentikan pekerjaan sampai risiko dikendalikan.'],
    ],

    // Tenggat investigasi (hari sejak dilaporkan) menurut jenis; sisanya memakai nilai default.
    'investigation_days' => ['fatality' => 3, 'lost_time' => 5, 'default' => 7],

    // Pelaporan awal ke HSE (jam sejak kejadian) untuk jenis yang berat.
    'notify_hours' => ['fatality' => 1, 'lost_time' => 4, 'restricted_work' => 12, 'medical_treatment' => 12, 'fire' => 4, 'vehicle' => 12, 'environmental' => 12, 'default' => 24],

    // Jenis yang langsung memicu email "darurat" ke daftar penerima insiden berat.
    'severe_types' => ['fatality', 'lost_time', 'fire'],

    'hazard_categories' => [
        'unsafe_condition' => 'Kondisi tidak aman',
        'unsafe_act' => 'Tindakan tidak aman',
        'ppe' => 'APD tidak sesuai / tidak dipakai',
        'housekeeping' => 'Kebersihan & kerapihan (housekeeping)',
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

    'root_cause_categories' => [
        'human' => 'Faktor manusia (kompetensi, kelelahan, kelalaian)',
        'procedure' => 'Prosedur / instruksi kerja',
        'equipment' => 'Peralatan / perawatan',
        'environment' => 'Lingkungan kerja',
        'management' => 'Manajemen / pengawasan',
        'design' => 'Desain / perencanaan',
        'external' => 'Faktor eksternal / pihak lain',
    ],

    'injury_types' => ['Luka sayat / robek', 'Memar / benturan', 'Patah tulang', 'Terkilir / keseleo', 'Luka bakar', 'Tersengat listrik', 'Iritasi / paparan bahan kimia', 'Sesak / terhirup', 'Terjepit / tergencet', 'Lainnya'],

    'body_parts' => ['Kepala', 'Mata', 'Wajah', 'Leher', 'Bahu', 'Lengan', 'Tangan / Jari', 'Punggung', 'Dada', 'Perut', 'Pinggul', 'Kaki', 'Telapak kaki / Jari kaki', 'Seluruh tubuh', 'Lainnya'],

    'person_roles' => ['korban' => 'Korban', 'saksi' => 'Saksi', 'pelaku' => 'Pihak terlibat'],

    'incident_statuses' => [
        'submitted' => ['label' => 'Dilaporkan', 'color' => 'info', 'icon' => 'bi-send'],
        'investigating' => ['label' => 'Investigasi', 'color' => 'warning', 'icon' => 'bi-search'],
        'follow_up' => ['label' => 'Tindak lanjut', 'color' => 'primary', 'icon' => 'bi-list-check'],
        'closed' => ['label' => 'Ditutup', 'color' => 'success', 'icon' => 'bi-check2-circle'],
        'cancelled' => ['label' => 'Dibatalkan', 'color' => 'secondary', 'icon' => 'bi-x-circle'],
    ],

    'hazard_statuses' => [
        'open' => ['label' => 'Dilaporkan', 'color' => 'info', 'icon' => 'bi-send'],
        'in_progress' => ['label' => 'Dalam perbaikan', 'color' => 'warning', 'icon' => 'bi-tools'],
        'closed' => ['label' => 'Selesai', 'color' => 'success', 'icon' => 'bi-check2-circle'],
        'rejected' => ['label' => 'Ditolak', 'color' => 'secondary', 'icon' => 'bi-x-circle'],
    ],

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

    // Berkas lampiran (foto kejadian, dokumen). Ukuran dalam KB.
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
