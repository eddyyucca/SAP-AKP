<?php

namespace App\Http\Controllers;

use App\Models\CorrectiveAction;
use App\Models\Hazard;
use App\Models\Incident;
use App\Models\Location;
use App\Services\SafetyStats;
use App\Support\Access;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Admin/HSE/Manajer melihat dashboard keseluruhan; pelapor melihat ringkasan miliknya. */
    public function home(Request $request)
    {
        return Access::canViewAll() ? $this->overview($request) : $this->personal();
    }

    private function overview(Request $request)
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'location' => ['nullable', 'integer'],
        ]);

        $year = (int) ($filters['year'] ?? now()->year);
        $locationId = isset($filters['location']) ? (int) $filters['location'] : null;
        $stats = SafetyStats::forYear($year, $locationId);

        $first = min(array_filter([
            Incident::min('occurred_at') ? (int) substr((string) Incident::min('occurred_at'), 0, 4) : null,
            Hazard::min('observed_at') ? (int) substr((string) Hazard::min('observed_at'), 0, 4) : null,
            $year,
        ]));

        return view('ssap.dashboard', [
            'year' => $year,
            'years' => range(now()->year, max($first, now()->year - 6)),
            'locationId' => $locationId,
            'locations' => Location::ordered()->get(['id', 'name']),
            'kpi' => $stats->kpis(),
            'charts' => $stats->charts(),
            'heatmap' => $stats->heatmap(),
            'attention' => $stats->attention(),
            'topHazards' => $stats->topHazards(),
            'topReporters' => $stats->topReporters(),
            'overdueActions' => CorrectiveAction::overdue()->with('source:id,number,title')->orderBy('due_date')->limit(6)->get(),
            'canManage' => Access::canManage(),
        ]);
    }

    private function personal()
    {
        $user = portal_user();
        $nik = $user->employee_no ?: '__none__';

        $incidents = Incident::query()->visibleTo($user)->where(fn ($q) => $q->where('created_by_id', (string) $user->id)->orWhere('reporter_nik', $nik));
        $hazards = Hazard::query()->visibleTo($user)->where(fn ($q) => $q->where('created_by_id', (string) $user->id)->orWhere('reporter_nik', $nik));
        $myActions = CorrectiveAction::query()->where('pic_nik', $nik);

        $stats = SafetyStats::forYear(now()->year);

        return view('ssap.my', [
            'counts' => [
                'incidents' => (clone $incidents)->count(),
                'hazards' => (clone $hazards)->count(),
                'open' => (clone $incidents)->open()->count() + (clone $hazards)->open()->count(),
                'actions' => (clone $myActions)->pending()->count(),
                'overdue' => (clone $myActions)->overdue()->count(),
            ],
            'daysWithoutLti' => $stats->daysWithoutLti(),
            'recentIncidents' => (clone $incidents)->with('location:id,name')->latest('occurred_at')->limit(5)->get(),
            'recentHazards' => (clone $hazards)->with('location:id,name')->latest('observed_at')->limit(5)->get(),
            'actions' => (clone $myActions)->pending()->with('source:id,number,title')->orderBy('due_date')->limit(8)->get(),
        ]);
    }
}
