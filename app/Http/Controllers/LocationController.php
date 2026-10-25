<?php

namespace App\Http\Controllers;

use App\Models\Hazard;
use App\Models\Incident;
use App\Models\Location;
use App\Models\ManHour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Master lokasi / site (admin). */
class LocationController extends Controller
{
    public function index()
    {
        $incidents = Incident::withTrashed()->selectRaw('location_id, count(*) as n')->groupBy('location_id')->pluck('n', 'location_id');
        $hazards = Hazard::withTrashed()->selectRaw('location_id, count(*) as n')->groupBy('location_id')->pluck('n', 'location_id');

        return view('ssap.locations.index', [
            'locations' => Location::ordered()->get(),
            'incidents' => $incidents,
            'hazards' => $hazards,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:locations,code'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        Location::create(['code' => strtoupper($data['code']), 'name' => trim($data['name']), 'is_active' => true]);

        return back()->with('success', 'Lokasi ditambahkan.');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('locations', 'code')->ignore($location->id)],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $location->update(['code' => strtoupper($data['code']), 'name' => trim($data['name']), 'is_active' => (bool) ($data['is_active'] ?? false)]);

        return back()->with('success', 'Lokasi diperbarui.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $used = Incident::withTrashed()->where('location_id', $location->id)->exists()
            || Hazard::withTrashed()->where('location_id', $location->id)->exists()
            || ManHour::where('location_id', $location->id)->exists();

        if ($used) {
            return back()->with('error', 'Lokasi sudah dipakai oleh laporan atau jam kerja, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }

        $location->delete();

        return back()->with('success', 'Lokasi dihapus.');
    }
}
