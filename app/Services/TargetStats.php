<?php

namespace App\Services;

use App\Models\Hazard;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Pencapaian target pelaporan per karyawan menurut grade HRIS. Tiap grade punya target sendiri dan periode sendiri
 * (per minggu atau per bulan), diatur admin di Pengaturan.
 *  - Hazard   : laporan hazard yang dibuat sendiri pada periode itu (bukan ditolak; temuan otomatis dari inspeksi tidak dihitung)
 *  - Inspeksi : inspeksi rutin yang diisi pada periode itu
 * Tercapai bila hazard >= target hazard DAN inspeksi >= target inspeksi. Grade tanpa target (0 + 0) tidak masuk daftar.
 */
class TargetStats
{
    public const PERIODS = ['week' => 'per minggu', 'month' => 'per bulan'];

    public function __construct(private readonly HrisEmployees $hris)
    {
    }

    /** Grade yang dapat diberi target: seluruh grade yang ada di HRIS, ditambah grade bawaan di config. @return list<int> */
    public static function grades(): array
    {
        $found = array_keys(app(HrisEmployees::class)->gradeCounts());

        return collect(array_keys(config('ssap.grade_targets')))->merge($found)->map(fn ($g) => (int) $g)->filter(fn ($g) => $g > 0)->unique()->sort()->values()->all();
    }

    /** @return array<int, array{hazard:int,inspection:int,period:string}> target per grade (pengaturan admin; bila belum ada memakai config) */
    public static function targets(): array
    {
        $out = [];
        foreach (self::grades() as $g) {
            $d = config("ssap.grade_targets.$g", ['hazard' => 0, 'inspection' => 0, 'period' => 'week']);
            $period = Setting::value("target_period_$g", $d['period'] ?? 'week');
            $out[$g] = [
                'hazard' => (int) Setting::value("target_hazard_$g", (string) $d['hazard']),
                'inspection' => (int) Setting::value("target_inspection_$g", (string) $d['inspection']),
                'period' => isset(self::PERIODS[$period]) ? $period : 'week',
            ];
        }

        return $out;
    }

    /** Rentang periode yang memuat tanggal acuan. @return array{0:Carbon,1:Carbon} */
    public static function range(string $period, CarbonInterface $ref): array
    {
        $ref = Carbon::instance($ref);

        return $period === 'month'
            ? [$ref->copy()->startOfMonth(), $ref->copy()->endOfMonth()]
            : [$ref->copy()->startOfWeek(), $ref->copy()->endOfWeek()];
    }

    public static function periodLabel(string $period, CarbonInterface $ref): string
    {
        [$from, $to] = self::range($period, $ref);

        return $period === 'month'
            ? $from->translatedFormat('F Y')
            : $from->translatedFormat('d M').' - '.$to->translatedFormat('d M Y');
    }

    /**
     * @param  list<string>|null  $niks  batasi ke NIK ini (null = semua karyawan yang gradenya bertarget)
     * @return list<array<string, mixed>> baris per karyawan; yang belum tercapai di urutan atas
     */
    public function rows(CarbonInterface $ref, ?array $niks = null): array
    {
        $targets = array_filter(self::targets(), fn ($t) => $t['hazard'] + $t['inspection'] > 0);
        if (! $targets) {
            return [];
        }

        $members = $this->hris->members(array_keys($targets), $niks);
        if (! $members) {
            return [];
        }

        $fromInspection = InspectionItem::query()->whereNotNull('hazard_id')->pluck('hazard_id');
        $counts = [];   // [period => [hazard => [nik => n], inspection => [nik => n]]]
        foreach (array_unique(array_column($targets, 'period')) as $period) {
            [$from, $to] = self::range($period, $ref);
            $ids = array_column(array_filter($members, fn ($m) => $targets[$m['grade']]['period'] === $period), 'nik');

            $counts[$period] = [
                'hazard' => Hazard::query()->whereIn('reporter_nik', $ids)->where('status', '!=', 'rejected')
                    ->whereBetween('observed_at', [$from, $to])->whereNotIn('id', $fromInspection)
                    ->selectRaw('reporter_nik, count(*) as total')->groupBy('reporter_nik')->pluck('total', 'reporter_nik'),
                'inspection' => Inspection::query()->whereIn('inspector_nik', $ids)->whereBetween('inspected_at', [$from, $to])
                    ->selectRaw('inspector_nik, count(*) as total')->groupBy('inspector_nik')->pluck('total', 'inspector_nik'),
            ];
        }

        $rows = array_map(function (array $m) use ($targets, $counts, $ref) {
            $t = $targets[$m['grade']];
            $h = (int) ($counts[$t['period']]['hazard'][$m['nik']] ?? 0);
            $i = (int) ($counts[$t['period']]['inspection'][$m['nik']] ?? 0);
            $need = $t['hazard'] + $t['inspection'];
            $got = min($h, $t['hazard']) + min($i, $t['inspection']);

            $end = self::range($t['period'], $ref)[1];

            return $m + [
                'period' => $t['period'], 'period_label' => self::periodLabel($t['period'], $ref),
                'days_left' => today()->lte($end) && today()->gte(self::range($t['period'], $ref)[0]) ? (int) today()->diffInDays($end) : null,
                'target_hazard' => $t['hazard'], 'target_inspection' => $t['inspection'],
                'hazard' => $h, 'inspection' => $i,
                'pct' => $need > 0 ? (int) round($got * 100 / $need) : 100,
                'achieved' => $h >= $t['hazard'] && $i >= $t['inspection'],
            ];
        }, $members);

        usort($rows, fn ($a, $b) => [$a['achieved'], $a['pct'], $a['department'], $a['name']] <=> [$b['achieved'], $b['pct'], $b['department'], $b['name']]);

        return $rows;
    }

    /** Ringkasan per departemen dari baris rows(). @return list<array<string, mixed>> */
    public function byDepartment(array $rows): array
    {
        return collect($rows)->groupBy(fn ($r) => $r['department'] ?: 'Tanpa departemen')->map(function ($g, $dept) {
            $ok = $g->where('achieved', true)->count();

            return [
                'name' => $dept, 'members' => $g->count(), 'achieved' => $ok, 'behind' => $g->count() - $ok,
                'pct' => (int) round($ok * 100 / max(1, $g->count())),
            ];
        })->sortBy([['pct', 'asc'], ['name', 'asc']])->values()->all();
    }

    /** Data grafik: capaian (%) per departemen, atau per anggota bila hanya satu tim/departemen. */
    public function chart(array $rows, bool $byDepartment): array
    {
        $items = $byDepartment
            ? collect($this->byDepartment($rows))->map(fn ($d) => ['label' => $d['name'], 'pct' => $d['pct']])
            : collect($rows)->map(fn ($r) => ['label' => $r['name'], 'pct' => $r['pct']]);

        return [
            'labels' => $items->pluck('label')->take(25)->all(),
            'pct' => $items->pluck('pct')->take(25)->all(),
            'by_department' => $byDepartment,
        ];
    }
}
