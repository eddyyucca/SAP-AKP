<?php

namespace App\Http\Controllers;

use App\Services\TargetStats;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/** Target pelaporan bulanan (grade 3-5): milik sendiri, tim (grade 5), atau seluruh departemen (grade 6+, admin, HSE, manajer). */
class TargetController extends Controller
{
    public function index(Request $request, TargetStats $stats)
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'department' => ['nullable', 'string', 'max:120'],
            'only' => ['nullable', 'in:behind'],
        ]);

        $ref = Carbon::createFromFormat('Y-m-d', $data['date'] ?? now()->toDateString())->startOfDay();
        $scope = Access::scope();

        $rows = collect($stats->rows($ref, $scope['mode'] === 'all' ? null : $scope['niks']));
        $departments = $rows->pluck('department')->filter()->unique()->sort()->values()->all();
        $mine = $rows->firstWhere('nik', $scope['nik']);

        $view = $rows
            ->when($scope['mode'] === 'all' && filled($data['department'] ?? null), fn ($c) => $c->where('department', $data['department']))
            ->values();

        $summary = [
            'members' => $view->count(),
            'achieved' => $view->where('achieved', true)->count(),
            'behind' => $view->where('achieved', false)->count(),
        ];
        $summary['pct'] = $summary['members'] ? (int) round($summary['achieved'] * 100 / $summary['members']) : null;

        $byDepartment = $scope['mode'] === 'all' && blank($data['department'] ?? null);
        $showBehind = ($data['only'] ?? null) === 'behind';

        // Daftar anggota dipecah per halaman (departemen besar bisa ratusan orang); ringkasan dan grafik tetap memakai seluruh data.
        $list = $showBehind ? $view->where('achieved', false)->values() : $view;
        $perPage = 25;
        $pager = new LengthAwarePaginator(
            $list->forPage(max(1, (int) $request->query('page', 1)), $perPage)->values(),
            $list->count(), $perPage, max(1, (int) $request->query('page', 1)),
            ['path' => $request->url(), 'query' => $request->except('page')],
        );

        return view('ssap.targets', [
            'pager' => $pager,
            'ref' => $ref,
            'scope' => $scope,
            'targets' => TargetStats::targets(),
            'rows' => $pager->items(),
            'mine' => $mine,
            'summary' => $summary,
            'departmentRows' => $scope['mode'] === 'all' ? $stats->byDepartment($view->all()) : [],
            'departments' => $departments,
            'filters' => ['date' => $ref->toDateString(), 'department' => $data['department'] ?? '', 'only' => $data['only'] ?? ''],
            'charts' => ['target' => $stats->chart($view->all(), $byDepartment)],
        ]);
    }
}
