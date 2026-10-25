<?php

namespace App\Services;

use App\Models\Hazard;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\InspectionTemplate;
use App\Models\Location;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Angka dashboard inspeksi rutin: kepatuhan terhadap target, skor, temuan, dan tren. */
class InspectionStats
{
    private ?Collection $inspections = null;

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

    /** @return Collection<int, Inspection> */
    public function inspections(): Collection
    {
        return $this->inspections ??= Inspection::query()
            ->whereBetween('inspected_at', [$this->from, $this->to])
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->get(['id', 'number', 'template_id', 'template_name', 'location_id', 'inspected_at', 'inspector_name', 'total_items', 'ok_count', 'nok_count', 'na_count', 'score']);
    }

    /** Jumlah bulan yang dihitung untuk target: dari awal periode sampai bulan berjalan (bila periode belum selesai). */
    public function monthsCounted(): int
    {
        $end = $this->to->lt(now()) ? Carbon::instance($this->to) : now();
        $start = Carbon::instance($this->from)->startOfMonth();

        return max(1, (int) $start->diffInMonths($end->copy()->startOfMonth()) + 1);
    }

    public function kpis(): array
    {
        $ins = $this->inspections();
        $ok = (int) $ins->sum('ok_count');
        $nok = (int) $ins->sum('nok_count');
        $templates = InspectionTemplate::active()->get(['id', 'target_per_month']);
        $target = (int) $templates->sum('target_per_month') * $this->monthsCounted();

        $itemIds = $ins->pluck('id');
        $hazardIds = InspectionItem::query()->whereIn('inspection_id', $itemIds->all() ?: [0])->whereNotNull('hazard_id')->pluck('hazard_id');
        $hazards = Hazard::withTrashed()->whereIn('id', $hazardIds->all() ?: [0])->get(['id', 'status']);

        return [
            'count' => $ins->count(),
            'target' => $target,
            'compliance' => $target > 0 ? min(100, (int) round($ins->count() * 100 / $target)) : null,
            'avg_score' => $ins->whereNotNull('score')->isEmpty() ? null : round($ins->whereNotNull('score')->avg('score'), 1),
            'items_checked' => $ok + $nok,
            'findings' => $nok,
            'findings_open' => $hazards->whereIn('status', ['open', 'in_progress'])->count(),
            'findings_closed' => $hazards->where('status', 'closed')->count(),
            'findings_close_pct' => $hazards->isEmpty() ? null : round($hazards->where('status', 'closed')->count() * 100 / $hazards->count()),
            'inspectors' => $ins->pluck('inspector_name')->unique()->count(),
        ];
    }

    /** Pencapaian tiap template terhadap targetnya. */
    public function byTemplate(): array
    {
        $months = $this->monthsCounted();
        $done = $this->inspections()->groupBy('template_id');

        return InspectionTemplate::active()->orderBy('name')->get(['id', 'name', 'target_per_month'])->map(function ($t) use ($done, $months) {
            $rows = $done->get($t->id, collect());
            $target = $t->target_per_month * $months;

            return [
                'id' => $t->id, 'name' => $t->name, 'done' => $rows->count(), 'target' => $target,
                'pct' => $target > 0 ? min(100, (int) round($rows->count() * 100 / $target)) : null,
                'score' => $rows->whereNotNull('score')->isEmpty() ? null : round($rows->whereNotNull('score')->avg('score'), 1),
            ];
        })->all();
    }

    /** Jumlah inspeksi dan skor rata-rata per bulan. */
    public function trend(): array
    {
        $cursor = Carbon::instance($this->from)->startOfMonth();
        $end = Carbon::instance($this->to)->startOfMonth();
        $keys = $labels = [];
        while ($cursor->lte($end)) {
            $keys[] = $cursor->format('Y-m');
            $labels[] = $cursor->translatedFormat('M y');
            $cursor->addMonth();
        }
        $keys = array_slice($keys, -36);
        $labels = array_slice($labels, -36);

        $by = $this->inspections()->groupBy(fn ($i) => $i->inspected_at->format('Y-m'));
        $templates = InspectionTemplate::active()->sum('target_per_month');

        return [
            'labels' => $labels,
            'count' => array_map(fn ($k) => $by->get($k, collect())->count(), $keys),
            'target' => array_map(fn () => (int) $templates, $keys),
            'score' => array_map(function ($k) use ($by) {
                $rows = $by->get($k, collect())->whereNotNull('score');

                return $rows->isEmpty() ? null : round($rows->avg('score'), 1);
            }, $keys),
        ];
    }

    public function byLocation(): array
    {
        $names = Location::query()->pluck('name', 'id');
        $rows = $this->inspections()->groupBy('location_id')->map(function ($g, $id) use ($names) {
            $scored = $g->whereNotNull('score');

            return ['name' => $names[$id] ?? '-', 'count' => $g->count(), 'score' => $scored->isEmpty() ? null : round($scored->avg('score'), 1), 'nok' => (int) $g->sum('nok_count')];
        })->sortByDesc('count')->values();

        return ['rows' => $rows->all(), 'labels' => $rows->pluck('name')->all(), 'count' => $rows->pluck('count')->all(), 'score' => $rows->pluck('score')->all()];
    }

    /** Pertanyaan yang paling sering tidak sesuai (akar masalah berulang). */
    public function topFindings(int $limit = 8): array
    {
        return InspectionItem::query()
            ->selectRaw('question, count(*) as total')
            ->where('result', 'nok')
            ->whereIn('inspection_id', $this->inspections()->pluck('id')->all() ?: [0])
            ->groupBy('question')->orderByDesc('total')->limit($limit)->get()
            ->map(fn ($r) => ['question' => $r->question, 'total' => (int) $r->total])->all();
    }

    public function recent(int $limit = 8): Collection
    {
        return Inspection::query()->with('location:id,name')
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->latest('inspected_at')->limit($limit)->get();
    }

    public function charts(): array
    {
        return ['trend' => $this->trend(), 'location' => $this->byLocation(), 'template' => $this->byTemplate()];
    }
}
