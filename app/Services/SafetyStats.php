<?php

namespace App\Services;

use App\Models\Hazard;
use App\Models\Location;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Angka dashboard dan laporan HAZARD + AKUNTABILITAS (PIC, departemen, tenggat, tindakan).
 * Satu kelas = satu definisi untuk setiap indikator, jadi dashboard, laporan, dan cetakan selalu sama.
 * Pengelompokan dilakukan di PHP supaya tidak bergantung pada fungsi tanggal MySQL/SQLite.
 *
 * Definisi:
 *  Tepat waktu (SLA) = hazard selesai pada atau sebelum tenggat (due_date) / seluruh hazard selesai
 *  Terlambat         = hazard terbuka yang lewat tenggat
 *  Penyelesaian      = hazard selesai / seluruh hazard pada periode
 */
class SafetyStats
{
    private ?Collection $hazards = null;

    private ?Collection $open = null;

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

    /** @return Collection<int, Hazard> hazard (bukan ditolak) yang ditemukan dalam periode */
    public function hazards(): Collection
    {
        return $this->hazards ??= Hazard::query()
            ->where('status', '!=', 'rejected')
            ->whereBetween('observed_at', [$this->from, $this->to])
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->get(['id', 'number', 'title', 'observed_at', 'created_at', 'location_id', 'category', 'reporter_nik', 'reporter_name', 'reporter_department', 'status', 'closed_at', 'due_date', 'pic_nik', 'pic_name', 'pic_department']);
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

    private static function isOpen(Hazard $h): bool
    {
        return in_array($h->status, ['open', 'in_progress'], true);
    }

    private static function isLate(Hazard $h): bool
    {
        return self::isOpen($h) && $h->due_date && $h->due_date->lt(today());
    }

    /** Selesai tepat waktu? Null bila belum selesai atau tanpa tenggat. */
    private static function onTime(Hazard $h): ?bool
    {
        if ($h->status !== 'closed' || ! $h->closed_at || ! $h->due_date) {
            return null;
        }

        return $h->closed_at->copy()->startOfDay()->lte($h->due_date);
    }

    private static function daysToClose(Hazard $h): float
    {
        return round($h->created_at->diffInHours($h->closed_at, false) / 24, 1);
    }

    private static function pct(int|float $part, int|float $whole): ?int
    {
        return $whole > 0 ? (int) round($part * 100 / $whole) : null;
    }

    public function kpis(): array
    {
        $h = $this->hazards();
        $closed = $h->where('status', 'closed');
        $timed = $closed->filter(fn ($x) => self::onTime($x) !== null);
        $openAll = $this->openHazards();

        return [
            'hazards' => $h->count(),
            'open' => $h->filter(fn ($x) => self::isOpen($x))->count(),
            'closed' => $closed->count(),
            'closure_pct' => self::pct($closed->count(), $h->count()),
            'overdue' => $h->filter(fn ($x) => self::isLate($x))->count(),
            'in_progress' => $h->where('status', 'in_progress')->count(),
            'sla_pct' => self::pct($timed->filter(fn ($x) => self::onTime($x))->count(), $timed->count()),
            'avg_days' => $closed->filter(fn ($x) => $x->closed_at)->isEmpty() ? null : round($closed->filter(fn ($x) => $x->closed_at)->avg(fn ($x) => self::daysToClose($x)), 1),
            'tta' => $h->where('category', 'tta')->count(),
            'kta' => $h->where('category', 'kta')->count(),
            // Kondisi terkini (seluruh riwayat, mengikuti filter lokasi)
            'open_now' => $openAll->count(),
            'tta_open_now' => $openAll->where('category', 'tta')->count(),
            'kta_open_now' => $openAll->where('category', 'kta')->count(),
            'overdue_now' => $openAll->filter(fn ($x) => self::isLate($x))->count(),
            'oldest_open_days' => $openAll->isEmpty() ? null : (int) $openAll->max(fn ($x) => $x->created_at->copy()->startOfDay()->diffInDays(today())),

        ];
    }

    /** Hazard yang MASIH TERBUKA, seluruh riwayat (bukan hanya periode), mengikuti filter lokasi. */
    public function openHazards(): Collection
    {
        return $this->open ??= Hazard::query()->open()
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->get(['id', 'number', 'title', 'created_at', 'observed_at', 'location_id', 'category', 'status', 'due_date', 'pic_name', 'pic_department']);
    }

    /** Tren bulanan: dilaporkan (TTA/KTA, bertumpuk) vs diselesaikan, dan penyelesaian tepat waktu. */
    public function trend(): array
    {
        $months = $this->months();
        $keys = array_column($months, 'key');
        $cats = config('ssap.hazard_categories');

        $byCat = [];
        foreach ($cats as $ck => $_) {
            $byCat[$ck] = array_fill_keys($keys, 0);
        }
        $opened = $closed = $onTime = $late = array_fill_keys($keys, 0);

        foreach ($this->hazards() as $hz) {
            $m = $hz->observed_at->format('Y-m');
            if (isset($opened[$m])) {
                $opened[$m]++;
                $byCat[$hz->category][$m] = ($byCat[$hz->category][$m] ?? 0) + 1;
            }
            if ($hz->status === 'closed' && $hz->closed_at) {
                $cm = $hz->closed_at->format('Y-m');
                if (isset($closed[$cm])) {
                    $closed[$cm]++;
                    self::onTime($hz) ? $onTime[$cm]++ : $late[$cm]++;
                }
            }
        }

        return [
            'labels' => array_column($months, 'label'),
            'categories' => collect($cats)->map(fn ($label, $k) => ['label' => $label, 'color' => config("ssap.hazard_category_colors.{$k}"), 'data' => array_values($byCat[$k])])->values()->all(),
            'opened' => array_values($opened),
            'closed' => array_values($closed),
            'sla' => array_map(fn ($k) => self::pct($onTime[$k], $onTime[$k] + $late[$k]), $keys),
        ];
    }

    /** Umur hazard yang masih terbuka. */
    public function aging(): array
    {
        $buckets = config('ssap.aging_buckets');
        $counts = array_fill(0, count($buckets), 0);
        $overdue = array_fill(0, count($buckets), 0);

        foreach ($this->openHazards() as $h) {
            $age = $h->created_at->copy()->startOfDay()->diffInDays(today());
            foreach ($buckets as $i => [$max]) {
                if ($max === null || $age <= $max) {
                    $counts[$i]++;
                    if (self::isLate($h)) {
                        $overdue[$i]++;
                    }
                    break;
                }
            }
        }

        return [
            'labels' => array_column($buckets, 1),
            'data' => $counts,
            'overdue' => $overdue,
            'colors' => ['#38c0b0', '#84cc16', '#eab308', '#f97316', '#dc2626'],
        ];
    }

    /** Komposisi TTA vs KTA. */
    public function byCategory(): array
    {
        $cats = config('ssap.hazard_categories');
        $counts = $this->hazards()->countBy('category');

        return [
            'labels' => array_values($cats),
            'data' => array_map(fn ($k) => (int) ($counts[$k] ?? 0), array_keys($cats)),
            'colors' => array_map(fn ($k) => config("ssap.hazard_category_colors.{$k}"), array_keys($cats)),
        ];
    }

    /** Hazard per lokasi: terbuka vs selesai. */
    public function byLocation(): array
    {
        $names = Location::query()->pluck('name', 'id');
        $rows = $this->hazards()->groupBy('location_id')->map(function ($g, $id) use ($names) {
            $timed = $g->filter(fn ($x) => self::onTime($x) !== null);

            return [
                'name' => $names[$id] ?? '-', 'total' => $g->count(),
                'open' => $g->filter(fn ($x) => self::isOpen($x))->count(), 'closed' => $g->where('status', 'closed')->count(),
                'overdue' => $g->filter(fn ($x) => self::isLate($x))->count(),
                'tta' => $g->where('category', 'tta')->count(), 'kta' => $g->where('category', 'kta')->count(),
                'sla_pct' => self::pct($timed->filter(fn ($x) => self::onTime($x))->count(), $timed->count()),
            ];
        })->sortByDesc('total')->values();

        return ['rows' => $rows->all(), 'labels' => $rows->pluck('name')->all(), 'open' => $rows->pluck('open')->all(), 'closed' => $rows->pluck('closed')->all(),
            'tta' => $rows->pluck('tta')->all(), 'kta' => $rows->pluck('kta')->all()];
    }

    /** Departemen PELAPOR (partisipasi) dan departemen PIC (akuntabilitas penyelesaian). */
    public function byDepartment(): array
    {
        $reported = $this->hazards()->countBy(fn ($h) => $h->reporter_department ?: 'Tidak diketahui')->sortDesc()->take(8);

        return ['labels' => $reported->keys()->all(), 'data' => $reported->values()->all()];
    }

    /**
     * Papan akuntabilitas: kinerja penyelesaian per PIC atau per departemen PIC.
     *
     * @param  'pic'|'department'  $by
     * @return list<array<string, mixed>>
     */
    public function board(string $by = 'pic', int $limit = 12): array
    {
        $assigned = $this->hazards()->filter(fn ($h) => filled($h->pic_name));

        return $assigned->groupBy(fn ($h) => $by === 'pic' ? $h->pic_name : ($h->pic_department ?: 'Tidak diketahui'))->map(function (Collection $g, $name) use ($by) {
            $closed = $g->where('status', 'closed');
            $timed = $closed->filter(fn ($x) => self::onTime($x) !== null);
            $withClose = $closed->filter(fn ($x) => $x->closed_at);

            return [
                'name' => $name,
                'dept' => $by === 'pic' ? ($g->first()->pic_department ?: '-') : null,
                'assigned' => $g->count(),
                'open' => $g->filter(fn ($x) => self::isOpen($x))->count(),
                'overdue' => $g->filter(fn ($x) => self::isLate($x))->count(),
                'closed' => $closed->count(),
                'sla_pct' => self::pct($timed->filter(fn ($x) => self::onTime($x))->count(), $timed->count()),
                'avg_days' => $withClose->isEmpty() ? null : round($withClose->avg(fn ($x) => self::daysToClose($x)), 1),
            ];
        })->sortBy([['overdue', 'desc'], ['open', 'desc'], ['assigned', 'desc']])->take($limit)->values()->all();
    }

    /** Pelapor teraktif (budaya pelaporan). */
    public function topReporters(int $limit = 8): array
    {
        return $this->hazards()->groupBy(fn ($h) => $h->reporter_nik ?: $h->reporter_name)->map(fn (Collection $g) => [
            'name' => $g->first()->reporter_name, 'dept' => $g->first()->reporter_department, 'total' => $g->count(),
            'tta' => $g->where('category', 'tta')->count(), 'kta' => $g->where('category', 'kta')->count(),
        ])->sortByDesc('total')->take($limit)->values()->all();
    }

    /** Sebaran status hazard dalam periode (batang): dilaporkan, diperbaiki, selesai tepat waktu, selesai terlambat, terlambat (terbuka). */
    public function statusChart(): array
    {
        $h = $this->hazards();
        $late = $h->filter(fn ($x) => self::isLate($x))->count();
        $closed = $h->where('status', 'closed');

        $rows = [
            ['Dilaporkan', $h->where('status', 'open')->count() - $h->where('status', 'open')->filter(fn ($x) => self::isLate($x))->count(), '#94a3b8'],
            ['Dalam perbaikan', $h->where('status', 'in_progress')->count() - $h->where('status', 'in_progress')->filter(fn ($x) => self::isLate($x))->count(), '#f59e0b'],
            ['Terlambat (belum selesai)', $late, '#dc2626'],
            ['Selesai tepat waktu', $closed->filter(fn ($x) => self::onTime($x) === true)->count(), '#16a34a'],
            ['Selesai terlambat', $closed->filter(fn ($x) => self::onTime($x) === false)->count(), '#0ea5e9'],
        ];

        return ['labels' => array_column($rows, 0), 'data' => array_column($rows, 1), 'colors' => array_column($rows, 2)];
    }

    /** Hazard terbuka dengan tenggat paling dekat. */
    public function topHazards(int $limit = 8): Collection
    {
        return Hazard::query()->with('location:id,name')->open()
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->orderBy('due_date')->orderBy('id')->limit($limit)->get();
    }

    /** Hazard yang butuh perhatian segera: melewati tenggat. */
    public function attention(int $limit = 8): Collection
    {
        return Hazard::query()->with('location:id,name')->open()
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->whereDate('due_date', '<', today())
            ->orderBy('due_date')->orderBy('id')->limit($limit)->get();
    }

    /** Rekap jenis hazard (TTA/KTA) x status (untuk tabel laporan). @return list<array<string,mixed>> */
    public function categoryTable(): array
    {
        $h = $this->hazards();

        return collect(config('ssap.hazard_categories'))->map(fn ($label, $key) => [
            'key' => $key, 'label' => $label, 'color' => config("ssap.hazard_category_colors.{$key}"),
            'total' => $h->where('category', $key)->count(),
            'open' => $h->where('category', $key)->filter(fn ($x) => self::isOpen($x))->count(),
            'closed' => $h->where('category', $key)->where('status', 'closed')->count(),
            'overdue' => $h->where('category', $key)->filter(fn ($x) => self::isLate($x))->count(),
        ])->values()->all();
    }

    /** Beban PIC: terbuka, terlambat, selesai (batang bertumpuk). */
    public function picChart(int $limit = 8): array
    {
        $rows = collect($this->board('pic', $limit));

        return [
            'labels' => $rows->pluck('name')->all(),
            'open' => $rows->map(fn ($r) => max(0, $r['open'] - $r['overdue']))->all(),
            'overdue' => $rows->pluck('overdue')->all(),
            'closed' => $rows->pluck('closed')->all(),
        ];
    }

    /** Semua data grafik dalam satu larik (disematkan ke halaman sebagai JSON). */
    public function charts(): array
    {
        return [
            'trend' => $this->trend(),
            'aging' => $this->aging(),
            'category' => $this->byCategory(),
            'pic' => $this->picChart(),
            'location' => $this->byLocation(),
            'department' => $this->byDepartment(),
            'status' => $this->statusChart(),
        ];
    }
}
