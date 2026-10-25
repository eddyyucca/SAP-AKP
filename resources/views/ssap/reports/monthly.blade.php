<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ringkasan Safety {{ $month->translatedFormat('F Y') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4; margin: 12mm; }
        body { font-family: 'Inter', sans-serif; font-size: 12px; background: #f5f7fb; color: #111827; }
        .sheet { max-width: 980px; margin: 0 auto; background: #fff; padding: 22px; }
        .head { background: #203868; color: #fff; padding: 14px 18px; border-radius: 10px; }
        h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #203868; border-bottom: 2px solid #203868; padding-bottom: 3px; margin: 18px 0 8px; }
        .k { border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 10px; text-align: center; height: 100%; }
        .k b { display: block; font-size: 20px; font-variant-numeric: tabular-nums; }
        .k span { font-size: 10px; color: #6b7280; text-transform: uppercase; }
        table { font-size: 11px; }
        .ch { height: 230px; position: relative; }
        @media print { body { background: #fff; } .sheet { padding: 0; max-width: none; } .no-print { display: none !important; } h2, .k, table, .ch { break-inside: avoid; } }
    </style>
</head>
<body>
@php($n = fn ($v, $d = 0) => $v === null ? '-' : number_format($v, $d, ',', '.'))
<div class="no-print text-center p-2 bg-white border-bottom">
    <form method="GET" class="d-inline-flex gap-2 align-items-center">
        <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm" style="width:auto;">
        @if ($location)<input type="hidden" name="location" value="{{ $location->id }}">@endif
        <button class="btn btn-sm btn-outline-primary">Tampilkan</button>
    </form>
    <button class="btn btn-sm btn-primary ms-2" onclick="window.print()"><i class="bi bi-printer"></i> Cetak / Simpan PDF</button>
    <a class="btn btn-sm btn-link" href="{{ route('ssap.reports.index') }}">Kembali</a>
</div>

<div class="sheet">
    <div class="head d-flex justify-content-between align-items-center">
        <div><div style="font-size:10px;opacity:.8;">{{ config('ssap.company') }} &middot; SSAP Safety</div><div class="fw-bold" style="font-size:18px;">Ringkasan Safety Bulanan</div></div>
        <div class="text-end"><div class="fw-bold">{{ $month->translatedFormat('F Y') }}</div><div style="font-size:10px;">{{ $location?->name ?? 'Semua lokasi' }}</div></div>
    </div>

    <h2>Indikator bulan ini</h2>
    <div class="row g-2">
        @foreach ([['Insiden', $kpi['incidents']], ['Nyaris celaka', $kpi['near_miss']], ['LTI / fatal', $kpi['lti']], ['Kasus tercatat', $kpi['recordable']], ['Hari hilang', $kpi['lost_days']], ['Jam kerja', $n($kpi['man_hours'])]] as [$l, $v])
            <div class="col-4 col-md-2"><div class="k"><b>{{ $v }}</b><span>{{ $l }}</span></div></div>
        @endforeach
        @foreach ([['LTIFR', $n($kpi['ltifr'], 2)], ['TRIR', $n($kpi['trir'], 2)], ['Hazard baru', $kpi['hazards']], ['Hazard terbuka', $kpi['hazards_open']], ['Tindakan telat', $kpi['actions_overdue']], ['Hari tanpa LTI', $kpi['days_without_lti'] ?? '∞']] as [$l, $v])
            <div class="col-4 col-md-2"><div class="k"><b>{{ $v }}</b><span>{{ $l }}</span></div></div>
        @endforeach
    </div>

    <h2>Kumulatif tahun berjalan (Jan - {{ $month->translatedFormat('M Y') }})</h2>
    <div class="row g-2">
        @foreach ([['Insiden', $ytd['incidents']], ['LTI / fatal', $ytd['lti']], ['Kasus tercatat', $ytd['recordable']], ['LTIFR', $n($ytd['ltifr'], 2)], ['TRIR', $n($ytd['trir'], 2)], ['Severity rate', $n($ytd['sr'], 1)]] as [$l, $v])
            <div class="col-4 col-md-2"><div class="k"><b>{{ $v }}</b><span>{{ $l }}</span></div></div>
        @endforeach
    </div>
    <div class="row g-3 mt-1">
        <div class="col-7"><div class="ch"><canvas id="cvTrend"></canvas></div></div>
        <div class="col-5"><div class="ch"><canvas id="cvType"></canvas></div></div>
    </div>

    <h2>Rekap per lokasi (bulan ini)</h2>
    <table class="table table-sm table-bordered mb-0">
        <thead class="table-light"><tr><th>Lokasi</th><th class="text-end">Insiden</th><th class="text-end">LTI</th><th class="text-end">Jam kerja</th><th class="text-end">LTIFR</th><th class="text-end">TRIR</th><th class="text-end">Hazard</th></tr></thead>
        <tbody>
        @forelse ($byLocation as $r)<tr><td>{{ $r['name'] }}</td><td class="text-end">{{ $r['incidents'] }}</td><td class="text-end">{{ $r['lti'] }}</td><td class="text-end">{{ $n($r['hours']) }}</td><td class="text-end">{{ $n($r['ltifr'], 2) }}</td><td class="text-end">{{ $n($r['trir'], 2) }}</td><td class="text-end">{{ $r['hazards'] }}</td></tr>
        @empty<tr><td colspan="7" class="text-center text-muted">Tidak ada data.</td></tr>@endforelse
        </tbody>
    </table>

    <h2>Daftar insiden bulan ini</h2>
    <table class="table table-sm table-bordered mb-0">
        <thead class="table-light"><tr><th>Nomor</th><th>Tanggal</th><th>Jenis</th><th>Lokasi</th><th>Judul</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($incidents as $i)<tr><td>{{ $i->number }}</td><td>{{ $i->occurred_at->format('d/m') }}</td><td>{{ $i->typeMeta()['short'] }}</td><td>{{ $i->location?->name }}</td><td>{{ \Illuminate\Support\Str::limit($i->title, 60) }}</td><td>{{ $i->statusMeta()['label'] }}</td></tr>
        @empty<tr><td colspan="6" class="text-center text-muted">Tidak ada insiden bulan ini.</td></tr>@endforelse
        </tbody>
    </table>

    <h2>Hazard risiko tinggi/ekstrem yang masih terbuka</h2>
    <table class="table table-sm table-bordered mb-0">
        <thead class="table-light"><tr><th>Nomor</th><th>Hazard</th><th>Risiko</th><th>Lokasi</th><th>PIC</th><th>Tenggat</th></tr></thead>
        <tbody>
        @forelse ($hazards as $h)<tr><td>{{ $h->number }}</td><td>{{ \Illuminate\Support\Str::limit($h->title, 60) }}</td><td>{{ $h->riskMeta()['label'] }} ({{ $h->risk_score }})</td><td>{{ $h->location?->name }}</td><td>{{ $h->pic_name ?? '-' }}</td><td>{{ $h->due_date?->format('d/m/Y') }}</td></tr>
        @empty<tr><td colspan="6" class="text-center text-muted">Tidak ada.</td></tr>@endforelse
        </tbody>
    </table>

    <h2>Tindakan korektif terlambat</h2>
    <table class="table table-sm table-bordered mb-0">
        <thead class="table-light"><tr><th>Sumber</th><th>Tindakan</th><th>PIC</th><th>Tenggat</th></tr></thead>
        <tbody>
        @forelse ($overdue as $a)<tr><td>{{ $a->source?->number }}</td><td>{{ \Illuminate\Support\Str::limit($a->description, 70) }}</td><td>{{ $a->pic_name }}</td><td>{{ $a->due_date->format('d/m/Y') }} ({{ $a->due_date->diffInDays(today()) }} hari)</td></tr>
        @empty<tr><td colspan="4" class="text-center text-muted">Tidak ada.</td></tr>@endforelse
        </tbody>
    </table>

    <div class="row mt-4 text-center" style="font-size:11px;">
        <div class="col-4">Disusun oleh<div style="height:60px;"></div><div class="border-top pt-1">Petugas HSE</div></div>
        <div class="col-4">Diperiksa oleh<div style="height:60px;"></div><div class="border-top pt-1">Superintendent HSE</div></div>
        <div class="col-4">Disetujui oleh<div style="height:60px;"></div><div class="border-top pt-1">Manajer</div></div>
    </div>
    <div class="text-muted mt-3" style="font-size:10px;">Dicetak {{ now()->translatedFormat('d F Y H:i') }} &middot; LTIFR/TRIR per {{ number_format(config('ssap.rate_base'), 0, ',', '.') }} jam kerja.</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/ssap-charts.js') }}?v=2"></script>
<script>
    window.addEventListener('load', function () { window.SSAPCharts && window.SSAPCharts(@json($charts)); });
</script>
</body>
</html>
