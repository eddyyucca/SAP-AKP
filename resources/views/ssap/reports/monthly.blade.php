<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ringkasan SAP {{ $month->translatedFormat('F Y') }}</title>
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
@php
    $n = fn ($v, $d = 0) => $v === null ? '-' : number_format($v, $d, ',', '.');
    $p = fn ($v) => $v === null ? '-' : $v.'%';
@endphp
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
        <div><div style="font-size:10px;opacity:.8;">{{ config('ssap.company') }} &middot; Safety Accountability Program (SAP)</div><div class="fw-bold" style="font-size:18px;">Ringkasan Hazard &amp; Inspeksi Bulanan</div></div>
        <div class="text-end"><div class="fw-bold">{{ $month->translatedFormat('F Y') }}</div><div style="font-size:10px;">{{ $location?->name ?? 'Semua lokasi' }}</div></div>
    </div>

    <h2>Hazard bulan ini</h2>
    <div class="row g-2">
        @foreach ([['Dilaporkan', $kpi['hazards']], ['Selesai', $kpi['closed']], ['Penyelesaian', $p($kpi['closure_pct'])], ['Tepat waktu', $p($kpi['sla_pct'])], ['TTA', $kpi['tta']], ['KTA', $kpi['kta']], ['Rata-rata hari', $n($kpi['avg_days'], 1)]] as [$l, $v])
            <div class="col-4 col-md-2"><div class="k"><b>{{ $v }}</b><span>{{ $l }}</span></div></div>
        @endforeach
        @foreach ([['Terbuka kini', $kpi['open_now']], ['Terlambat kini', $kpi['overdue_now']], ['Belum ada PIC', $kpi['unassigned']], ['Tindakan telat', $kpi['actions_overdue']], ['Menunggu verifikasi', $kpi['actions_verify']], ['Hazard tertua (hr)', $kpi['oldest_open_days'] ?? '-']] as [$l, $v])
            <div class="col-4 col-md-2"><div class="k"><b>{{ $v }}</b><span>{{ $l }}</span></div></div>
        @endforeach
    </div>

    <h2>Inspeksi bulan ini</h2>
    <div class="row g-2">
        @foreach ([['Terlaksana', $ikpi['count']], ['Target', $ikpi['target']], ['Kepatuhan', $p($ikpi['compliance'])], ['Skor rata-rata', $ikpi['avg_score'] === null ? '-' : $n($ikpi['avg_score'], 1).'%'], ['Temuan', $ikpi['findings']], ['Inspektor', $ikpi['inspectors']]] as [$l, $v])
            <div class="col-4 col-md-2"><div class="k"><b>{{ $v }}</b><span>{{ $l }}</span></div></div>
        @endforeach
    </div>

    <h2>Kumulatif tahun berjalan (Jan - {{ $month->translatedFormat('M Y') }})</h2>
    <div class="row g-2">
        @foreach ([['Hazard', $ytd['hazards']], ['Selesai', $ytd['closed']], ['Penyelesaian', $p($ytd['closure_pct'])], ['Tepat waktu', $p($ytd['sla_pct'])], ['Inspeksi', $iytd['count']], ['Skor rata-rata', $iytd['avg_score'] === null ? '-' : $n($iytd['avg_score'], 1).'%']] as [$l, $v])
            <div class="col-4 col-md-2"><div class="k"><b>{{ $v }}</b><span>{{ $l }}</span></div></div>
        @endforeach
    </div>
    <div class="row g-3 mt-1">
        <div class="col-7"><div class="ch"><canvas id="cvTrend"></canvas></div></div>
        <div class="col-5"><div class="ch"><canvas id="cvInsTrend"></canvas></div></div>
    </div>

    <h2>TTA &amp; KTA menurut status (bulan ini)</h2>
    <table class="table table-sm table-bordered mb-0">
        <thead class="table-light"><tr><th>Jenis</th><th class="text-end">Total</th><th class="text-end">Terbuka</th><th class="text-end">Terlambat</th><th class="text-end">Selesai</th></tr></thead>
        <tbody>@foreach ($categoryTable as $r)<tr><td>{{ $r['label'] }}</td><td class="text-end">{{ $r['total'] }}</td><td class="text-end">{{ $r['open'] }}</td><td class="text-end">{{ $r['overdue'] }}</td><td class="text-end">{{ $r['closed'] }}</td></tr>@endforeach</tbody>
    </table>

    <h2>Rekap per lokasi (bulan ini)</h2>
    <table class="table table-sm table-bordered mb-0">
        <thead class="table-light"><tr><th>Lokasi</th><th class="text-end">Hazard</th><th class="text-end">Terbuka</th><th class="text-end">Terlambat</th><th class="text-end">TTA</th><th class="text-end">KTA</th><th class="text-end">Inspeksi</th><th class="text-end">Skor</th><th class="text-end">Temuan</th></tr></thead>
        <tbody>
        @forelse ($locationRows as $r)<tr><td>{{ $r['name'] }}</td><td class="text-end">{{ $r['hazards'] }}</td><td class="text-end">{{ $r['open'] }}</td><td class="text-end">{{ $r['overdue'] }}</td><td class="text-end">{{ $r['tta'] }}</td><td class="text-end">{{ $r['kta'] }}</td><td class="text-end">{{ $r['inspections'] }}</td><td class="text-end">{{ $r['score'] !== null ? $n($r['score'], 1).'%' : '-' }}</td><td class="text-end">{{ $r['findings'] }}</td></tr>
        @empty<tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>@endforelse
        </tbody>
    </table>

    <h2>Pencapaian inspeksi per template</h2>
    <table class="table table-sm table-bordered mb-0">
        <thead class="table-light"><tr><th>Template</th><th class="text-end">Terlaksana</th><th class="text-end">Target</th><th class="text-end">Kepatuhan</th><th class="text-end">Skor rata-rata</th></tr></thead>
        <tbody>@forelse ($templateRows as $t)<tr><td>{{ $t['name'] }}</td><td class="text-end">{{ $t['done'] }}</td><td class="text-end">{{ $t['target'] }}</td><td class="text-end">{{ $p($t['pct']) }}</td><td class="text-end">{{ $t['score'] !== null ? $n($t['score'], 1).'%' : '-' }}</td></tr>
        @empty<tr><td colspan="5" class="text-center text-muted">Belum ada template.</td></tr>@endforelse</tbody>
    </table>
    @if ($topFindings)
        <div class="mt-2 small"><strong>Temuan terbanyak:</strong>
            @foreach ($topFindings as $f){{ $f['question'] }} ({{ $f['total'] }}x)@if (! $loop->last); @endif @endforeach
        </div>
    @endif

    <h2>Akuntabilitas PIC (bulan ini)</h2>
    <table class="table table-sm table-bordered mb-0">
        <thead class="table-light"><tr><th>PIC</th><th>Departemen</th><th class="text-end">Ditugaskan</th><th class="text-end">Terbuka</th><th class="text-end">Terlambat</th><th class="text-end">Selesai</th><th class="text-end">Tepat waktu</th></tr></thead>
        <tbody>@forelse ($picBoard as $r)<tr><td>{{ $r['name'] }}</td><td>{{ $r['dept'] }}</td><td class="text-end">{{ $r['assigned'] }}</td><td class="text-end">{{ $r['open'] }}</td><td class="text-end">{{ $r['overdue'] }}</td><td class="text-end">{{ $r['closed'] }}</td><td class="text-end">{{ $p($r['sla_pct']) }}</td></tr>
        @empty<tr><td colspan="7" class="text-center text-muted">Tidak ada data.</td></tr>@endforelse</tbody>
    </table>

    <h2>Hazard terbuka (tenggat terdekat)</h2>
    <table class="table table-sm table-bordered mb-0">
        <thead class="table-light"><tr><th>Nomor</th><th>Keterangan</th><th>Jenis</th><th>Lokasi</th><th>PIC</th><th>Tenggat</th></tr></thead>
        <tbody>@forelse ($openList as $h)<tr><td>{{ $h->number }}</td><td>{{ \Illuminate\Support\Str::limit($h->description, 60) }}</td><td>{{ strtoupper($h->category) }}</td><td>{{ $h->location?->name }}</td><td>{{ $h->pic_name ?? '-' }}</td><td>{{ $h->due_date?->format('d/m/Y') }}</td></tr>
        @empty<tr><td colspan="6" class="text-center text-muted">Tidak ada.</td></tr>@endforelse</tbody>
    </table>

    <h2>Tindakan korektif terlambat</h2>
    <table class="table table-sm table-bordered mb-0">
        <thead class="table-light"><tr><th>Sumber</th><th>Tindakan</th><th>PIC</th><th>Tenggat</th></tr></thead>
        <tbody>@forelse ($overdue as $a)<tr><td>{{ $a->source?->number }}</td><td>{{ \Illuminate\Support\Str::limit($a->description, 70) }}</td><td>{{ $a->pic_name }}</td><td>{{ $a->due_date->format('d/m/Y') }} ({{ $a->due_date->diffInDays(today()) }} hari)</td></tr>
        @empty<tr><td colspan="4" class="text-center text-muted">Tidak ada.</td></tr>@endforelse</tbody>
    </table>

    <div class="row mt-4 text-center" style="font-size:11px;">
        <div class="col-4">Disusun oleh<div style="height:60px;"></div><div class="border-top pt-1">Petugas HSE</div></div>
        <div class="col-4">Diperiksa oleh<div style="height:60px;"></div><div class="border-top pt-1">Superintendent HSE</div></div>
        <div class="col-4">Disetujui oleh<div style="height:60px;"></div><div class="border-top pt-1">Manajer</div></div>
    </div>
    <div class="text-muted mt-3" style="font-size:10px;">Dicetak {{ now()->translatedFormat('d F Y H:i') }} &middot; Tepat waktu = hazard ditutup sebelum/pada tenggat; skor inspeksi = butir sesuai / (sesuai + tidak sesuai), N/A tidak dihitung.</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/ssap-charts.js') }}?v=7"></script>
<script>window.addEventListener('load', function () { window.SSAPCharts && window.SSAPCharts(@json($charts)); });</script>
</body>
</html>
