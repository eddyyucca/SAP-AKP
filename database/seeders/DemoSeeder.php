<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Hazard;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\Location;
use App\Support\Numbering;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data REKAAN untuk mencoba dashboard dan laporan (php artisan ssap:demo). Semua nama, NIK, dan temuan fiktif.
 * Jangan dijalankan di produksi (perintahnya menolak).
 */
class DemoSeeder extends Seeder
{
    private const NAMES = ['Ahmad Fauzi', 'Budi Santoso', 'Citra Lestari', 'Dewi Anggraini', 'Eko Prasetyo', 'Fitri Handayani', 'Gunawan Wibowo', 'Hendra Saputra', 'Indra Kurniawan', 'Joko Susilo', 'Kartika Sari', 'Lukman Hakim', 'Mulyadi', 'Nur Aini', 'Oki Setiawan', 'Putra Mahendra', 'Rahmat Hidayat', 'Siti Rahmawati', 'Taufik Ismail', 'Umar Said', 'Wahyu Nugroho', 'Yusuf Ramadhan', 'Zainal Arifin', 'Rina Marlina', 'Dedi Kurnia'];

    private const DEPARTMENTS = ['Produksi', 'Plant & Maintenance', 'Logistik', 'HSE', 'Engineering', 'General Affairs', 'Hauling', 'Kontraktor'];

    private const POSITIONS = ['Operator', 'Mekanik', 'Supervisor', 'Foreman', 'Teknisi', 'Staf', 'Pengawas Lapangan', 'Driver'];

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
        ['unsafe_condition', 'Tanggul pengaman di tikungan hauling rendah', 'Tinggi tanggul pengaman tidak sesuai standar di tikungan tajam.'],
        ['housekeeping', 'Tumpahan oli di wash bay belum dibersihkan', 'Lantai wash bay licin akibat tumpahan oli.'],
    ];

    /** @var list<array<string, mixed>> */
    private array $people = [];

    /** PIC langganan: indeks 0-2 andal, 3-4 sedang, 5-7 sering terlambat (supaya papan akuntabilitas bervariasi). */
    private array $lateRate = [0.08, 0.12, 0.15, 0.3, 0.35, 0.55, 0.65, 0.7];

    public function run(): void
    {
        mt_srand(2026);

        foreach (self::NAMES as $i => $name) {
            $this->people[] = [
                'nik' => (string) (99000001 + $i), 'name' => $name,
                'department' => self::DEPARTMENTS[$i % count(self::DEPARTMENTS)], 'position' => self::POSITIONS[$i % count(self::POSITIONS)],
                'email' => str($name)->lower()->replace(' ', '.')->append('@demo.invalid')->toString(),
            ];
        }

        $this->seedHrisCache();

        $locations = $this->locations();
        $now = now();
        $start = $now->copy()->subMonthsNoOverflow(13)->startOfMonth();

        for ($m = $start->copy(); $m->lte($now); $m->addMonth()) {
            $this->hazardsFor($m->copy(), $locations, $now);
            $this->inspectionsFor($m->copy(), $locations, $now);
        }
    }

    /**
     * Hierarki demo untuk target & cakupan tim, disimpan di cache HRIS (hanya terbaca bila HRIS_API_URL/TOKEN terisi):
     * indeks 0 = grade 6 (kepala departemen); 1-4 = grade 5 (atasan tim); sisanya grade 3/4 di bawah salah satu grade 5.
     */
    private function seedHrisCache(): void
    {
        $rows = [];
        foreach ($this->people as $i => $p) {
            $grade = $i === 0 ? 6 : ($i <= 4 ? 5 : ($i % 2 ? 3 : 4));
            $supervisor = $i === 0 ? null : ($i <= 4 ? $this->people[0]['nik'] : $this->people[1 + ($i % 4)]['nik']);
            $rows[$p['nik']] = [
                'employee_no' => $p['nik'], 'name' => $p['name'], 'department' => $p['department'], 'position' => $p['position'],
                'email' => $p['email'], 'is_active' => true, 'grade' => $grade, 'supervisor_nik' => $supervisor,
            ];
        }

        \Illuminate\Support\Facades\Cache::put('hris.employees', $rows, now()->addDay());
        \Illuminate\Support\Facades\Cache::forever('hris.employees.stale', $rows);
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

    private function hazardsFor(Carbon $month, $locations, Carbon $now): void
    {
        $count = mt_rand(18, 32);

        for ($n = 0; $n < $count; $n++) {
            $observed = $month->copy()->addDays(mt_rand(0, $month->daysInMonth - 1))->setTime(mt_rand(6, 17), mt_rand(0, 59));
            if ($observed->gt($now)) {
                continue;
            }

            [$category, $title, $description] = self::HAZARD_TEMPLATES[array_rand(self::HAZARD_TEMPLATES)];

            $this->makeHazard([
                'observed_at' => $observed, 'location_id' => $locations->random()->id,
                'area_detail' => ['Workshop', 'Jalan hauling KM 2', 'Pit ramp', 'Gudang B3', 'Mess', 'Stockpile'][mt_rand(0, 5)],
                'category' => $category, 'title' => $title, 'description' => $description,
            ], $observed->copy()->addHours(mt_rand(0, 5)), $now);
        }
    }

    /**
     * Membuat satu hazard dengan siklus hidup realistis: pelapor = PIC, perbaikan dikerjakan manual,
     * lalu pelapor menutupnya tepat waktu atau terlambat sesuai "kebiasaan" pelapor tersebut.
     */
    private function makeHazard(array $attrs, Carbon $createdAt, Carbon $now): Hazard
    {
        $reporterIdx = array_rand($this->people);
        $reporter = $this->people[$reporterIdx];
        $sla = (int) config('ssap.hazard_sla_days');
        // Jenis hazard: perilaku orang = TTA, selebihnya kondisi = KTA.
        $attrs['category'] = in_array($attrs['category'], ['unsafe_act', 'ppe'], true) ? 'tta' : 'kta';
        $attrs['description'] = str($attrs['title'])->finish('.')->append(' ', $attrs['description'] ?? '')->squish()->limit(400)->toString();
        $attrs['title'] = str($attrs['description'])->limit(150)->toString();
        if ($createdAt->gt($now)) {
            $createdAt = $now->copy();
        }

        $age = $createdAt->diffInDays($now);
        $fixing = $age > 2 || mt_rand(0, 3) > 0;   // perbaikan sudah mulai dikerjakan
        $due = $createdAt->copy()->addDays($sla)->startOfDay();

        $closeProb = $age > 40 ? 98 : ($age > 14 ? 86 : ($age > 5 ? 55 : 15));
        $closedAt = null;
        if ($fixing && mt_rand(1, 100) <= $closeProb) {
            $late = mt_rand(1, 100) <= (int) ($this->lateRate[$reporterIdx % count($this->lateRate)] * 100);
            $closedAt = $late
                ? $due->copy()->addDays(mt_rand(1, 9))->setTime(mt_rand(8, 16), 0)
                : $createdAt->copy()->addDays(mt_rand(0, max(0, $sla - 1)))->setTime(mt_rand(8, 16), 0);
            if ($closedAt->gt($now) || $closedAt->lt($createdAt)) {
                $closedAt = null;
            }
        }

        $hazard = Hazard::create($attrs + [
            'number' => Numbering::next('HZD', Hazard::class, $attrs['observed_at']->year),
            'immediate_action' => mt_rand(0, 1) ? 'Area diberi pembatas sementara.' : null,
            'suggestion' => 'Perbaiki kondisi dan lakukan sosialisasi ke tim.',
            'reporter_nik' => $reporter['nik'], 'reporter_name' => $reporter['name'], 'reporter_department' => $reporter['department'],
            'reporter_position' => $reporter['position'], 'reporter_email' => $reporter['email'], 'created_by_id' => 'demo',
            'due_date' => $due->toDateString(),
            'status' => $closedAt ? 'closed' : ($fixing ? 'in_progress' : 'open'),
            'closed_at' => $closedAt, 'closed_by' => $closedAt ? $reporter['name'] : null,
            'closure_note' => $closedAt ? ['Kondisi sudah diperbaiki oleh tim terkait dan dicek di lapangan.', 'Perbaikan selesai, area sudah aman dan diberi rambu.', 'Komponen diganti dan prosedur disosialisasikan ke tim.'][mt_rand(0, 2)] : null,
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);

        ActivityLog::forceCreate(['loggable_type' => 'hazard', 'loggable_id' => $hazard->id, 'actor_id' => 'demo', 'actor_name' => $reporter['name'], 'event' => 'created', 'note' => 'Laporan dibuat', 'created_at' => $createdAt, 'updated_at' => $createdAt]);

        return $hazard;
    }

    /** Inspeksi rutin: sekitar 65-105% dari target tiap template; butir tidak sesuai menjadi hazard. */
    private function inspectionsFor(Carbon $month, $locations, Carbon $now): void
    {
        $inspectors = array_slice($this->people, 8, 6);

        foreach (InspectionTemplate::active()->with('items')->get() as $template) {
            $count = (int) round($template->target_per_month * (mt_rand(65, 105) / 100));

            for ($n = 0; $n < $count; $n++) {
                $at = $month->copy()->addDays(mt_rand(0, $month->daysInMonth - 1))->setTime(mt_rand(6, 16), mt_rand(0, 59));
                if ($at->gt($now)) {
                    continue;
                }

                $inspector = $inspectors[array_rand($inspectors)];
                $location = $locations->random();
                $area = ['Workshop Bay 1', 'Area parkir unit', 'Gudang B3', 'Pit ramp', 'Mess & kantin', 'Stockpile'][mt_rand(0, 5)];
                $nokRate = mt_rand(2, 30) / 100;
                $ok = $nok = $na = 0;
                $rows = [];

                foreach ($template->items as $item) {
                    $roll = mt_rand(1, 100) / 100;
                    $result = $roll < $nokRate ? 'nok' : ($roll > 0.95 ? 'na' : 'ok');
                    ${$result}++;
                    $rows[] = [$item, $result];
                }

                $checked = $ok + $nok;
                $inspection = Inspection::create([
                    'number' => Numbering::next('INS', Inspection::class, $at->year),
                    'template_id' => $template->id, 'template_name' => $template->name, 'category' => $template->category,
                    'location_id' => $location->id, 'area_detail' => $area, 'inspected_at' => $at,
                    'inspector_nik' => $inspector['nik'], 'inspector_name' => $inspector['name'], 'inspector_department' => $inspector['department'],
                    'inspector_email' => $inspector['email'], 'created_by_id' => 'demo',
                    'total_items' => $template->items->count(), 'ok_count' => $ok, 'nok_count' => $nok, 'na_count' => $na,
                    'score' => $checked ? round($ok * 100 / $checked, 1) : null,
                    'notes' => mt_rand(0, 2) === 0 ? 'Secara umum baik, temuan sudah dibahas dengan pengawas area.' : null,
                    'created_at' => $at, 'updated_at' => $at,
                ]);

                foreach ($rows as $pos => [$item, $result]) {
                    $note = $result === 'nok' ? ['Belum sesuai standar, perlu perbaikan', 'Ditemukan kondisi rusak/tidak lengkap', 'Tidak dilakukan oleh pekerja saat inspeksi'][mt_rand(0, 2)] : null;
                    $hazardId = null;

                    if ($result === 'nok') {
                        $hazardId = $this->makeHazard([
                            'observed_at' => $at, 'location_id' => $location->id, 'area_detail' => $area,
                            'category' => mt_rand(1, 100) <= 35 ? 'ppe' : 'kta',
                            'title' => str('Temuan inspeksi: '.$item->question)->limit(150)->toString(),
                            'description' => "{$note} ({$inspection->number})",
                        ], $at->copy(), $now)->id;
                    }

                    $inspection->items()->create(['position' => $pos + 1, 'question' => $item->question, 'result' => $result, 'note' => $note, 'hazard_id' => $hazardId]);
                }

                ActivityLog::forceCreate(['loggable_type' => 'inspection', 'loggable_id' => $inspection->id, 'actor_id' => 'demo', 'actor_name' => $inspector['name'], 'event' => 'created', 'note' => "Inspeksi selesai: skor {$inspection->score}%, {$nok} temuan", 'created_at' => $at, 'updated_at' => $at]);
            }
        }
    }

    /** Hapus seluruh data transaksi SAP (bukan lokasi, template, atau pengaturan). */
    public static function wipe(): void
    {
        DB::transaction(function () {
            foreach (['inspection_items', 'inspections', 'activity_logs', 'attachments', 'corrective_actions', 'hazards', 'email_logs'] as $table) {
                DB::table($table)->delete();
            }
        });
    }
}
