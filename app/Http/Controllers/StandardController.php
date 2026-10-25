<?php

namespace App\Http\Controllers;

/** Halaman "Standar Pelaporan": rujukan klasifikasi, matriks risiko, tenggat, dan rumus (dibaca dari config/ssap.php). */
class StandardController extends Controller
{
    public function __invoke()
    {
        return view('ssap.standard', [
            'types' => config('ssap.incident_types'),
            'severity' => config('ssap.severity'),
            'likelihood' => config('ssap.likelihood'),
            'consequence' => config('ssap.consequence'),
            'levels' => config('ssap.risk_levels'),
            'investigationDays' => config('ssap.investigation_days'),
            'notifyHours' => config('ssap.notify_hours'),
            'rateBase' => config('ssap.rate_base'),
        ]);
    }
}
