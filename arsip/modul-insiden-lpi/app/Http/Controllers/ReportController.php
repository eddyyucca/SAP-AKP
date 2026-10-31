<?php

namespace App\Http\Controllers;

use App\Models\CorrectiveAction;
use App\Models\Hazard;
use App\Models\Incident;
use App\Models\Location;
use App\Services\SafetyStats;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Laporan periodik, ringkasan bulanan untuk dicetak, dan ekspor CSV. */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to, $locationId, $filters] = $this->range($request);
        $stats = new SafetyStats($from, $to, $locationId);

        $types = config('ssap.incident_types');
        $byType = $stats->incidents()->countBy('type');
        $typeRows = collect($types)->map(fn ($t, $key) => [
            'label' => $t['label'], 'color' => $t['color'], 'count' => (int) ($byType[$key] ?? 0),
            'lost_days' => (int) $stats->incidents()->where('type', $key)->sum('lost_days'),
        ])->filter(fn ($r) => $r['count'] > 0)->values();

        $trend = $stats->trend();
        $months = $stats->months();
        $monthRows = [];
        foreach ($months as $i => $month) {
            $monthRows[] = [
                'label' => $month['label'],
                'incidents' => array_sum(array_column(array_column($trend['groups'], 'data'), $i)),
                'hours' => $trend['hours'][$i],
                'ltifr' => $trend['ltifr'][$i],
                'trir' => $trend['trir'][$i],
                'hazard_opened' => $trend['hazard_opened'][$i],
                'hazard_closed' => $trend['hazard_closed'][$i],
            ];
        }

        $locationRows = $this->locationTable($stats);

        return view('ssap.reports.index', [
            'filters' => $filters,
            'from' => $from,
            'to' => $to,
            'locationId' => $locationId,
            'locations' => Location::ordered()->get(['id', 'name']),
            'kpi' => $stats->kpis(),
            'charts' => $stats->charts(),
            'recap' => $stats->recap(),
            'typeRows' => $typeRows,
            'monthRows' => $monthRows,
            'locationRows' => $locationRows,
            'topReporters' => $stats->topReporters(),
        ]);
    }

    /** Ringkasan bulanan satu halaman, siap cetak / simpan PDF. */
    public function monthly(Request $request)
    {
        $data = $request->validate(['month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'], 'location' => ['nullable', 'integer']]);

        $month = Carbon::createFromFormat('Y-m-d', ($data['month'] ?? now()->format('Y-m')).'-01')->startOfDay();
        $locationId = isset($data['location']) ? (int) $data['location'] : null;

        $stats = new SafetyStats($month->copy()->startOfMonth(), $month->copy()->endOfMonth(), $locationId);
        $yearStats = new SafetyStats($month->copy()->startOfYear(), $month->copy()->endOfMonth(), $locationId);

        return view('ssap.reports.monthly', [
            'month' => $month,
            'location' => $locationId ? Location::find($locationId) : null,
            'kpi' => $stats->kpis(),
            'recap' => $stats->recap(),
            'ytd' => $yearStats->kpis(),
            'charts' => $yearStats->charts(),
            'incidents' => Incident::with('location:id,name')->where('status', '!=', 'cancelled')
                ->whereBetween('occurred_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->when($locationId, fn ($q) => $q->where('location_id', $locationId))->orderBy('occurred_at')->get(),
            'hazards' => Hazard::with('location:id,name')->open()
                ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
                ->whereIn('risk_level', ['high', 'extreme'])->orderByDesc('risk_score')->limit(15)->get(),
            'overdue' => CorrectiveAction::overdue()->with('source:id,number,title')->orderBy('due_date')->limit(15)->get(),
            'byLocation' => $this->locationTable($stats),
        ]);
    }

    public function export(Request $request, string $dataset): StreamedResponse
    {
        abort_unless(in_array($dataset, ['incidents', 'hazards', 'actions'], true), 404);

        [$from, $to, $locationId] = $this->range($request);

        [$header, $rows] = match ($dataset) {
            'incidents' => $this->incidentRows($from, $to, $locationId),
            'hazards' => $this->hazardRows($from, $to, $locationId),
            'actions' => $this->actionRows($from, $to, $locationId),
        };

        $filename = "ssap-{$dataset}-".$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM supaya Excel membaca UTF-8
            fputcsv($out, $header, ';');
            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'safeCell'], $row), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0:Carbon,1:Carbon,2:?int,3:array} */
    private function range(Request $request): array
    {
        $filters = $request->validate([
            'preset' => ['nullable', 'in:month,last_month,quarter,year,last_year'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'location' => ['nullable', 'integer'],
        ]);

        $now = now();
        [$from, $to] = match ($filters['preset'] ?? null) {
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            default => [
                isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : $now->copy()->startOfYear(),
                isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : $now->copy()->endOfDay(),
            ],
        };

        $filters['from'] = $from->toDateString();
        $filters['to'] = $to->toDateString();

        return [$from, $to, isset($filters['location']) ? (int) $filters['location'] : null, $filters];
    }

    private function locationTable(SafetyStats $stats): array
    {
        $types = config('ssap.incident_types');
        $names = Location::query()->pluck('name', 'id');
        $hoursByLocation = \App\Models\ManHour::query()
            ->whereRaw('(year * 100 + month) >= ?', [$stats->from->year * 100 + $stats->from->month])
            ->whereRaw('(year * 100 + month) <= ?', [$stats->to->year * 100 + $stats->to->month])
            ->get()->groupBy('location_id')->map->sum('hours');

        $rows = [];
        foreach ($names as $id => $name) {
            $inc = $stats->incidents()->where('location_id', $id);
            $haz = $stats->hazards()->where('location_id', $id);
            if ($inc->isEmpty() && $haz->isEmpty()) {
                continue;
            }
            $hours = (int) ($hoursByLocation[$id] ?? 0);
            $lti = $inc->filter(fn ($i) => $types[$i->type]['lti'] ?? false)->count();
            $rec = $inc->filter(fn ($i) => $types[$i->type]['recordable'] ?? false)->count();
            $rows[] = [
                'name' => $name, 'incidents' => $inc->count(), 'lti' => $lti, 'recordable' => $rec, 'hours' => $hours,
                'ltifr' => SafetyStats::rate($lti, $hours), 'trir' => SafetyStats::rate($rec, $hours),
                'hazards' => $haz->count(), 'hazards_open' => $haz->whereIn('status', ['open', 'in_progress'])->count(),
            ];
        }

        usort($rows, fn ($a, $b) => [$b['incidents'], $b['hazards']] <=> [$a['incidents'], $a['hazards']]);

        return $rows;
    }

    private function incidentRows(Carbon $from, Carbon $to, ?int $locationId): array
    {
        $types = config('ssap.incident_types');
        $rows = Incident::with(['location:id,name', 'persons'])->whereBetween('occurred_at', [$from, $to])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))->orderBy('occurred_at')->get()
            ->map(fn (Incident $i) => [
                $i->number, $i->occurred_at->format('Y-m-d H:i'), $types[$i->type]['label'] ?? $i->type, $i->severity,
                $i->location?->name, $i->area_detail, $i->title, $i->description,
                $i->reporter_name, $i->reporter_department, config("ssap.incident_statuses.{$i->status}.label"),
                $i->injured_count, $i->lost_days, $i->cost_estimate,
                $i->persons->where('role', 'korban')->pluck('name')->implode(', '),
                $i->risk_level ? config("ssap.risk_levels.{$i->risk_level}.label") : '',
                $i->root_cause_category ? config("ssap.root_cause_categories.{$i->root_cause_category}") : '', $i->root_cause,
                $i->investigator_name, $i->closed_at?->format('Y-m-d'),
            ])->all();

        return [['Nomor', 'Waktu kejadian', 'Jenis', 'Keparahan (1-5)', 'Lokasi', 'Detail area', 'Judul', 'Uraian', 'Pelapor', 'Departemen pelapor', 'Status', 'Jumlah korban', 'Hari hilang', 'Estimasi biaya', 'Nama korban', 'Potensi risiko', 'Kategori akar masalah', 'Akar masalah', 'Investigator', 'Tanggal ditutup'], $rows];
    }

    private function hazardRows(Carbon $from, Carbon $to, ?int $locationId): array
    {
        $rows = Hazard::with('location:id,name')->whereBetween('observed_at', [$from, $to])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))->orderBy('observed_at')->get()
            ->map(fn (Hazard $h) => [
                $h->number, $h->observed_at->format('Y-m-d H:i'), $h->categoryLabel(), $h->location?->name, $h->area_detail, $h->title, $h->description,
                $h->likelihood, $h->consequence, $h->risk_score, config("ssap.risk_levels.{$h->risk_level}.label"),
                $h->reporter_name, $h->reporter_department, $h->pic_name, $h->due_date?->format('Y-m-d'),
                config("ssap.hazard_statuses.{$h->status}.label"), $h->closed_at?->format('Y-m-d'), $h->closure_note,
            ])->all();

        return [['Nomor', 'Waktu temuan', 'Kategori', 'Lokasi', 'Detail area', 'Judul', 'Uraian', 'Kemungkinan (1-5)', 'Konsekuensi (1-5)', 'Skor', 'Tingkat risiko', 'Pelapor', 'Departemen pelapor', 'PIC', 'Tenggat', 'Status', 'Tanggal selesai', 'Catatan penutupan'], $rows];
    }

    private function actionRows(Carbon $from, Carbon $to, ?int $locationId): array
    {
        $rows = CorrectiveAction::with('source')->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('due_date')->get()
            ->filter(fn ($a) => ! $locationId || ($a->source && (int) $a->source->location_id === $locationId))
            ->map(fn (CorrectiveAction $a) => [
                $a->source?->number, $a->typeLabel(), $a->description, $a->pic_name, $a->pic_department, $a->due_date->format('Y-m-d'),
                $a->statusMeta()['label'], $a->isOverdue() ? 'Ya' : 'Tidak', $a->completed_at?->format('Y-m-d'), $a->completion_note, $a->verified_by, $a->verified_at?->format('Y-m-d'),
            ])->values()->all();

        return [['Sumber', 'Jenis', 'Tindakan', 'PIC', 'Departemen PIC', 'Tenggat', 'Status', 'Terlambat', 'Tanggal selesai', 'Catatan penyelesaian', 'Diverifikasi oleh', 'Tanggal verifikasi'], $rows];
    }

    /** Cegah injeksi rumus spreadsheet: sel yang diawali = + - @ diberi tanda petik. */
    private function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
