<?php

namespace App\Http\Controllers;

use App\Models\Hazard;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\Location;
use App\Services\InspectionStats;
use App\Services\SafetyStats;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Laporan periodik hazard & inspeksi, ringkasan bulanan untuk dicetak, dan ekspor CSV. */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to, $locationId, $filters] = $this->range($request);
        $hz = new SafetyStats($from, $to, $locationId);
        $ins = new InspectionStats($from, $to, $locationId);

        $hTrend = $hz->trend();
        $iTrend = $ins->trend();
        $months = $hz->months();
        $monthRows = [];
        foreach ($months as $i => $m) {
            $monthRows[] = [
                'label' => $m['label'],
                'opened' => $hTrend['opened'][$i], 'closed' => $hTrend['closed'][$i], 'sla' => $hTrend['sla'][$i],
                'inspections' => $iTrend['count'][$i] ?? 0, 'target' => $iTrend['target'][$i] ?? 0, 'score' => $iTrend['score'][$i] ?? null,
            ];
        }

        return view('ssap.reports.index', [
            'filters' => $filters,
            'from' => $from,
            'to' => $to,
            'locationId' => $locationId,
            'locations' => Location::ordered()->get(['id', 'name']),
            'kpi' => $hz->kpis(),
            'ikpi' => $ins->kpis(),
            'charts' => $hz->charts() + ['inspection' => $ins->charts()],
            'categoryTable' => $hz->categoryTable(),
            'locationRows' => $this->locationRows($hz, $ins),
            'monthRows' => $monthRows,
            'templateRows' => $ins->byTemplate(),
            'topFindings' => $ins->topFindings(10),
            'picBoard' => $hz->board('pic', 15),
            'deptBoard' => $hz->board('department', 12),
            'inspectors' => $ins->inspectorBoard(10),
            'topReporters' => $hz->topReporters(8),
        ]);
    }

    /** Ringkasan bulanan satu halaman (hazard + inspeksi + akuntabilitas), siap cetak / simpan PDF. */
    public function monthly(Request $request)
    {
        $data = $request->validate(['month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'], 'location' => ['nullable', 'integer']]);

        $month = Carbon::createFromFormat('Y-m-d', ($data['month'] ?? now()->format('Y-m')).'-01')->startOfDay();
        $locationId = isset($data['location']) ? (int) $data['location'] : null;
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $hz = new SafetyStats($start, $end, $locationId);
        $hzYtd = new SafetyStats($month->copy()->startOfYear(), $end, $locationId);
        $ins = new InspectionStats($start, $end, $locationId);
        $insYtd = new InspectionStats($month->copy()->startOfYear(), $end, $locationId);

        return view('ssap.reports.monthly', [
            'month' => $month,
            'location' => $locationId ? Location::find($locationId) : null,
            'kpi' => $hz->kpis(),
            'ytd' => $hzYtd->kpis(),
            'ikpi' => $ins->kpis(),
            'iytd' => $insYtd->kpis(),
            'charts' => $hzYtd->charts() + ['inspection' => $insYtd->charts()],
            'categoryTable' => $hz->categoryTable(),
            'openList' => Hazard::with('location:id,name')->open()
                ->when($locationId, fn ($q) => $q->where('location_id', $locationId))->orderBy('due_date')->limit(15)->get(),
            'overdue' => $hz->attention(15),
            'templateRows' => $ins->byTemplate(),
            'topFindings' => $ins->topFindings(8),
            'picBoard' => $hz->board('pic', 10),
            'locationRows' => $this->locationRows($hz, $ins),
            'behind' => $ins->behindThisMonth(),
        ]);
    }

    public function export(Request $request, string $dataset): StreamedResponse
    {
        abort_unless(in_array($dataset, ['hazards', 'inspections', 'findings'], true), 404);

        [$from, $to, $locationId] = $this->range($request);

        [$header, $rows] = match ($dataset) {
            'hazards' => $this->hazardRows($from, $to, $locationId),
            'inspections' => $this->inspectionRows($from, $to, $locationId),
            'findings' => $this->findingRows($from, $to, $locationId),
        };

        $filename = "sap-{$dataset}-".$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

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

    /** Gabungan hazard + inspeksi per lokasi. */
    private function locationRows(SafetyStats $hz, InspectionStats $ins): array
    {
        $byHazard = collect($hz->byLocation()['rows'])->keyBy('name');
        $byInsp = collect($ins->byLocation()['rows'])->keyBy('name');

        return $byHazard->keys()->merge($byInsp->keys())->unique()->map(fn ($name) => [
            'name' => $name,
            'hazards' => $byHazard[$name]['total'] ?? 0, 'open' => $byHazard[$name]['open'] ?? 0, 'overdue' => $byHazard[$name]['overdue'] ?? 0,
            'tta' => $byHazard[$name]['tta'] ?? 0, 'kta' => $byHazard[$name]['kta'] ?? 0, 'sla_pct' => $byHazard[$name]['sla_pct'] ?? null,
            'inspections' => $byInsp[$name]['count'] ?? 0, 'score' => $byInsp[$name]['score'] ?? null, 'findings' => $byInsp[$name]['nok'] ?? 0,
        ])->sortByDesc(fn ($r) => $r['hazards'] + $r['inspections'])->values()->all();
    }

    private function hazardRows(Carbon $from, Carbon $to, ?int $locationId): array
    {
        $rows = Hazard::with('location:id,name')->whereBetween('observed_at', [$from, $to])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))->orderBy('observed_at')->get()
            ->map(fn (Hazard $h) => [
                $h->number, $h->observed_at->format('Y-m-d H:i'), $h->categoryLabel(), $h->location?->name, $h->area_detail, $h->description,
                $h->reporter_name, $h->reporter_department, $h->pic_name, $h->pic_department, $h->due_date?->format('Y-m-d'),
                config("ssap.hazard_statuses.{$h->status}.label"), $h->closed_at?->format('Y-m-d'),
                $h->status === 'closed' && $h->closed_at && $h->due_date ? ($h->closed_at->copy()->startOfDay()->lte($h->due_date) ? 'Ya' : 'Tidak') : '',
                $h->isOverdue() ? 'Ya' : 'Tidak', $h->closure_note,
            ])->all();

        return [['Nomor', 'Waktu temuan', 'Jenis (TTA/KTA)', 'Lokasi', 'Detail area', 'Keterangan', 'Pelapor', 'Departemen pelapor', 'PIC', 'Departemen PIC', 'Tenggat', 'Status', 'Tanggal selesai', 'Selesai tepat waktu', 'Terlambat (terbuka)', 'Catatan penutupan'], $rows];
    }

    private function inspectionRows(Carbon $from, Carbon $to, ?int $locationId): array
    {
        $rows = Inspection::with('location:id,name')->whereBetween('inspected_at', [$from, $to])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))->orderBy('inspected_at')->get()
            ->map(fn (Inspection $i) => [
                $i->number, $i->inspected_at->format('Y-m-d H:i'), $i->template_name, $i->location?->name, $i->area_detail,
                $i->inspector_name, $i->inspector_department, $i->total_items, $i->ok_count, $i->nok_count, $i->na_count,
                $i->score === null ? '' : number_format($i->score, 1, ',', ''), $i->scoreMeta()['label'], $i->notes,
            ])->all();

        return [['Nomor', 'Waktu inspeksi', 'Template', 'Lokasi', 'Detail area', 'Inspektor', 'Departemen inspektor', 'Jumlah butir', 'Sesuai', 'Tidak sesuai', 'N/A', 'Skor (%)', 'Kondisi', 'Catatan'], $rows];
    }

    /** Temuan inspeksi (butir tidak sesuai) beserta hazard yang terbentuk. */
    private function findingRows(Carbon $from, Carbon $to, ?int $locationId): array
    {
        $rows = InspectionItem::query()->where('result', 'nok')
            ->whereHas('inspection', fn ($q) => $q->whereBetween('inspected_at', [$from, $to])->when($locationId, fn ($w) => $w->where('location_id', $locationId)))
            ->with(['inspection.location:id,name', 'hazard:id,number,status'])->get()
            ->sortBy(fn ($i) => $i->inspection->inspected_at)->map(fn (InspectionItem $i) => [
                $i->inspection->number, $i->inspection->inspected_at->format('Y-m-d'), $i->inspection->template_name, $i->inspection->location?->name,
                $i->question, $i->note, $i->hazard?->number,
                $i->hazard ? config("ssap.hazard_statuses.{$i->hazard->status}.label") : '',
            ])->values()->all();

        return [['Nomor inspeksi', 'Tanggal', 'Template', 'Lokasi', 'Butir', 'Temuan', 'Nomor hazard', 'Status hazard'], $rows];
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
