<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\ManHour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Jam kerja bulanan per lokasi: dasar LTIFR, TRIR, dan severity rate. */
class ManHourController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $year = (int) ($filters['year'] ?? now()->year);

        $locations = Location::active()->ordered()->get(['id', 'name', 'code']);
        $hours = ManHour::where('year', $year)->get()->groupBy('location_id')->map(fn ($rows) => $rows->pluck('hours', 'month'));

        return view('ssap.manhours.index', [
            'year' => $year,
            'years' => range(now()->year + 1, now()->year - 6),
            'locations' => $locations,
            'hours' => $hours,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'hours' => ['nullable', 'array'],
            'hours.*' => ['nullable', 'array'],
            'hours.*.*' => ['nullable', 'integer', 'min:0', 'max:99999999'],
        ]);

        $year = (int) $request->input('year');
        $valid = Location::active()->pluck('id')->all();
        $saved = 0;

        foreach ((array) $request->input('hours', []) as $locationId => $months) {
            if (! in_array((int) $locationId, $valid, true)) {
                continue;
            }

            foreach ((array) $months as $month => $value) {
                $month = (int) $month;
                if ($month < 1 || $month > 12) {
                    continue;
                }

                $key = ['year' => $year, 'month' => $month, 'location_id' => (int) $locationId];

                if ($value === null || $value === '') {
                    ManHour::where($key)->delete();

                    continue;
                }

                ManHour::updateOrCreate($key, ['hours' => (int) $value, 'updated_by' => portal_user()->name]);
                $saved++;
            }
        }

        return redirect()->route('ssap.manhours.index', ['year' => $year])->with('success', "Jam kerja {$year} disimpan ({$saved} isian).");
    }
}
