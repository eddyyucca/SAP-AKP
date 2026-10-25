<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Insiden {{ $incident->number }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #111827; margin: 0; }
        .bar { background: #203868; color: #fff; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; }
        .bar h1 { font-size: 15px; margin: 0; }
        h2 { font-size: 11.5px; text-transform: uppercase; letter-spacing: .05em; color: #203868; border-bottom: 1.5px solid #203868; padding-bottom: 3px; margin: 14px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #d1d5db; padding: 4px 6px; vertical-align: top; text-align: left; }
        th { background: #f1f5f9; font-weight: 600; width: 28%; }
        table.grid th { width: auto; }
        .pre { white-space: pre-line; }
        .sign td { height: 62px; text-align: center; vertical-align: bottom; }
        .toolbar { padding: 10px 14px; background: #f1f5f9; }
        @media print { .toolbar { display: none; } }
        .badge { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 10px; font-weight: 600; background: #e5e7eb; }
        img.ev { max-width: 170px; max-height: 130px; margin: 3px; border: 1px solid #d1d5db; }
    </style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
<div class="bar">
    <div><h1>LAPORAN INSIDEN</h1><div style="font-size:10px;opacity:.85;">{{ config('ssap.company') }} &middot; SAP Safety</div></div>
    <div style="text-align:right;"><strong style="font-size:14px;">{{ $incident->number }}</strong><div style="font-size:10px;">Dicetak {{ now()->translatedFormat('d F Y H:i') }}</div></div>
</div>

<h2>1. Informasi kejadian</h2>
<table>
    <tr><th>Jenis kejadian</th><td>{{ $incident->typeMeta()['label'] }}</td><th>Status</th><td>{{ $incident->statusMeta()['label'] }}</td></tr>
    <tr><th>Waktu kejadian</th><td>{{ $incident->occurred_at->translatedFormat('l, d F Y H:i') }}</td><th>Keparahan</th><td>{{ $incident->severity }} - {{ $incident->severityMeta()['label'] }}</td></tr>
    <tr><th>Lokasi</th><td>{{ $incident->location?->name }}{{ $incident->area_detail ? ' - '.$incident->area_detail : '' }}</td><th>Potensi risiko</th><td>{{ $incident->riskMeta() ? $incident->riskMeta()['label'].' (skor '.$incident->risk_score.')' : '-' }}</td></tr>
    <tr><th>Aktivitas</th><td>{{ $incident->activity ?: '-' }}</td><th>Unit / peralatan</th><td>{{ $incident->equipment ?: '-' }}</td></tr>
    <tr><th>Judul</th><td colspan="3"><strong>{{ $incident->title }}</strong></td></tr>
    <tr><th>Kronologi</th><td colspan="3" class="pre">{{ $incident->description }}</td></tr>
    <tr><th>Tindakan segera</th><td colspan="3" class="pre">{{ $incident->immediate_action ?: '-' }}</td></tr>
    <tr><th>Dilaporkan oleh</th><td>{{ $incident->reporter_name }} (NIK {{ $incident->reporter_nik ?: '-' }})<br>{{ $incident->reporter_department }} {{ $incident->reporter_position ? '/ '.$incident->reporter_position : '' }}</td><th>Tanggal laporan</th><td>{{ $incident->created_at->translatedFormat('d F Y H:i') }}</td></tr>
</table>

@if ($incident->persons->isNotEmpty())
    <h2>2. Orang yang terlibat</h2>
    <table class="grid">
        <tr><th>Peran</th><th>Nama / NIK</th><th>Departemen / jabatan</th><th>Cedera</th><th>Hari hilang</th></tr>
        @foreach ($incident->persons as $p)
            <tr><td>{{ $p->roleLabel() }}</td><td>{{ $p->name }}<br>{{ $p->nik ? 'NIK '.$p->nik : $p->company }}</td><td>{{ $p->department }} {{ $p->position ? '/ '.$p->position : '' }}</td><td>{{ $p->injury_type }} {{ $p->body_part ? '('.$p->body_part.')' : '' }}<br>{{ $p->treatment }}</td><td>{{ $p->role === 'korban' ? $p->lost_days : '-' }}</td></tr>
        @endforeach
    </table>
@endif

<h2>3. Dampak</h2>
<table>
    <tr><th>Jumlah korban</th><td>{{ $incident->injured_count }} orang</td><th>Hari kerja hilang</th><td>{{ $incident->lost_days }} hari</td></tr>
    <tr><th>Estimasi kerugian</th><td>{{ $incident->cost_estimate ? 'Rp '.number_format($incident->cost_estimate, 0, ',', '.') : '-' }}</td><th>Dampak lingkungan</th><td>{{ $incident->environmental_impact ?: '-' }}</td></tr>
    <tr><th>Dilaporkan ke otoritas</th><td colspan="3">{{ $incident->reported_to_authority ? 'Ya. '.$incident->authority_note : 'Belum / tidak diperlukan' }}</td></tr>
</table>

<h2>4. Investigasi</h2>
<table>
    <tr><th>Investigator</th><td>{{ $incident->investigator_name ?: '-' }}</td><th>Batas investigasi</th><td>{{ $incident->investigation_due?->translatedFormat('d F Y') ?: '-' }}</td></tr>
    <tr><th>Penyebab langsung</th><td colspan="3" class="pre">{{ $incident->immediate_cause ?: '-' }}</td></tr>
    @if ($incident->why_chain)
        <tr><th>Analisis 5-Why</th><td colspan="3">@foreach ($incident->why_chain as $n => $w)<div>{{ $n + 1 }}. {{ $w }}</div>@endforeach</td></tr>
    @endif
    <tr><th>Akar masalah</th><td colspan="3" class="pre">{{ $incident->root_cause_category ? '['.config('ssap.root_cause_categories.'.$incident->root_cause_category).'] ' : '' }}{{ $incident->root_cause ?: '-' }}</td></tr>
    <tr><th>Faktor kontribusi</th><td colspan="3" class="pre">{{ $incident->contributing_factors ?: '-' }}</td></tr>
    <tr><th>Pelajaran</th><td colspan="3" class="pre">{{ $incident->lessons_learned ?: '-' }}</td></tr>
</table>

<h2>5. Tindakan korektif &amp; pencegahan</h2>
<table class="grid">
    <tr><th>Jenis</th><th>Tindakan</th><th>PIC</th><th>Tenggat</th><th>Status</th></tr>
    @forelse ($incident->actions as $a)
        <tr><td>{{ $a->typeLabel() }}</td><td>{{ $a->description }}</td><td>{{ $a->pic_name }}</td><td>{{ $a->due_date->translatedFormat('d/m/Y') }}</td><td>{{ $a->statusMeta()['label'] }}</td></tr>
    @empty
        <tr><td colspan="5">Belum ada tindakan.</td></tr>
    @endforelse
</table>

@if ($incident->closed_at)
    <h2>6. Penutupan</h2>
    <table><tr><th>Ditutup</th><td>{{ $incident->closed_at->translatedFormat('d F Y') }} oleh {{ $incident->closed_by }}</td><th>Catatan</th><td>{{ $incident->closure_note }}</td></tr></table>
@endif

@php($images = $incident->attachments->filter->isImage())
@if ($images->isNotEmpty())
    <h2>Foto / bukti</h2>
    @foreach ($images as $img)<img class="ev" src="{{ route('ssap.attachments.show', $img) }}" alt="{{ $img->original_name }}">@endforeach
@endif

<h2>Pengesahan</h2>
<table class="grid sign">
    <tr><th>Pelapor</th><th>Investigator</th><th>Petugas HSE</th><th>Manajer</th></tr>
    <tr><td>{{ $incident->reporter_name }}</td><td>{{ $incident->investigator_name }}</td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table>
</body>
</html>
