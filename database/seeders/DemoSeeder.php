<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\CorrectiveAction;
use App\Models\Hazard;
use App\Models\Incident;
use App\Models\Location;
use App\Models\ManHour;
use App\Support\Numbering;
use App\Support\Risk;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data REKAAN untuk mencoba dashboard dan laporan (php artisan ssap:demo). Semua nama, NIK, dan kejadian fiktif.
 * Jangan dijalankan di produksi (perintahnya menolak).
 */
class DemoSeeder extends Seeder
{
    private const NAMES = ['Ahmad Fauzi', 'Budi Santoso', 'Citra Lestari', 'Dewi Anggraini', 'Eko Prasetyo', 'Fitri Handayani', 'Gunawan Wibowo', 'Hendra Saputra', 'Indra Kurniawan', 'Joko Susilo', 'Kartika Sari', 'Lukman Hakim', 'Mulyadi', 'Nur Aini', 'Oki Setiawan', 'Putra Mahendra', 'Rahmat Hidayat', 'Siti Rahmawati', 'Taufik Ismail', 'Umar Said', 'Wahyu Nugroho', 'Yusuf Ramadhan', 'Zainal Arifin', 'Rina Marlina', 'Dedi Kurnia'];

    private const DEPARTMENTS = ['Produksi', 'Plant & Maintenance', 'Logistik', 'HSE', 'Engineering', 'General Affairs', 'Hauling', 'Kontraktor'];

    private const POSITIONS = ['Operator', 'Mekanik', 'Supervisor', 'Foreman', 'Teknisi', 'Staf', 'Pengawas Lapangan', 'Driver'];

    private const INCIDENT_TEMPLATES = [
        'near_miss' => [
            ['Hampir tertabrak unit saat menyeberang di area parkir', 'Pekerja menyeberang tanpa melihat unit light vehicle yang melintas; pengemudi berhasil mengerem tepat waktu.'],
            ['Material jatuh dari ketinggian di area workshop', 'Baut dan perkakas jatuh dari platform perawatan, tidak ada orang di bawahnya saat kejadian.'],
            ['Kebocoran selang hidrolik terdeteksi sebelum menimbulkan cedera', 'Operator menemukan selang hidrolik pecah saat pemanasan unit, oli menetes ke area kerja.'],
            ['Kabel listrik terkelupas pada panel penerangan', 'Saat inspeksi pagi ditemukan kabel terkelupas dan hampir tersentuh pekerja.'],
        ],
        'first_aid' => [
            ['Luka sayat ringan di jari saat memotong material', 'Pekerja terkena sayatan kecil, ditangani P3K di pos dan kembali bekerja.'],
            ['Debu masuk ke mata saat pembersihan unit', 'Mata pekerja kemasukan debu, dibilas di eyewash dan kembali bekerja.'],
        ],
        'medical_treatment' => [
            ['Tangan terjepit saat penggantian komponen', 'Jari pekerja terjepit komponen dan perlu dijahit di klinik site.'],
            ['Terkilir saat turun dari unit', 'Pekerja terpeleset anak tangga unit dan mengalami keseleo pergelangan kaki.'],
        ],
        'restricted_work' => [
            ['Cedera punggung saat mengangkat beban', 'Pekerja mengangkat komponen tanpa alat bantu; dokter menyarankan pembatasan angkat beban.'],
        ],
        'lost_time' => [
            ['Patah tulang jari akibat terjepit di conveyor', 'Pekerja membersihkan area conveyor yang belum di-lockout; jari terjepit dan perlu rawat inap.'],
            ['Terjatuh dari tangga workshop', 'Pekerja terjatuh dari tangga setinggi 2 meter, mengalami cedera bahu dan istirahat medis.'],
        ],
        'property_damage' => [
            ['Kerusakan kaca spion unit saat manuver', 'Unit menyenggol tiang rambu saat memutar di area sempit.'],
            ['Forklift menabrak rak penyimpanan', 'Forklift membentur rak material saat mundur, rak penyok.'],
        ],
        'vehicle' => [
            ['Dump truck selip di jalan hauling basah', 'Unit selip akibat jalan licin setelah hujan dan menabrak tanggul pengaman.'],
            ['Tabrakan ringan antar light vehicle', 'Dua unit light vehicle bersenggolan di persimpangan tanpa rambu jelas.'],
        ],
        'fire' => [
            ['Percikan api pada kabel baterai unit', 'Terjadi korsleting kabel baterai dan api kecil dipadamkan dengan APAR.'],
        ],
        'environmental' => [
            ['Tumpahan oli di area pencucian unit', 'Tumpahan oli sekitar 20 liter di area wash bay, ditangani dengan spill kit.'],
        ],
    ];

    private const HAZARD_TEMPLATES = [
        ['unsafe_condition', 'Jalan hauling berlubang dan licin', 'Terdapat lubang dan genangan pada jalan hauling yang berbahaya bagi unit.'],
        ['ppe', 'Pekerja tidak memakai kacamata pengaman saat gerinda', 'Terlihat pekerja menggerinda tanpa kacamata pengaman.'],
        ['housekeeping', 'Material berserakan di jalur evakuasi', 'Palet dan material menutupi jalur evakuasi di workshop.'],
        ['electrical', 'Kabel ekstensi terkelupas di area las', 'Kabel ekstensi yang dipakai di area basah terkelupas.'],
        ['height', 'Pekerjaan di ketinggian tanpa full body harness', 'Pekerja naik ke atap tanpa harness dan lifeline.'],
        ['vehicle', 'Rambu batas kecepatan tertutup vegetasi', 'Rambu batas kecepatan di tikungan tidak terlihat.'],
        ['fire', 'APAR kedaluwarsa di pos genset', 'Segel dan masa uji APAR sudah lewat.'],
        ['chemical', 'Drum bahan kimia tanpa label dan tanpa secondary containment', 'Drum diletakkan terbuka di luar gudang B3.'],
        ['equipment', 'Guard mesin gerinda dilepas', 'Pelindung mata gerinda tidak terpasang.'],
        ['slope', 'Retakan di crest lereng pit', 'Ditemukan retakan memanjang di crest, perlu pemeriksaan geoteknik.'],
        ['unsafe_act', 'Pengemudi tidak memakai sabuk pengaman', 'Pengemudi light vehicle tidak memakai sabuk pengaman saat melintas.'],
        ['environment', 'Genangan air dengan sheen minyak di drainase', 'Terlihat lapisan minyak pada drainase dekat workshop.'],
        ['ergonomic', 'Posisi kerja membungkuk lama di bengkel', 'Pekerja perawatan bekerja membungkuk lebih dari 30 menit tanpa alat bantu.'],
    ];

    public function run(): void
    {
        mt_srand(2026);

        $locations = $this->locations();
        $now = now();
        $start = $now->copy()->subMonthsNoOverflow(13)->startOfMonth();

        // Jam kerja bulanan per lokasi
        $weights = $locations->mapWithKeys(fn ($l) => [$l->id => mt_rand(60, 160) * 1000]);
        for ($m = $start->copy(); $m->lte($now); $m->addMonth()) {
            foreach ($locations as $location) {
                ManHour::updateOrCreate(
                    ['year' => $m->year, 'month' => $m->month, 'location_id' => $location->id],
                    ['hours' => (int) ($weights[$location->id] * (0.9 + mt_rand(0, 20) / 100)), 'updated_by' => 'Data demo'],
                );
            }
        }

        for ($m = $start->copy(); $m->lte($now); $m->addMonth()) {
            $this->incidentsFor($m->copy(), $locations, $now);
            $this->hazardsFor($m->copy(), $locations, $now);
        }
    }

    private function locations()
    {
        if (Location::count() === 0) {
            foreach ([['PIT-N', 'Pit Utara'], ['PIT-S', 'Pit Selatan'], ['WS', 'Workshop'], ['HAUL', 'Jalan Hauling'], ['PORT', 'Stockpile & Pelabuhan'], ['OFF', 'Kantor & Mess']] as [$code, $name]) {
                Location::create(['code' => $code, 'name' => $name, 'is_active' => true]);
            }
        }

        return Location::active()->get();
    }

    private function incidentsFor(Carbon $month, $locations, Carbon $now): void
    {
        $plan = ['near_miss' => mt_rand(6, 13), 'first_aid' => mt_rand(2, 5), 'medical_treatment' => mt_rand(0, 2), 'restricted_work' => mt_rand(0, 1), 'lost_time' => mt_rand(0, 4) === 0 ? 1 : 0, 'property_damage' => mt_rand(1, 3), 'vehicle' => mt_rand(0, 2), 'fire' => mt_rand(0, 6) === 0 ? 1 : 0, 'environmental' => mt_rand(0, 1)];
        $types = config('ssap.incident_types');

        foreach ($plan as $type => $count) {
            for ($n = 0; $n < $count; $n++) {
                $occurred = $month->copy()->addDays(mt_rand(0, $month->daysInMonth - 1))->setTime(mt_rand(5, 21), mt_rand(0, 59));
                if ($occurred->gt($now)) {
                    continue;
                }

                [$title, $description] = self::INCIDENT_TEMPLATES[$type][array_rand(self::INCIDENT_TEMPLATES[$type])];
                $reporter = $this->person();
                $location = $locations->random();
                $meta = $types[$type];
                $createdAt = $occurred->copy()->addHours(mt_rand(1, $type === 'near_miss' ? 30 : 6));
                if ($createdAt->gt($now)) {
                    $createdAt = $now->copy();
                }

                $age = $createdAt->diffInDays($now);
                $status = match (true) {
                    $age > 45 => 'closed',
                    $age > 20 => mt_rand(0, 3) === 0 ? 'follow_up' : 'closed',
                    $age > 8 => ['follow_up', 'investigating', 'closed'][mt_rand(0, 2)],
                    $age > 2 => ['investigating', 'submitted'][mt_rand(0, 1)],
                    default => 'submitted',
                };

                $lostDays = $type === 'lost_time' ? mt_rand(3, 21) : ($type === 'restricted_work' ? mt_rand(0, 3) : 0);
                $likelihood = $meta['default_severity'] >= 3 ? mt_rand(2, 4) : mt_rand(1, 3);
                $consequence = min(5, $meta['default_severity'] + mt_rand(0, 1));
                $score = Risk::score($likelihood, $consequence);
                $investigator = $status === 'submitted' ? null : $this->person();

                $incident = Incident::create([
                    'number' => Numbering::next('INC', Incident::class, $occurred->year),
                    'type' => $type,
                    'severity' => $meta['default_severity'],
                    'likelihood' => $likelihood, 'consequence' => $consequence, 'risk_score' => $score, 'risk_level' => Risk::level($score),
                    'occurred_at' => $occurred,
                    'location_id' => $location->id,
                    'area_detail' => ['Area parkir', 'Bay 2', 'Persimpangan KM 3', 'Gudang sparepart', 'Wash bay', 'Pit ramp'][mt_rand(0, 5)],
                    'activity' => ['Perawatan unit', 'Pemuatan material', 'Hauling', 'Inspeksi harian', 'Pembersihan area'][mt_rand(0, 4)],
                    'title' => $title,
                    'description' => $description,
                    'immediate_action' => 'Area diamankan dan atasan diberi tahu; pekerjaan dihentikan sementara.',
                    'reporter_nik' => $reporter['nik'], 'reporter_name' => $reporter['name'], 'reporter_department' => $reporter['department'],
                    'reporter_position' => $reporter['position'], 'reporter_email' => $reporter['email'], 'created_by_id' => 'demo',
                    'status' => $status,
                    'injured_count' => $meta['injury'] ? 1 : 0,
                    'lost_days' => $lostDays,
                    'cost_estimate' => in_array($type, ['property_damage', 'vehicle', 'fire'], true) ? mt_rand(2, 80) * 1_000_000 : null,
                    'investigator_nik' => $investigator['nik'] ?? null, 'investigator_name' => $investigator['name'] ?? null, 'investigator_email' => $investigator['email'] ?? null,
                    'investigation_due' => $createdAt->copy()->addDays($type === 'lost_time' ? 5 : 7)->toDateString(),
                    'investigation_started_at' => $investigator ? $createdAt->copy()->addDay() : null,
                    'immediate_cause' => $investigator ? 'Tindakan tidak aman dan kondisi area kerja yang kurang terkendali.' : null,
                    'root_cause_category' => in_array($status, ['follow_up', 'closed'], true) ? array_rand(config('ssap.root_cause_categories')) : null,
                    'root_cause' => in_array($status, ['follow_up', 'closed'], true) ? 'Prosedur kerja belum diterapkan konsisten dan pengawasan di lapangan kurang.' : null,
                    'why_chain' => in_array($status, ['follow_up', 'closed'], true) ? ['Pekerjaan dilakukan tanpa pengaman yang sesuai', 'Pengawas tidak berada di lokasi saat pekerjaan dimulai', 'Tidak ada pemeriksaan sebelum mulai kerja (pre-job check)'] : null,
                    'closed_at' => $status === 'closed' ? $createdAt->copy()->addDays(mt_rand(7, 25)) : null,
                    'closed_by' => $status === 'closed' ? 'Petugas HSE (demo)' : null,
                    'closure_note' => $status === 'closed' ? 'Seluruh tindakan korektif telah selesai dan diverifikasi.' : null,
                    'created_at' => $createdAt, 'updated_at' => $createdAt,
                ]);

                if ($meta['injury']) {
                    $victim = $this->person();
                    $incident->persons()->create([
                        'role' => 'korban', 'nik' => $victim['nik'], 'name' => $victim['name'], 'company' => 'PT Adhi Kartiko Pratama Tbk',
                        'department' => $victim['department'], 'position' => $victim['position'],
                        'injury_type' => config('ssap.injury_types')[mt_rand(0, 8)], 'body_part' => config('ssap.body_parts')[mt_rand(0, 13)],
                        'treatment' => 'Pertolongan pertama di klinik site.', 'lost_days' => $lostDays,
                    ]);
                }

                ActivityLog::forceCreate(['loggable_type' => 'incident', 'loggable_id' => $incident->id, 'actor_id' => 'demo', 'actor_name' => $reporter['name'], 'event' => 'created', 'note' => 'Laporan dibuat', 'created_at' => $createdAt, 'updated_at' => $createdAt]);

                if (in_array($status, ['follow_up', 'closed'], true)) {
                    foreach (range(1, mt_rand(1, 3)) as $i) {
                        $this->action($incident, 'incident', $createdAt, $status === 'closed', $now);
                    }
                }
            }
        }
    }

    private function hazardsFor(Carbon $month, $locations, Carbon $now): void
    {
        $count = mt_rand(14, 28);

        for ($n = 0; $n < $count; $n++) {
            $observed = $month->copy()->addDays(mt_rand(0, $month->daysInMonth - 1))->setTime(mt_rand(6, 17), mt_rand(0, 59));
            if ($observed->gt($now)) {
                continue;
            }

            [$category, $title, $description] = self::HAZARD_TEMPLATES[array_rand(self::HAZARD_TEMPLATES)];
            $reporter = $this->person();
            $likelihood = [1, 2, 2, 3, 3, 3, 4, 4, 5][mt_rand(0, 8)];
            $consequence = [1, 2, 2, 3, 3, 4, 4, 5][mt_rand(0, 7)];
            $score = Risk::score($likelihood, $consequence);
            $level = Risk::level($score);
            $createdAt = $observed->copy()->addHours(mt_rand(0, 5));
            if ($createdAt->gt($now)) {
                $createdAt = $now->copy();
            }

            $age = $createdAt->diffInDays($now);
            $closed = $age > 30 ? mt_rand(0, 9) > 0 : ($age > 10 ? mt_rand(0, 9) > 3 : mt_rand(0, 9) > 7);
            $assigned = $closed || $age > 3 || mt_rand(0, 2) > 0;
            $pic = $assigned ? $this->person() : null;
            $due = $createdAt->copy()->addDays(Risk::slaDays($level));

            $hazard = Hazard::create([
                'number' => Numbering::next('HZD', Hazard::class, $observed->year),
                'observed_at' => $observed,
                'location_id' => $locations->random()->id,
                'area_detail' => ['Workshop', 'Jalan hauling KM 2', 'Pit ramp', 'Gudang B3', 'Mess', 'Stockpile'][mt_rand(0, 5)],
                'category' => $category, 'title' => $title, 'description' => $description,
                'likelihood' => $likelihood, 'consequence' => $consequence, 'risk_score' => $score, 'risk_level' => $level,
                'immediate_action' => mt_rand(0, 1) ? 'Area diberi pembatas sementara.' : null,
                'suggestion' => 'Perbaiki kondisi dan lakukan sosialisasi ke tim.',
                'reporter_nik' => $reporter['nik'], 'reporter_name' => $reporter['name'], 'reporter_department' => $reporter['department'],
                'reporter_position' => $reporter['position'], 'reporter_email' => $reporter['email'], 'created_by_id' => 'demo',
                'pic_nik' => $pic['nik'] ?? null, 'pic_name' => $pic['name'] ?? null, 'pic_department' => $pic['department'] ?? null, 'pic_email' => $pic['email'] ?? null,
                'due_date' => $due->toDateString(),
                'status' => $closed ? 'closed' : ($assigned ? 'in_progress' : 'open'),
                'closed_at' => $closed ? $createdAt->copy()->addDays(mt_rand(1, max(2, Risk::slaDays($level) + 4))) : null,
                'closed_by' => $closed ? 'Petugas HSE (demo)' : null,
                'closure_note' => $closed ? 'Kondisi sudah diperbaiki dan diverifikasi di lapangan.' : null,
                'created_at' => $createdAt, 'updated_at' => $createdAt,
            ]);

            if ($hazard->closed_at && $hazard->closed_at->gt($now)) {
                $hazard->update(['closed_at' => $now->copy()]);
            }

            ActivityLog::forceCreate(['loggable_type' => 'hazard', 'loggable_id' => $hazard->id, 'actor_id' => 'demo', 'actor_name' => $reporter['name'], 'event' => 'created', 'note' => 'Laporan dibuat', 'created_at' => $createdAt, 'updated_at' => $createdAt]);

            if ($assigned) {
                $this->action($hazard, 'hazard', $createdAt, $closed, $now, $due, $pic);
            }
        }
    }

    private function action($source, string $type, Carbon $createdAt, bool $closed, Carbon $now, ?Carbon $due = null, ?array $pic = null): void
    {
        $pic ??= $this->person();
        $due ??= $createdAt->copy()->addDays(mt_rand(5, 30));
        $status = $closed ? 'verified' : ['open', 'in_progress', 'in_progress', 'done'][mt_rand(0, 3)];

        CorrectiveAction::forceCreate([
            'source_type' => $type, 'source_id' => $source->id,
            'type' => ['immediate', 'corrective', 'preventive'][mt_rand(0, 2)],
            'description' => ['Perbaiki kondisi di lokasi dan pasang rambu peringatan', 'Sosialisasi prosedur kerja aman ke seluruh tim shift', 'Ganti komponen rusak dan lakukan pengecekan berkala', 'Tambahkan pengawas pada pekerjaan berisiko', 'Perbarui JSA dan briefing sebelum kerja'][mt_rand(0, 4)],
            'pic_nik' => $pic['nik'], 'pic_name' => $pic['name'], 'pic_department' => $pic['department'], 'pic_email' => $pic['email'],
            'due_date' => $due->toDateString(),
            'status' => $status,
            'completed_at' => in_array($status, ['done', 'verified'], true) ? min($due->copy()->subDays(mt_rand(-2, 3)), $now)->copy()->setTime(10, 0) : null,
            'completion_note' => in_array($status, ['done', 'verified'], true) ? 'Sudah dikerjakan sesuai rencana.' : null,
            'verified_at' => $status === 'verified' ? min($due->copy()->addDays(1), $now) : null,
            'verified_by' => $status === 'verified' ? 'Petugas HSE (demo)' : null,
            'created_by' => 'Petugas HSE (demo)',
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
    }

    private function person(): array
    {
        $name = self::NAMES[array_rand(self::NAMES)];

        return [
            'nik' => (string) (99000000 + mt_rand(1, 999)),
            'name' => $name,
            'department' => self::DEPARTMENTS[array_rand(self::DEPARTMENTS)],
            'position' => self::POSITIONS[array_rand(self::POSITIONS)],
            'email' => str($name)->lower()->replace(' ', '.')->append('@demo.invalid')->toString(),
        ];
    }

    /** Hapus seluruh data transaksi (bukan lokasi/pengaturan). */
    public static function wipe(): void
    {
        DB::transaction(function () {
            foreach (['activity_logs', 'attachments', 'corrective_actions', 'incident_persons', 'incidents', 'hazards', 'man_hours', 'email_logs'] as $table) {
                DB::table($table)->delete();
            }
        });
    }
}
