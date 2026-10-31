<?php

namespace App\Http\Controllers;

use App\Models\CorrectiveAction;
use App\Models\Hazard;
use App\Models\Inspection;
use App\Models\Location;
use App\Services\InspectionStats;
use App\Services\SafetyStats;
use App\Support\Access;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Admin/HSE/Manajer melihat dashboard eksekutif; pelapor melihat ringkasan pribadinya. */
    public function home(Request $request)
    {
        if (! Access::canViewAll()) {
            return $this->personal();
        }

        [$year, $locationId, $filters] = $this->filters($request);
        $hz = SafetyStats::forYear($year, $locationId);
        $ins = InspectionStats::forYear($year, $locationId);

        return view('ssap.dashboard', $filters + [
            'kpi' => $hz->kpis(),
            'ikpi' => $ins->kpis(),
            'charts' => $hz->charts() + ['inspection' => $ins->charts()],
            'attention' => $hz->attention(6),
            'board' => $hz->board('pic', 6),
            'behind' => $ins->behindThisMonth(),
            'recentInspections' => $ins->recent(5),
            'overdueActions' => CorrectiveAction::overdue()->with('source:id,number,title')->orderBy('due_date')->limit(6)->get(),
            'canManage' => Access::canManage(),
        ]);
    }

    /** Dashboard rinci hazard. */
    public function hazards(Request $request)
    {
        [$year, $locationId, $filters] = $this->filters($request);
        $hz = SafetyStats::forYear($year, $locationId);

        return view('ssap.hazards.dashboard', $filters + [
            'kpi' => $hz->kpis(),
            'charts' => $hz->charts(),
            'categoryTable' => $hz->categoryTable(),
            'locationRows' => $hz->byLocation()['rows'],
            'topHazards' => $hz->topHazards(8),
            'topReporters' => $hz->topReporters(8),
        ]);
    }

    /** Papan akuntabilitas: siapa yang menyelesaikan tepat waktu, siapa yang menunggak. */
    public function accountability(Request $request)
    {
        [$year, $locationId, $filters] = $this->filters($request);
        $hz = SafetyStats::forYear($year, $locationId);
        $ins = InspectionStats::forYear($year, $locationId);

        return view('ssap.accountability', $filters + [
            'kpi' => $hz->kpis(),
            'ikpi' => $ins->kpis(),
            'picBoard' => $hz->board('pic', 25),
            'deptBoard' => $hz->board('department', 15),
            'inspectors' => $ins->inspectorBoard(12),
            'behind' => $ins->behindThisMonth(),
            'reporters' => $hz->topReporters(10),
            'overdueActions' => CorrectiveAction::overdue()->with('source:id,number,title')->orderBy('due_date')->limit(15)->get(),
        ]);
    }

    /** @return array{0:int,1:?int,2:array} */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'location' => ['nullable', 'integer'],
        ]);

        $year = (int) ($data['year'] ?? now()->year);
        $locationId = isset($data['location']) ? (int) $data['location'] : null;

        $first = (int) substr((string) (min(array_filter([Hazard::min('observed_at'), Inspection::min('inspected_at'), $year.'-01-01']))), 0, 4);

        return [$year, $locationId, [
            'year' => $year,
            'years' => range(now()->year, max($first, now()->year - 6)),
            'locationId' => $locationId,
            'locations' => Location::ordered()->get(['id', 'name']),
        ]];
    }

    private function personal()
    {
        $user = portal_user();
        $nik = $user->employee_no ?: '__none__';

        $hazards = Hazard::query()->visibleTo($user)->where(fn ($q) => $q->where('created_by_id', (string) $user->id)->orWhere('reporter_nik', $nik));
        $inspections = Inspection::query()->visibleTo($user);
        $myActions = CorrectiveAction::query()->where('pic_nik', $nik);

        $mine = collect(app(\App\Services\TargetStats::class)->rows(now(), [$nik]))->first();

        return view('ssap.my', [
            'mine' => $mine,
            'counts' => [
                'hazards' => (clone $hazards)->count(),
                'hazards_open' => (clone $hazards)->open()->count(),
                'inspections' => (clone $inspections)->whereMonth('inspected_at', now()->month)->whereYear('inspected_at', now()->year)->count(),
                'actions' => (clone $myActions)->pending()->count(),
                'to_verify' => CorrectiveAction::where('status', 'done')->where('source_type', 'hazard')->whereIn('source_id', (clone $hazards)->select('id'))->count(),
                'overdue' => (clone $myActions)->overdue()->count(),
            ],
            'recentHazards' => (clone $hazards)->with('location:id,name')->latest('observed_at')->limit(6)->get(),
            'recentInspections' => (clone $inspections)->with('location:id,name')->latest('inspected_at')->limit(5)->get(),
            'actions' => (clone $myActions)->pending()->with('source:id,number,title')->orderBy('due_date')->limit(8)->get(),
        ]);
    }
}
