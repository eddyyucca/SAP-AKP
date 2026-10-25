<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\InspectionTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Template checklist inspeksi (admin/HSE). Butir ditulis satu per baris. */
class InspectionTemplateController extends Controller
{
    public function index()
    {
        return view('ssap.inspections.templates', [
            'templates' => InspectionTemplate::with('items')->withCount('items')->orderByDesc('is_active')->orderBy('name')->get(),
            'usage' => Inspection::withTrashed()->selectRaw('template_id, count(*) as n')->groupBy('template_id')->pluck('n', 'template_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $template = InspectionTemplate::create([
                'name' => trim($data['name']), 'category' => $data['category'], 'description' => $data['description'] ?? null,
                'target_per_month' => $data['target_per_month'], 'is_active' => true,
            ]);
            $this->syncItems($template, $data['items']);
        });

        return back()->with('success', 'Template inspeksi dibuat.');
    }

    public function update(Request $request, InspectionTemplate $template): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($template, $data, $request) {
            $template->update([
                'name' => trim($data['name']), 'category' => $data['category'], 'description' => $data['description'] ?? null,
                'target_per_month' => $data['target_per_month'], 'is_active' => $request->boolean('is_active'),
            ]);
            $this->syncItems($template, $data['items']);
        });

        return back()->with('success', 'Template diperbarui. Inspeksi lama tetap memakai butir saat itu.');
    }

    public function destroy(InspectionTemplate $template): RedirectResponse
    {
        if (Inspection::withTrashed()->where('template_id', $template->id)->exists()) {
            return back()->with('error', 'Template sudah dipakai inspeksi, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }

        $template->delete();

        return back()->with('success', 'Template dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(config('ssap.hazard_categories')))],
            'description' => ['nullable', 'string', 'max:255'],
            'target_per_month' => ['required', 'integer', 'between:0,500'],
            'items' => ['required', 'string', 'max:20000'],
        ]);

        $lines = collect(preg_split('/\r\n|\r|\n/', $data['items']))->map(fn ($l) => trim($l))->filter()->unique()->values();

        if ($lines->isEmpty() || $lines->count() > 100 || $lines->contains(fn ($l) => mb_strlen($l) > 255)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Isi 1-100 butir pemeriksaan, satu per baris (maksimal 255 karakter per butir).']);
        }

        $data['items'] = $lines->all();

        return $data;
    }

    private function syncItems(InspectionTemplate $template, array $lines): void
    {
        $template->items()->delete();
        foreach ($lines as $i => $question) {
            $template->items()->create(['position' => $i + 1, 'question' => $question]);
        }
    }
}
