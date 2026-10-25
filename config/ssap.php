<?php

/*
 * STANDAR PELAPORAN SAFETY AKP. Semua klasifikasi, matriks risiko, dan tenggat ada di sini supaya form, dashboard,
 * laporan, dan halaman "Standar Pelaporan" memakai sumber yang sama. Kode (kunci) disimpan di database: jangan diubah
 * setelah ada data; label dan warna boleh disesuaikan.
 */
return [
    'company' => 'PT Adhi Kartiko Pratama Tbk',

    // Nama resmi aplikasi (kode modul tetap ssap-safety).
    'app_full_name' => 'Safety Accountability Program',
    'app_short_name' => 'SAP',

    // Pengali laju kecelakaan: LTIFR = kasus LTI x 1.000.000 / jam kerja.
    'rate_base' => 1_000_000,

    /*
     * Jenis kejadian (urutan dari paling berat). group = pengelompokan grafik; lti = menghitung LTI;
     * recordable = termasuk "recordable injury" (untuk TRIR); injury = ada korban orang.
     */
    'incident_types' => [
        // A. Kecelakaan tambang (KEPMEN ESDM 1827 K/30/MEM/2018)
        'fatality' => ['label' => 'Fatality', 'short' => 'Fatality', 'category' => 'mining', 'group' => 'lti', 'lti' => true, 'recordable' => true, 'injury' => true, 'color' => '#7f1d1d', 'default_severity' => 5, 'definition' => 'Pekerja meninggal dunia akibat kecelakaan tambang.'],
        'serious_injury' => ['label' => 'Cidera Berat', 'short' => 'Cidera Berat', 'category' => 'mining', 'group' => 'lti', 'lti' => true, 'recordable' => true, 'injury' => true, 'color' => '#dc2626', 'default_severity' => 4, 'definition' => 'Korban tidak mampu melakukan tugas semula lebih dari 3 minggu.'],
        'minor_injury' => ['label' => 'Cidera Ringan', 'short' => 'Cidera Ringan', 'category' => 'mining', 'group' => 'lti', 'lti' => true, 'recordable' => true, 'injury' => true, 'color' => '#f97316', 'default_severity' => 3, 'definition' => 'Korban tidak mampu melakukan tugas semula lebih dari 1 hari/shift sampai kurang dari 3 minggu.'],
        // B. Kecelakaan umum (ANSI 1885.1/1990)
        'restricted_work' => ['label' => 'Restricted Work Injury', 'short' => 'RWI', 'category' => 'general', 'group' => 'recordable', 'lti' => false, 'recordable' => true, 'injury' => true, 'color' => '#ea580c', 'default_severity' => 3, 'definition' => 'Cedera yang membuat pekerja hanya dapat melakukan sebagian tugas atau ditugaskan pekerjaan lain pada hari berikutnya.'],
        'medical_treatment' => ['label' => 'Medical Treatment Injury', 'short' => 'MTI', 'category' => 'general', 'group' => 'recordable', 'lti' => false, 'recordable' => true, 'injury' => true, 'color' => '#f59e0b', 'default_severity' => 3, 'definition' => 'Cedera yang memerlukan perawatan medis di luar pertolongan pertama; pekerja dapat kembali bekerja.'],
        'first_aid' => ['label' => 'First Aid Injury', 'short' => 'FAI', 'category' => 'general', 'group' => 'first_aid', 'lti' => false, 'recordable' => false, 'injury' => true, 'color' => '#eab308', 'default_severity' => 2, 'definition' => 'Cedera ringan yang cukup ditangani dengan pertolongan pertama (P3K).'],
        'property_damage' => ['label' => 'Property Damage', 'short' => 'Prop. Damage', 'category' => 'general', 'group' => 'asset', 'lti' => false, 'recordable' => false, 'injury' => false, 'color' => '#2563eb', 'default_severity' => 2, 'definition' => 'Kerusakan peralatan, unit, atau properti (termasuk tabrakan kendaraan/alat berat tanpa korban).'],
        // C. Kejadian lingkungan
        'environmental' => ['label' => 'Pencemaran Lingkungan', 'short' => 'Lingkungan', 'category' => 'environment', 'group' => 'asset', 'lti' => false, 'recordable' => false, 'injury' => false, 'color' => '#16a34a', 'default_severity' => 2, 'definition' => 'Tumpahan, kebocoran, atau pembuangan yang mencemari tanah, air, atau udara.'],
        // D. Kebakaran
        'fire' => ['label' => 'Fire Case (Kebakaran / Ledakan)', 'short' => 'Fire Case', 'category' => 'fire', 'group' => 'asset', 'lti' => false, 'recordable' => false, 'injury' => false, 'color' => '#7c3aed', 'default_severity' => 3, 'definition' => 'Kebakaran atau ledakan, dengan atau tanpa kerugian.'],
        // E. Incident (tidak ada kerugian)
        'near_miss' => ['label' => 'Nearmiss', 'short' => 'Nearmiss', 'category' => 'incident', 'group' => 'near_miss', 'lti' => false, 'recordable' => false, 'injury' => false, 'color' => '#38c0b0', 'default_severity' => 1, 'definition' => 'Kejadian tidak diinginkan yang tidak menimbulkan korban maupun kerugian.'],
    ],

    // Kelompok laporan rekap (format laporan kecelakaan A-E). cumulative = total digabung dengan kelompok lain.
    'incident_categories' => [
        'mining' => ['code' => 'A', 'title' => 'KECELAKAAN TAMBANG', 'ref' => 'KEPMEN 1827/2018', 'total_label' => 'Total Mining Accident Case'],
        'general' => ['code' => 'B', 'title' => 'KECELAKAAN UMUM', 'ref' => 'ANSI 1885.1/1990', 'total_label' => 'Total Accident Case', 'cumulative' => ['mining', 'general']],
        'environment' => ['code' => 'C', 'title' => 'KEJADIAN LINGKUNGAN', 'ref' => null, 'total_label' => 'Total Case Environment'],
        'fire' => ['code' => 'D', 'title' => 'FIRE CASE (KEBAKARAN)', 'ref' => null, 'total_label' => 'Total Fire Case'],
        'incident' => ['code' => 'E', 'title' => 'INCIDENT (TIDAK ADA KERUGIAN)', 'ref' => null, 'total_label' => null],
    ],

    // Kelompok untuk grafik tren (urutan susun dari bawah ke atas) dan warnanya.
    'incident_groups' => [
        'near_miss' => ['label' => 'Nyaris celaka', 'color' => '#38c0b0'],
        'first_aid' => ['label' => 'Cedera ringan (P3K)', 'color' => '#eab308'],
        'recordable' => ['label' => 'Cedera tercatat (MTC/RWC)', 'color' => '#ea580c'],
        'lti' => ['label' => 'Kecelakaan tambang (Fatal/Berat/Ringan)', 'color' => '#b91c1c'],
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
    'investigation_days' => ['fatality' => 3, 'serious_injury' => 5, 'default' => 7],

    // Pelaporan awal ke HSE (jam sejak kejadian) untuk jenis yang berat.
    'notify_hours' => ['fatality' => 1, 'serious_injury' => 4, 'minor_injury' => 12, 'restricted_work' => 12, 'medical_treatment' => 12, 'fire' => 4, 'environmental' => 12, 'default' => 24],

    // Jenis yang langsung memicu email "darurat" ke daftar penerima insiden berat.
    'severe_types' => ['fatality', 'serious_injury', 'fire'],

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

    // Kontraktor di area pertambangan: IUP (Izin Usaha Pertambangan) atau IUJP (Izin Usaha Jasa Pertambangan).
    'contractor_areas' => [
        'iup' => ['label' => 'IUP', 'full' => 'Izin Usaha Pertambangan', 'color' => '#203868'],
        'iujp' => ['label' => 'IUJP', 'full' => 'Izin Usaha Jasa Pertambangan', 'color' => '#0891b2'],
    ],

    /*
     * LPI (Laporan Penyelidikan Insiden) wajib disampaikan kontraktor untuk insiden di areanya.
     * Tenggat = tanggal dilaporkan + jumlah hari (investigation_days). Jenis di bawah tidak mewajibkan LPI.
     */
    'lpi_exempt_types' => ['near_miss'],

    'lpi_statuses' => [
        'pending' => ['label' => 'Belum diterima', 'color' => 'warning', 'icon' => 'bi-hourglass-split'],
        'submitted' => ['label' => 'Diterima, ditinjau', 'color' => 'info', 'icon' => 'bi-inbox'],
        'revision' => ['label' => 'Perlu revisi', 'color' => 'danger', 'icon' => 'bi-arrow-counterclockwise'],
        'accepted' => ['label' => 'Disetujui', 'color' => 'success', 'icon' => 'bi-patch-check'],
    ],

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
