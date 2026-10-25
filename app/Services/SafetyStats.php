<?php

namespace App\Services;

use App\Models\CorrectiveAction;
use App\Models\Hazard;
use App\Models\Incident;
use App\Models\Location;
use App\Models\ManHour;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Semua angka dashboard dan laporan. Satu kelas = satu definisi untuk setiap indikator, jadi dashboard, laporan,
 * dan cetakan selalu sama. Pengelompokan dilakukan di PHP supaya tidak bergantung pada fungsi tanggal MySQL/SQLite.
 *
 * Definisi (standar di halaman "Standar Pelaporan"):
 *  LTIFR = kasus LTI (termasuk fatal) x 1.000.000 / jam kerja
 *  TRIR  = kasus recordable (fatal, LTI, RWC, MTC) x 1.000.000 / jam kerja
 *  SR    = hari kerja hilang x 1.000.000 / jam kerja
 */
class SafetyStats
{
    private ?Collection $incidents = null;

    private ?Collection $hazards = null;

    public function __construct(
        public readonly CarbonInterface $from,
        public readonly CarbonInterface $to,
        public readonly ?int $locationId = null,
    ) {
    }

    public static function forYear(int $year, ?int $locationId = null): self
    {
        return new self(Carbon::create($year, 1, 1)->startOfDay(), Carbon::create($year, 12, 31)->endOfDay(), $locationId);
    }

    /** @return Collection<int, Incident> insiden (bukan batal) dalam periode */
    public function incidents(): Collection
    {
        return $this->incidents ??= Incident::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('occurred_at', [$this->from, $this->to])
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->get(['id', 'number', 'type', 'severity', 'occurred_at', 'created_at', 'location_id', 'status', 'lost_days', 'injured_count', 'reporter_nik', 'reporter_name', 'reporter_department', 'closed_at', 'investigation_due', 'root_cause_category']);
    }

    /** @return Collection<int, Hazard> hazard (bukan ditolak) dalam periode */
    public function hazards(): Collection
    {
        return $this->hazards ??= Hazard::query()
            ->where('status', '!=', 'rejected')
            ->whereBetween('observed_at', [$this->from, $this->to])
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->get(['id', 'number', 'observed_at', 'created_at', 'location_id', 'category', 'likelihood', 'consequence', 'risk_score', 'risk_level', 'reporter_nik', 'reporter_name', 'reporter_department', 'status', 'closed_at', 'due_date']);
    }

    /** Bulan-bulan dalam periode (maks. 36 terakhir). @return list<array{key:string,label:string}> */
    public function months(): array
    {
        $cursor = Carbon::instance($this->from)->startOfMonth();
        $end = Carbon::instance($this->to)->startOfMonth();
        $months = [];

        while ($cursor->lte($end)) {
            $months[] = ['key' => $cursor->format('Y-m'), 'label' => $cursor->translatedFormat('M y')];
            $cursor->addMonth();
        }

        return array_slice($months, -36);
    }

    /** Jam kerja per bulan ("2026-03" => 120000). @return array<string,int> */
    public function manHoursByMonth(): array
    {
        $rows = ManHour::query()
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->where(function ($q) {
                $q->where('year', '>', $this->from->year)->orWhere(fn ($w) => $w->where('year', $this->from->year)->where('month', '>=', $this->from->month));
            })
            ->where(function ($q) {
                $q->where('year', '<', $this->to->year)->orWhere(fn ($w) => $w->where('year', $this->to->year)->where('month', '<=', $this->to->month));
            })
            ->get();

        $by = [];
        foreach ($rows as $row) {
            $key = sprintf('%04d-%02d', $row->year, $row->month);
            $by[$key] = ($by[$key] ?? 0) + (int) $row->hours;
        }

        return $by;
    }

    public static function rate(int|float $count, int|float $hours): ?float
    {
        return $hours > 0 ? round($count * config('ssap.rate_base') / $hours, 2) : null;
    }

    public function kpis(): array
    {
        $types = config('ssap.incident_types');
        $incidents = $this->incidents();
        $hazards = $this->hazards();
        $hours = array_sum($this->manHoursByMonth());

        $lti = $incidents->filter(fn ($i) => $types[$i->type]['lti'] ?? false)->count();
        $fatal = $incidents->where('type', 'fatality')->count();
        $recordable = $incidents->filter(fn ($i) => $types[$i->type]['recordable'] ?? false)->count();
        $lostDays = (int) $incidents->sum('lost_days');
        $nearMiss = $incidents->where('type', 'near_miss')->count();

        $closedHazards = $hazards->where('status', 'closed');
        $actions = $this->actionsQuery()->get(['id', 'status', 'due_date', 'completed_at', 'verified_at']);

        $closedIncidents = $incidents->where('status', 'closed')->filter(fn ($i) => $i->closed_at);
        $reportedLate = $incidents->filter(fn ($i) => $i->created_at && $i->occurred_at && $i->occurred_at->diffInHours($i->created_at, false) > ($this->limit($i->type)))->count();

        return [
            'incidents' => $incidents->count(),
            'incidents_open' => $incidents->whereNotIn('status', ['closed'])->count(),
            'near_miss' => $nearMiss,
            'near_miss_ratio' => $recordable > 0 ? round($nearMiss / $recordable, 1) : null,
            'lti' => $lti,
            'fatal' => $fatal,
            'recordable' => $recordable,
            'injured' => (int) $incidents->sum('injured_count'),
            'lost_days' => $lostDays,
            'man_hours' => $hours,
            'ltifr' => self::rate($lti, $hours),
            'trir' => self::rate($recordable, $hours),
            'sr' => self::rate($lostDays, $hours),
            'days_without_lti' => $this->daysWithoutLti(),
            'investigation_overdue' => $incidents->filter(fn ($i) => in_array($i->status, ['submitted', 'investigating'], true) && $i->investigation_due && $i->investigation_due->lt(today()))->count(),
            'avg_close_days' => $closedIncidents->isEmpty() ? null : round($closedIncidents->avg(fn ($i) => $i->created_at->diffInHours($i->closed_at, false) / 24), 1),
            'reported_late' => $reportedLate,
            'reported_late_pct' => $incidents->isEmpty() ? null : round($reportedLate * 100 / $incidents->count()),

            'hazards' => $hazards->count(),
            'hazards_open' => $hazards->whereIn('status', ['open', 'in_progress'])->count(),
            'hazards_closed' => $closedHazards->count(),
            'hazards_high' => $hazards->whereIn('risk_level', ['high', 'extreme'])->whereIn('status', ['open', 'in_progress'])->count(),
            'hazards_overdue' => $hazards->filter(fn ($h) => in_array($h->status, ['open', 'in_progress'], true) && $h->due_date && $h->due_date->lt(today()))->count(),
            'hazard_close_pct' => $hazards->isEmpty() ? null : round($closedHazards->count() * 100 / $hazards->count()),
            'hazard_avg_days' => $closedHazards->filter(fn ($h) => $h->closed_at)->isEmpty() ? null : round($closedHazards->filter(fn ($h) => $h->closed_at)->avg(fn ($h) => $h->created_at->diffInHours($h->closed_at, false) / 24), 1),

            'actions' => $actions->count(),
            'actions_pending' => $actions->whereIn('status', ['open', 'in_progress', 'done'])->count(),
            'actions_overdue' => $actions->filter(fn ($a) => in_array($a->status, ['open', 'in_progress'], true) && $a->due_date->lt(today()))->count(),
            'actions_done_pct' => $actions->isEmpty() ? null : round($actions->where('status', 'verified')->count() * 100 / $actions->count()),
        ];
    }

    private function limit(string $type): int
    {
        $limits = config('ssap.notify_hours');

        return (int) ($limits[$type] ?? $limits['default']);
    }

    /** Hari sejak LTI/fatal terakhir (seluruh riwayat, mengikuti filter lokasi). Null = belum pernah ada. */
    public function daysWithoutLti(): ?int
    {
        $ltiTypes = collect(config('ssap.incident_types'))->filter(fn ($t) => $t['lti'])->keys()->all();

        $last = Incident::query()
            ->where('status', '!=', 'cancelled')
            ->whereIn('type', $ltiTypes)
            ->where('occurred_at', '<=', now())
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->max('occurred_at');

        return $last ? (int) Carbon::parse($last)->startOfDay()->diffInDays(today()) : null;
    }

    /** Tren bulanan jumlah kejadian per kelompok + laju kecelakaan per bulan. */
    public function trend(): array
    {
        $months = $this->months();
        $groups = config('ssap.incident_groups');
        $types = config('ssap.incident_types');
        $hours = $this->manHoursByMonth();

        $series = [];
        foreach ($groups as $key => $group) {
            $series[$key] = array_fill_keys(array_column($months, 'key'), 0);
        }

        $lti = $recordable = $lost = array_fill_keys(array_column($months, 'key'), 0);

        foreach ($this->incidents() as $incident) {
            $month = $incident->occurred_at->format('Y-m');
            if (! isset($series['near_miss'][$month])) {
                continue;
            }
            $meta = $types[$incident->type] ?? null;
            $series[$meta['group'] ?? 'asset'][$month]++;
            $lti[$month] += ($meta['lti'] ?? false) ? 1 : 0;
            $recordable[$month] += ($meta['recordable'] ?? false) ? 1 : 0;
            $lost[$month] += (int) $incident->lost_days;
        }

        $hazardOpened = $hazardClosed = array_fill_keys(array_column($months, 'key'), 0);
        foreach ($this->hazards() as $hazard) {
            $opened = $hazard->observed_at->format('Y-m');
            if (isset($hazardOpened[$opened])) {
                $hazardOpened[$opened]++;
            }
            if ($hazard->status === 'closed' && $hazard->closed_at) {
                $closed = $hazard->closed_at->format('Y-m');
                if (isset($hazardClosed[$closed])) {
                    $hazardClosed[$closed]++;
                }
            }
        }

        return [
            'labels' => array_column($months, 'label'),
            'groups' => collect($groups)->map(fn ($g, $key) => ['label' => $g['label'], 'color' => $g['color'], 'data' => array_values($series[$key])])->values()->all(),
            'ltifr' => array_map(fn ($m) => self::rate($lti[$m['key']], $hours[$m['key']] ?? 0), $months),
            'trir' => array_map(fn ($m) => self::rate($recordable[$m['key']], $hours[$m['key']] ?? 0), $months),
            'hours' => array_map(fn ($m) => (int) ($hours[$m['key']] ?? 0), $months),
            'hazard_opened' => array_values($hazardOpened),
            'hazard_closed' => array_values($hazardClosed),
        ];
    }

    /** @return array{labels:list<string>,data:list<int>,colors:list<string>} */
    public function byType(): array
    {
        $counts = $this->incidents()->countBy('type');
        $labels = $data = $colors = [];

        foreach (config('ssap.incident_types') as $key => $type) {
            if (($counts[$key] ?? 0) > 0) {
                $labels[] = $type['short'];
                $data[] = $counts[$key];
                $colors[] = $type['color'];
            }
        }

        return compact('labels', 'data', 'colors');
    }

    public function bySeverity(): array
    {
        $counts = $this->incidents()->countBy('severity');
        $labels = $data = $colors = [];

        foreach (config('ssap.severity') as $level => $meta) {
            $labels[] = $level.' - '.$meta['label'];
            $data[] = (int) ($counts[$level] ?? 0);
            $colors[] = $meta['color'];
        }

        return compact('labels', 'data', 'colors');
    }

    /** Insiden dan hazard per lokasi, diurutkan dari yang terbanyak. */
    public function byLocation(): array
    {
        $names = Location::query()->pluck('name', 'id');
        $incidents = $this->incidents()->countBy('location_id');
        $hazards = $this->hazards()->countBy('location_id');

        $rows = collect($names)->map(fn ($name, $id) => [
            'name' => $name, 'incidents' => (int) ($incidents[$id] ?? 0), 'hazards' => (int) ($hazards[$id] ?? 0),
        ])->filter(fn ($r) => $r['incidents'] + $r['hazards'] > 0)
            ->sortByDesc(fn ($r) => $r['incidents'] * 3 + $r['hazards'])->values();

        return [
            'labels' => $rows->pluck('name')->all(),
            'incidents' => $rows->pluck('incidents')->all(),
            'hazards' => $rows->pluck('hazards')->all(),
        ];
    }

    /** Departemen paling banyak terlibat (korban/pelapor insiden dan pelapor hazard) - 8 teratas. */
    public function byDepartment(): array
    {
        $incidents = $this->incidents()->countBy(fn ($i) => $i->reporter_department ?: 'Tidak diketahui');
        $hazards = $this->hazards()->countBy(fn ($h) => $h->reporter_department ?: 'Tidak diketahui');

        $rows = collect($incidents->keys()->merge($hazards->keys())->unique())
            ->map(fn ($d) => ['name' => $d, 'incidents' => (int) ($incidents[$d] ?? 0), 'hazards' => (int) ($hazards[$d] ?? 0)])
            ->sortByDesc(fn ($r) => $r['incidents'] + $r['hazards'])->take(8)->values();

        return [
            'labels' => $rows->pluck('name')->all(),
            'incidents' => $rows->pluck('incidents')->all(),
            'hazards' => $rows->pluck('hazards')->all(),
        ];
    }

    /** Pelapor teraktif (budaya pelaporan): 8 teratas dari insiden + hazard. */
    public function topReporters(): array
    {
        $rows = $this->incidents()->map(fn ($i) => ['key' => $i->reporter_nik ?: $i->reporter_name, 'name' => $i->reporter_name, 'dept' => $i->reporter_department, 'type' => 'i'])
            ->concat($this->hazards()->map(fn ($h) => ['key' => $h->reporter_nik ?: $h->reporter_name, 'name' => $h->reporter_name, 'dept' => $h->reporter_department, 'type' => 'h']));

        return $rows->groupBy('key')->map(fn ($g) => [
            'name' => $g->first()['name'],
            'dept' => $g->first()['dept'],
            'total' => $g->count(),
            'incidents' => $g->where('type', 'i')->count(),
            'hazards' => $g->where('type', 'h')->count(),
        ])->sortByDesc('total')->take(8)->values()->all();
    }

    public function hazardByCategory(): array
    {
        $counts = $this->hazards()->countBy('category')->sortDesc();
        $labels = config('ssap.hazard_categories');

        return [
            'labels' => $counts->keys()->map(fn ($k) => $labels[$k] ?? $k)->all(),
            'data' => $counts->values()->all(),
        ];
    }

    public function hazardByLevel(): array
    {
        $counts = $this->hazards()->countBy('risk_level');
        $levels = config('ssap.risk_levels');

        return [
            'labels' => array_column($levels, 'label'),
            'data' => array_map(fn ($k) => (int) ($counts[$k] ?? 0), array_keys($levels)),
            'colors' => array_column($levels, 'color'),
        ];
    }

    /** Peta panas risiko 5x5 untuk hazard yang MASIH TERBUKA (seluruh riwayat, mengikuti filter lokasi). */
    public function heatmap(): array
    {
        $open = Hazard::query()->open()
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->get(['likelihood', 'consequence']);

        $cells = [];
        for ($l = 5; $l >= 1; $l--) {
            for ($c = 1; $c <= 5; $c++) {
                $score = $l * $c;
                $level = \App\Support\Risk::level($score);
                $cells[] = [
                    'l' => $l, 'c' => $c, 'score' => $score, 'level' => $level,
                    'count' => $open->where('likelihood', $l)->where('consequence', $c)->count(),
                ];
            }
        }

        return ['cells' => $cells, 'total' => $open->count()];
    }

    public function rootCauses(): array
    {
        $labels = config('ssap.root_cause_categories');
        $counts = $this->incidents()->filter(fn ($i) => filled($i->root_cause_category))->countBy('root_cause_category')->sortDesc();

        return [
            'labels' => $counts->keys()->map(fn ($k) => $labels[$k] ?? $k)->all(),
            'data' => $counts->values()->all(),
        ];
    }

    /** Status tindakan korektif untuk insiden/hazard dalam periode. */
    public function actionStatus(): array
    {
        $actions = $this->actionsQuery()->get(['status', 'due_date']);

        $overdue = $actions->filter(fn ($a) => in_array($a->status, ['open', 'in_progress'], true) && $a->due_date->lt(today()))->count();
        $statuses = config('ssap.action_statuses');
        $labels = $data = $colors = [];
        $palette = ['open' => '#94a3b8', 'in_progress' => '#f59e0b', 'done' => '#3b82f6', 'verified' => '#16a34a'];

        foreach ($statuses as $key => $meta) {
            $labels[] = $meta['label'];
            $data[] = $actions->where('status', $key)->count();
            $colors[] = $palette[$key];
        }

        $labels[] = 'Terlambat';
        $data[] = $overdue;
        $colors[] = '#dc2626';

        return compact('labels', 'data', 'colors');
    }

    /** Insiden yang butuh perhatian: investigasi terlambat/belum ada investigator, berat yang masih terbuka. */
    public function attention(int $limit = 6): Collection
    {
        return Incident::query()->with('location:id,name')
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->orderByRaw("case when type in ('fatality','lost_time') then 0 else 1 end")
            ->orderBy('investigation_due')
            ->limit($limit)->get();
    }

    /** Hazard risiko tertinggi yang masih terbuka. */
    public function topHazards(int $limit = 6): Collection
    {
        return Hazard::query()->with('location:id,name')->open()
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->orderByDesc('risk_score')->orderBy('due_date')->limit($limit)->get();
    }

    /** Kueri tindakan korektif yang bersumber dari insiden/hazard dalam periode dan lokasi yang sama. */
    private function actionsQuery()
    {
        $incidentIds = $this->incidents()->pluck('id')->all();
        $hazardIds = $this->hazards()->pluck('id')->all();

        return CorrectiveAction::query()->where(function ($q) use ($incidentIds, $hazardIds) {
            $q->where(fn ($w) => $w->where('source_type', 'incident')->whereIn('source_id', $incidentIds ?: [0]))
                ->orWhere(fn ($w) => $w->where('source_type', 'hazard')->whereIn('source_id', $hazardIds ?: [0]));
        });
    }

    /** Semua data grafik dalam satu larik (disematkan ke halaman sebagai JSON). */
    public function charts(): array
    {
        return [
            'trend' => $this->trend(),
            'type' => $this->byType(),
            'severity' => $this->bySeverity(),
            'location' => $this->byLocation(),
            'department' => $this->byDepartment(),
            'hazardCategory' => $this->hazardByCategory(),
            'hazardLevel' => $this->hazardByLevel(),
            'rootCause' => $this->rootCauses(),
            'actions' => $this->actionStatus(),
        ];
    }
}
