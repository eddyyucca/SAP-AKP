<?php

namespace App\Http\Controllers;

/** Halaman "Standar Pelaporan": rujukan jenis hazard (TTA/KTA), tenggat, penilaian inspeksi, dan akuntabilitas (dibaca dari config/ssap.php). */
class StandardController extends Controller
{
    public function __invoke()
    {
        return view('ssap.standard', [
            'slaDays' => config('ssap.hazard_sla_days'),
            'categories' => config('ssap.hazard_categories'),
            'scoreGood' => config('ssap.inspection_score.good'),
            'scoreFair' => config('ssap.inspection_score.fair'),
            'templates' => \App\Models\InspectionTemplate::active()->withCount('items')->orderBy('name')->get(),
        ]);
    }
}
