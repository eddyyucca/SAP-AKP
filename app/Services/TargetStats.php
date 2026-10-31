<?php

namespace App\Services;

use App\Models\Hazard;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Pencapaian target pelaporan bulanan per karyawan (grade 3, 4, 5 menurut HRIS).
 *  - Hazard   : laporan hazard yang dibuat sendiri pada bulan itu (bukan ditolak; temuan otomatis dari inspeksi tidak dihitung)
 *  - Inspeksi : inspeksi rutin yang diisi pada bulan itu
 * Tercapai bila hazard >= target hazard DAN inspeksi >= target inspeksi.
 */
class TargetStats
{
    public const GRADES = [3, 4, 5];

    public function __construct(private readonly HrisEmployees $hris)
    {
    }

    /** @return array<int, array{hazard:int,inspection:int}> target per grade (pengaturan admin; bila belum ada memakai config) */
    public static function targets(): array
    {
        $out = [];
        foreach (self::GRADES as $g) {
            $default = config("ssap.grade_targets.$g", ['hazard' => 0, 'inspection' => 0]);
            $out[$g] = [
                'hazard' => (int) Setting::value("target_hazard_$g", (string) $default['hazard']),
                'inspection' => (int) Setting::value("target_inspection_$g", (string) $default['inspection']),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>|null  $niks  batasi ke NIK ini (null = seluruh karyawan ber-grade 3-5)
     * @return list<array<string, mixed>> baris per karyawan; yang belum tercapai di urutan atas
     */
    public function rows(CarbonInterface $month, ?array $niks = null): array
    {
        $members = $this->hris->members(self::GRADES, $niks);
        if (! $members) {
            return [];
        }

        $from = Carbon::instance($month)->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $ids = array_column($members, 'nik');
        $targets = self::targets();

        $fromInspection = InspectionItem::query()->whereNotNull('hazard_id')->pluck('hazard_id');
        $hazards = Hazard::query()->whereIn('reporter_nik', $ids)->where('status', '!=', 'rejected')
            ->whereBetween('observed_at', [$from, $to])->whereNotIn('id', $fromInspection)
            ->selectRaw('reporter_nik, count(*) as total')->groupBy('reporter_nik')->pluck('total', 'reporter_nik');
        $inspections = Inspection::query()->whereIn('inspector_nik', $ids)->whereBetween('inspected_at', [$from, $to])
            ->selectRaw('inspector_nik, count(*) as total')->groupBy('inspector_nik')->pluck('total', 'inspector_nik');

        $rows = array_map(function (array $m) use ($targets, $hazards, $inspections) {
            $t = $targets[$m['grade']];
            $h = (int) ($hazards[$m['nik']] ?? 0);
            $i = (int) ($inspections[$m['nik']] ?? 0);
            $need = $t['hazard'] + $t['inspection'];
            $got = min($h, $t['hazard']) + min($i, $t['inspection']);

            return $m + [
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
