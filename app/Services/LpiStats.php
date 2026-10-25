<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\Incident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pemantauan LPI (Laporan Penyelidikan Insiden) per kontraktor di area IUP / IUJP.
 * Cakupan: insiden yang dikaitkan ke kontraktor dalam periode (tanggal kejadian), bukan batal.
 */
class LpiStats
{
    private ?Collection $incidents = null;

    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly ?string $area = null,
        public readonly ?int $contractorId = null,
    ) {
    }

    /** @return Collection<int, Incident> insiden kontraktor (termasuk yang tidak mewajibkan LPI) */
    public function incidents(): Collection
    {
        return $this->incidents ??= Incident::query()
            ->whereNotNull('contractor_id')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('occurred_at', [$this->from, $this->to])
            ->when($this->contractorId, fn ($q) => $q->where('contractor_id', $this->contractorId))
            ->when($this->area, fn ($q) => $q->whereIn('contractor_id', Contractor::query()->where('area_type', $this->area)->select('id')))
            ->get(['id', 'number', 'title', 'type', 'occurred_at', 'created_at', 'contractor_id', 'location_id', 'status', 'lpi_status', 'lpi_due', 'lpi_number', 'lpi_submitted_at', 'lpi_revisions', 'lost_days']);
    }

    /** Insiden yang wajib LPI. */
    public function required(): Collection
    {
        return $this->incidents()->filter(fn ($i) => $i->lpi_status !== null);
    }

    public function kpis(): array
    {
        $req = $this->required();
        $received = $req->filter(fn ($i) => $i->lpi_submitted_at);
        $onTime = $received->filter(fn ($i) => $i->lpiOnTime());
        $overdue = $req->filter(fn ($i) => $i->lpiOverdue());

        return [
            'incidents' => $this->incidents()->count(),
            'required' => $req->count(),
            'received' => $received->count(),
            'received_pct' => $req->isEmpty() ? null : (int) round($received->count() * 100 / $req->count()),
            'on_time' => $onTime->count(),
            'on_time_pct' => $received->isEmpty() ? null : (int) round($onTime->count() * 100 / $received->count()),
            'accepted' => $req->where('lpi_status', 'accepted')->count(),
            'pending' => $req->where('lpi_status', 'pending')->count(),
            'revision' => $req->where('lpi_status', 'revision')->count(),
            'review' => $req->where('lpi_status', 'submitted')->count(),
            'pending_ok' => $req->where('lpi_status', 'pending')->filter(fn ($i) => ! $i->lpiOverdue())->count(),
            'revision_ok' => $req->where('lpi_status', 'revision')->filter(fn ($i) => ! $i->lpiOverdue())->count(),
            'overdue' => $overdue->count(),
            'avg_days' => $received->isEmpty() ? null : round($received->avg(fn ($i) => $i->created_at->startOfDay()->diffInDays($i->lpi_submitted_at, false)), 1),
            'contractors' => $this->incidents()->pluck('contractor_id')->unique()->count(),
        ];
    }

    /**
     * Satu baris per kontraktor (yang punya insiden pada periode), diurutkan dari LPI terlambat terbanyak.
     *
     * @return list<array<string, mixed>>
     */
    public function byContractor(): array
    {
        $contractors = Contractor::query()->get()->keyBy('id');

        return $this->incidents()->groupBy('contractor_id')->map(function (Collection $rows, $id) use ($contractors) {
            $c = $contractors[$id] ?? null;
            $req = $rows->filter(fn ($i) => $i->lpi_status !== null);
            $received = $req->filter(fn ($i) => $i->lpi_submitted_at);
            $onTime = $received->filter(fn ($i) => $i->lpiOnTime());

            return [
                'id' => (int) $id,
                'name' => $c?->name ?? '(dihapus)',
                'area' => $c?->areaMeta()['label'] ?? '-',
                'area_color' => $c?->areaMeta()['color'] ?? '#64748b',
                'incidents' => $rows->count(),
                'lti' => $rows->whereIn('type', ['lost_time', 'fatality'])->count(),
                'required' => $req->count(),
                'received' => $received->count(),
                'accepted' => $req->where('lpi_status', 'accepted')->count(),
                'pending' => $req->where('lpi_status', 'pending')->filter(fn ($i) => ! $i->lpiOverdue())->count(),
                'review' => $req->where('lpi_status', 'submitted')->count(),
                'revision' => $req->where('lpi_status', 'revision')->filter(fn ($i) => ! $i->lpiOverdue())->count(),
                'overdue' => $req->filter(fn ($i) => $i->lpiOverdue())->count(),
                'on_time_pct' => $received->isEmpty() ? null : (int) round($onTime->count() * 100 / $received->count()),
                'received_pct' => $req->isEmpty() ? null : (int) round($received->count() * 100 / $req->count()),
                'avg_days' => $received->isEmpty() ? null : round($received->avg(fn ($i) => $i->created_at->startOfDay()->diffInDays($i->lpi_submitted_at, false)), 1),
            ];
        })->sortBy([['overdue', 'desc'], ['revision', 'desc'], ['incidents', 'desc']])->values()->all();
    }

    /** Ringkasan per jenis area (IUP vs IUJP). */
    public function byArea(): array
    {
        $contractors = Contractor::query()->pluck('area_type', 'id');
        $out = [];

        foreach (config('ssap.contractor_areas') as $key => $meta) {
            $rows = $this->incidents()->filter(fn ($i) => ($contractors[$i->contractor_id] ?? null) === $key);
            $req = $rows->filter(fn ($i) => $i->lpi_status !== null);
            $received = $req->filter(fn ($i) => $i->lpi_submitted_at);

            $out[] = [
                'key' => $key, 'label' => $meta['label'], 'full' => $meta['full'], 'color' => $meta['color'],
                'contractors' => $rows->pluck('contractor_id')->unique()->count(),
                'incidents' => $rows->count(), 'required' => $req->count(), 'received' => $received->count(),
                'overdue' => $req->filter(fn ($i) => $i->lpiOverdue())->count(),
                'on_time_pct' => $received->isEmpty() ? null : (int) round($received->filter(fn ($i) => $i->lpiOnTime())->count() * 100 / $received->count()),
            ];
        }

        return $out;
    }

    /** LPI yang perlu ditindak: terlambat atau perlu revisi dulu, lalu yang paling dekat tenggat. */
    public function worklist(int $limit = 30): Collection
    {
        $contractors = Contractor::query()->get()->keyBy('id');

        return $this->required()
            ->filter(fn ($i) => in_array($i->lpi_status, ['pending', 'revision', 'submitted'], true))
            ->sortBy(fn ($i) => [$i->lpiOverdue() ? 0 : ($i->lpi_status === 'revision' ? 1 : ($i->lpi_status === 'submitted' ? 3 : 2)), $i->lpi_due?->timestamp ?? PHP_INT_MAX])
            ->take($limit)
            ->map(function ($i) use ($contractors) {
                $i->setRelation('contractor', $contractors[$i->contractor_id] ?? null);

                return $i;
            })->values();
    }

    /** Status LPI per kontraktor untuk grafik tumpuk. */
    public function charts(): array
    {
        $rows = collect($this->byContractor())->take(12);
        $kpi = $this->kpis();

        return [
            'contractor' => [
                'labels' => $rows->map(fn ($r) => $r['name'])->all(),
                'series' => [
                    ['label' => 'Disetujui', 'color' => '#16a34a', 'data' => $rows->pluck('accepted')->all()],
                    ['label' => 'Ditinjau', 'color' => '#3b82f6', 'data' => $rows->pluck('review')->all()],
                    ['label' => 'Perlu revisi', 'color' => '#f59e0b', 'data' => $rows->pluck('revision')->all()],
                    ['label' => 'Belum diterima', 'color' => '#94a3b8', 'data' => $rows->pluck('pending')->all()],
                    ['label' => 'Terlambat', 'color' => '#dc2626', 'data' => $rows->pluck('overdue')->all()],
                ],
            ],
            'status' => [
                'labels' => ['Disetujui', 'Ditinjau', 'Perlu revisi', 'Belum diterima', 'Terlambat'],
                'data' => [$kpi['accepted'], $kpi['review'], $kpi['revision_ok'], $kpi['pending_ok'], $kpi['overdue']],
                'colors' => ['#16a34a', '#3b82f6', '#f59e0b', '#94a3b8', '#dc2626'],
            ],
            'ontime' => [
                'labels' => $rows->map(fn ($r) => $r['name'])->all(),
                'data' => $rows->pluck('on_time_pct')->all(),
            ],
        ];
    }
}
