@extends('layouts.app')

@section('title', 'Standar Pelaporan')
@section('page-title', 'Standar Pelaporan Safety')
@section('breadcrumb')
    <li class="breadcrumb-item active">Standar</li>
@endsection

@section('content')
<div class="alert alert-primary small"><i class="bi bi-journal-check me-1"></i>Halaman ini merangkum aturan pelaporan safety {{ config('ssap.company') }} yang dipakai di seluruh formulir, dashboard, dan laporan SAP Safety.</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-1-circle"></i>Apa yang dilaporkan</h3></div>
            <div class="s-card-body small">
                <ul class="mb-0">
                    <li><strong>Insiden</strong>: kejadian yang sudah terjadi (cedera, kerusakan, kebakaran, tumpahan, nyaris celaka).</li>
                    <li><strong>Hazard</strong>: kondisi atau tindakan tidak aman yang ditemukan <em>sebelum</em> menjadi insiden.</li>
                    <li><strong>Inspeksi rutin</strong>: pemeriksaan terjadwal memakai checklist; temuan &ldquo;tidak sesuai&rdquo; otomatis menjadi hazard.</li>
                    <li><strong>Tindakan korektif</strong>: perbaikan yang ditugaskan HSE ke PIC, lalu diverifikasi sampai tuntas.</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-2-circle"></i>Alur &amp; tenggat</h3></div>
            <div class="s-card-body small">
                <ol class="mb-2">
                    <li><strong>Dilaporkan</strong> &rarr; <strong>Investigasi</strong> (investigator &amp; akar masalah) &rarr; <strong>Tindak lanjut</strong> (tindakan korektif) &rarr; <strong>Ditutup</strong>.</li>
                    <li>Laporan hanya bisa ditutup bila akar masalah terisi dan semua tindakan sudah terverifikasi HSE.</li>
                </ol>
                <table class="table table-sm table-ssap mb-0">
                    <thead><tr><th>Jenis</th><th>Lapor ke HSE</th><th>Investigasi</th></tr></thead>
                    <tbody>
                    @foreach ($types as $k => $t)
                        <tr><td>{{ $t['label'] }}</td><td>&le; {{ $notifyHours[$k] ?? $notifyHours['default'] }} jam</td><td>{{ $investigationDays[$k] ?? $investigationDays['default'] }} hari</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-3-circle"></i>Klasifikasi jenis kejadian</h3></div>
            <div class="table-responsive">
                <table class="table table-ssap mb-0">
                    <thead><tr><th>Jenis &amp; definisi</th><th class="text-center">LTI</th><th class="text-center">Tercatat (TRIR)</th></tr></thead>
                    <tbody>
                    @foreach (config('ssap.incident_categories') as $catKey => $cat)
                        <tr><td colspan="3" class="fw-bold" style="background:#f1f5f9;color:#203868;">{{ $cat['code'] }}. {{ $cat['title'] }}{{ $cat['ref'] ? ' ('.$cat['ref'].')' : '' }}</td></tr>
                        @foreach ($types as $t)
                            @if ($t['category'] === $catKey)
                                <tr><td><span class="legend-dot" style="background:{{ $t['color'] }}"></span><strong>{{ $t['label'] }}</strong><div class="small text-muted">{{ $t['definition'] ?? '' }}</div></td><td class="text-center">{!! $t['lti'] ? '<i class="bi bi-check-lg text-danger"></i>' : '-' !!}</td><td class="text-center">{!! $t['recordable'] ? '<i class="bi bi-check-lg text-warning"></i>' : '-' !!}</td></tr>
                            @endif
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="small text-muted p-3 pt-2">Pengelompokan mengikuti format laporan kecelakaan: A tambang (KEPMEN ESDM 1827 K/30/MEM/2018), B umum (ANSI), C lingkungan, D kebakaran, E nearmiss. Ambang hari kerja hilang mengikuti ketentuan tersebut; sesuaikan di <code>config/ssap.php</code> bila KTT/HSE menetapkan lain.</div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-4-circle"></i>Keparahan aktual</h3></div>
            <div class="table-responsive">
                <table class="table table-ssap mb-0">
                    <tbody>
                    @foreach ($severity as $k => $s)
                        <tr><td style="width:40px;"><span class="pill" style="background:{{ $s['color'] }}1f;color:{{ $s['color'] }};">{{ $k }}</span></td><td class="fw-semibold">{{ $s['label'] }}</td><td class="small text-muted">{{ $s['hint'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-5-circle"></i>Matriks risiko 5x5</h3><span class="s-card-sub">Skor = kemungkinan x konsekuensi</span></div>
            <div class="s-card-body">
                <div class="heat" style="grid-template-columns:90px repeat(5, 1fr);">
                    <div class="axis"></div>
                    @foreach ($consequence as $c => $m)<div class="axis text-center" title="{{ $m['hint'] }}">{{ $c }}<br><span class="fw-normal" style="font-size:.65rem;">{{ $m['label'] }}</span></div>@endforeach
                    @foreach (array_reverse(array_keys($likelihood)) as $l)
                        <div class="axis text-center" title="{{ $likelihood[$l]['hint'] }}">{{ $l }}<br><span class="fw-normal" style="font-size:.65rem;">{{ $likelihood[$l]['label'] }}</span></div>
                        @foreach ($consequence as $c => $m)
                            <div class="cell lv-{{ \App\Support\Risk::level($l * $c) }}" style="aspect-ratio:1.6;">{{ $l * $c }}</div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-6-circle"></i>Tingkat risiko &amp; target penyelesaian hazard</h3></div>
            <div class="table-responsive">
                <table class="table table-ssap mb-0">
                    <thead><tr><th>Tingkat</th><th>Skor</th><th>Tenggat</th><th>Arahan</th></tr></thead>
                    <tbody>
                    @php($prev = 0)
                    @foreach ($levels as $k => $lv)
                        <tr><td><x-risk :level="$k" /></td><td>{{ $prev + 1 }}-{{ $lv['max'] }}</td><td>{{ $lv['sla_days'] }} hari</td><td class="small">{{ $lv['action'] }}</td></tr>
                        @php($prev = $lv['max'])
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="s-card">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-7-circle"></i>Rumus indikator</h3></div>
            <div class="s-card-body small">
                <div class="row g-3">
                    <div class="col-12 col-md-4"><strong>LTIFR</strong> (Lost Time Injury Frequency Rate)<div class="text-muted">kasus LTI &amp; fatal x {{ number_format($rateBase, 0, ',', '.') }} / jam kerja</div></div>
                    <div class="col-12 col-md-4"><strong>TRIR</strong> (Total Recordable Incident Rate)<div class="text-muted">kasus fatal + LTI + RWC + MTC x {{ number_format($rateBase, 0, ',', '.') }} / jam kerja</div></div>
                    <div class="col-12 col-md-4"><strong>Severity Rate</strong><div class="text-muted">hari kerja hilang x {{ number_format($rateBase, 0, ',', '.') }} / jam kerja</div></div>
                    <div class="col-12 col-md-4"><strong>Skor inspeksi</strong><div class="text-muted">butir sesuai / (sesuai + tidak sesuai) x 100%; N/A tidak dihitung. &ge;90% baik, 75-89% cukup, &lt;75% perlu perbaikan.</div></div>
                    <div class="col-12 col-md-4"><strong>Kepatuhan inspeksi</strong><div class="text-muted">inspeksi terlaksana / target bulanan template x bulan berjalan.</div></div>
                    <div class="col-12 col-md-4"><strong>Jam kerja</strong><div class="text-muted">diisi per lokasi per bulan oleh HSE di menu Jam Kerja.</div></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
