<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use App\Models\Incident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Master kontraktor di area IUP / IUJP (admin, HSE). */
class ContractorController extends Controller
{
    public function index()
    {
        return view('ssap.lpi.contractors', [
            'contractors' => Contractor::ordered()->get(),
            'usage' => Incident::withTrashed()->whereNotNull('contractor_id')->selectRaw('contractor_id, count(*) as n')->groupBy('contractor_id')->pluck('n', 'contractor_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Contractor::create($this->validated($request) + ['is_active' => true]);

        return back()->with('success', 'Kontraktor ditambahkan.');
    }

    public function update(Request $request, Contractor $contractor): RedirectResponse
    {
        $contractor->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Kontraktor diperbarui.');
    }

    public function destroy(Contractor $contractor): RedirectResponse
    {
        if (Incident::withTrashed()->where('contractor_id', $contractor->id)->exists()) {
            return back()->with('error', 'Kontraktor sudah punya insiden, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }

        $contractor->delete();

        return back()->with('success', 'Kontraktor dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'area_type' => ['required', Rule::in(array_keys(config('ssap.contractor_areas')))],
            'permit_no' => ['nullable', 'string', 'max:80'],
            'pic_name' => ['nullable', 'string', 'max:120'],
            'pic_email' => ['nullable', 'email', 'max:160'],
            'pic_phone' => ['nullable', 'string', 'max:40'],
        ]);

        $data['name'] = trim($data['name']);

        return $data;
    }
}
