@extends('layouts.app')

@php
    $mode = $scope['mode'];
    $title = $mode === 'self' ? 'Target Saya' : ($mode === 'team' ? 'Target Tim' : 'Target Departemen');
    $pctTone = fn ($v) => $v === null ? 'secondary' : ($v >= 100 ? 'success' : ($v >= 50 ? 'warning' : 'danger'));
    $periodNames = \App\Services\TargetStats::PERIODS;
    $daysLeft = collect($rows)->pluck('days_left')->filter(fn ($d) => $d !== null)->min();
@endphp

@section('title', $title)
@section('page-title', $title)
@section('breadcrumb')
    <li class="breadcrumb-item active">{{ $title }}</li>
@endsection

@section('content')
{{-- Level terdeteksi otomatis dari HRIS --}}
<div class="alert {{ $scope['grade'] === null ? 'alert-warning' : 'alert-success' }} small mb-3">
    <i class="bi bi-person-badge me-1"></i>
    @if ($scope['grade'] === null)
        <strong>Grade Anda belum terbaca dari HRIS</strong>, jadi Anda diperlakukan seperti grade 3 dan hanya melihat target Anda sendiri. Pastikan NIK Portal Anda sama dengan NIK di HRIS dan grade Anda sudah diisi.
    @else
        <strong>Terdeteksi dari HRIS: Grade {{ $scope['grade'] }}@if (! empty($me['position'])) &middot; {{ $me['position'] }}@endif</strong>
        @if ($mode === 'all')
            &mdash; Anda dapat melihat target <strong>seluruh departemen dan setiap anggota</strong>@if ($scope['grade'] >= 6) (grade 6 ke atas)@endif.
        @elseif ($mode === 'team')
            &mdash; Anda dapat melihat <strong>target Anda dan setiap anggota tim</strong> ({{ max(0, count($scope['niks']) - 1) }} bawahan menurut atasan langsung di HRIS).@if (count($scope['niks']) <= 1) <span class="text-danger">Belum ada bawahan terdaftar; minta HR mengisi atasan langsung di HRIS.</span>@endif
        @else
            &mdash; grade 3 hanya melihat target sendiri. Grade 4 ke atas dapat melihat target bawahannya (grade 4 melihat grade 3, grade 5 melihat grade 3-4, dan seterusnya).
        @endif
    @endif
</div>

<div class="alert alert-primary small mb-3">
    <i class="bi bi-bullseye me-1"></i><strong>Target pelaporan</strong> berlaku untuk grade yang ditetapkan di Pengaturan (grade menurut HRIS):
    @foreach ($targets as $g => $t)@if ($t['hazard'] + $t['inspection'] > 0)<span class="d-inline-block me-3">Grade {{ $g }}: <strong>{{ $t['hazard'] }}</strong> hazard{{ $t['inspection'] ? ' + '.$t['inspection'].' inspeksi' : '' }} {{ $periodNames[$t['period']] }}</span>@endif @endforeach
    <div class="mt-1 text-muted">
        {{ $mode === 'self' ? 'Anda melihat capaian Anda sendiri.' : ($mode === 'team' ? 'Anda melihat capaian Anda dan seluruh bawahan menurut atasan langsung di HRIS.' : 'Anda melihat capaian seluruh departemen.') }}
        Hazard dihitung dari laporan yang dibuat sendiri; temuan otomatis dari inspeksi tidak dihitung.
    </div>
</div>

<form method="GET" class="s-card p-3 mb-3 filter-bar">
    <div class="row g-2 align-items-end">
        @if ($mode !== 'self')
            <div class="col-12 col-md-6 col-xl-3"><label class="form-label small mb-1" for="q">Cari nama atau NIK</label><input type="search" name="q" id="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="mis. Budi atau 99000002" maxlength="100"></div>
        @endif
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label small mb-1">Periode memuat tanggal</label><input type="date" name="date" value="{{ $filters['date'] }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm"></div>
        @if ($mode === 'all')
            <div class="col-6 col-md-4 col-xl-3"><label class="form-label small mb-1">Departemen</label>
                <select name="department" class="form-select form-select-sm"><option value="">Semua departemen</option>
                    @foreach ($departments as $d)<option value="{{ $d }}" @selected($filters['department'] === $d)>{{ $d }}</option>@endforeach
                </select>
            </div>
        @endif
        @if ($mode !== 'self')
            <div class="col-12 col-md-3 col-xl-2"><div class="form-check mt-md-3"><input class="form-check-input" type="checkbox" name="only" value="behind" id="only" @checked($filters['only'] === 'behind')><label class="form-check-label small" for="only">Belum tercapai saja</label></div></div>
        @endif
        <div class="col-12 col-md-auto d-flex gap-2">
            <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Terapkan</button>
            <a href="{{ route('ssap.targets') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            <a href="{{ route('ssap.targets', ['date' => now()->subWeek()->toDateString()] + array_filter(['department' => $filters['department'] ?? null, 'q' => $filters['q'] ?? null])) }}" class="btn btn-outline-secondary btn-sm">Minggu lalu</a>
            <a href="{{ route('ssap.targets', ['date' => now()->subMonthNoOverflow()->toDateString()] + array_filter(['department' => $filters['department'] ?? null, 'q' => $filters['q'] ?? null])) }}" class="btn btn-outline-secondary btn-sm">Bulan lalu</a>
        </div>
    </div>
</form>

@if ($mine)
    <div class="s-card mb-3">
        <div class="s-card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div><strong>Capaian saya</strong> <span class="text-muted small">Grade {{ $mine['grade'] }} &middot; {{ $periodNames[$mine['period']] }} &middot; {{ $mine['period_label'] }}</span></div>
                <span class="pill pill-{{ $mine['achieved'] ? 'success' : 'warning' }}">{{ $mine['achieved'] ? 'Target tercapai' : 'Belum tercapai' }}@if (! $mine['achieved'] && $mine['days_left'] !== null) &middot; sisa {{ $mine['days_left'] }} hari @endif</span>
            </div>
            <div class="progress mb-2" style="height:10px;"><div class="progress-bar bg-{{ $pctTone($mine['pct']) }}" style="width:{{ $mine['pct'] }}%"></div></div>
            <div class="row g-2 small">
                <div class="col-6">Hazard: <strong>{{ $mine['hazard'] }} / {{ $mine['target_hazard'] }}</strong> @if ($mine['hazard'] < $mine['target_hazard'])<a href="{{ route('ssap.hazards.create') }}">Lapor sekarang</a>@endif</div>
                <div class="col-6">Inspeksi: <strong>{{ $mine['inspection'] }} / {{ $mine['target_inspection'] }}</strong> @if ($mine['inspection'] < $mine['target_inspection'])<a href="{{ route('ssap.inspections.create') }}">Isi inspeksi</a>@endif</div>
            </div>
        </div>
    </div>
@elseif ($mode !== 'self')
    <div class="alert alert-secondary small"><i class="bi bi-info-circle me-1"></i>Grade {{ $scope['grade'] ?? '-' }} tidak punya target pribadi di Pengaturan; yang tampil adalah target {{ $mode === 'all' ? 'seluruh departemen' : 'tim Anda' }}.</div>
@elseif ($mode === 'self')
    <div class="alert alert-secondary small">Grade Anda di HRIS belum terbaca atau grade tersebut belum punya target di Pengaturan, jadi tidak ada target untuk Anda.</div>
@endif

@if ($mode !== 'self')
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><x-kpi label="Karyawan bertarget" :value="$summary['members']" icon="bi-people" tone="primary" sub="Grade bertarget" /></div>
        <div class="col-6 col-md-3"><x-kpi label="Target tercapai" :value="$summary['achieved']" icon="bi-check2-circle" tone="success" :sub="($summary['pct'] ?? '-').'% dari karyawan'" /></div>
        <div class="col-6 col-md-3"><x-kpi label="Belum tercapai" :value="$summary['behind']" icon="bi-hourglass-split" :tone="$summary['behind'] ? 'danger' : 'success'" :sub="$daysLeft !== null ? 'Periode berakhir '.$daysLeft.' hari lagi' : 'Periode sudah lewat'" :href="route('ssap.targets', ['date' => $filters['date'], 'department' => $filters['department'], 'only' => 'behind'])" /></div>
        <div class="col-6 col-md-3"><x-kpi label="Capaian" :value="($summary['pct'] ?? '-').'%'" icon="bi-bullseye" :tone="$pctTone($summary['pct'])" sub="Karyawan yang mencapai target" /></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 {{ $mode === 'all' && $departmentRows ? 'col-xl-5' : '' }}">
            <div class="s-card h-100">
                <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-bar-chart"></i>{{ $charts['target']['by_department'] ? 'Target vs realisasi per departemen' : 'Capaian per anggota' }}</h3><span class="s-card-sub">% dari target periode (tanggal acuan {{ $ref->translatedFormat('d M Y') }})</span></div>
                <div class="s-card-body"><div class="chart-box tall"><canvas id="cvTarget"></canvas></div></div>
            </div>
        </div>
        @if ($mode === 'all' && $departmentRows)
            <div class="col-12 col-xl-7">
                <div class="s-card h-100">
                    <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-diagram-3"></i>Target per departemen</h3><span class="s-card-sub">Total target vs realisasi periode berjalan</span></div>
                    <div class="table-responsive"><table class="table table-ssap mb-0">
                        <thead><tr><th>Departemen</th><th class="num">Orang</th><th class="num">Hazard<br><small class="fw-normal">real / target</small></th><th class="num">Inspeksi<br><small class="fw-normal">real / target</small></th><th class="num">Tercapai</th><th class="num">Capaian</th></tr></thead>
                        <tbody>@foreach ($departmentRows as $d)
                            <tr class="row-link" data-href="{{ route('ssap.targets', ['date' => $filters['date'], 'department' => $d['name'] === 'Tanpa departemen' ? '' : $d['name']]) }}">
                                <td class="fw-semibold">{{ $d['name'] }}</td>
                                <td class="num">{{ $d['members'] }}</td>
                                <td class="num">{{ $d['hazard'] }} / {{ $d['target_hazard'] }}</td>
                                <td class="num">{{ $d['target_inspection'] ? $d['inspection'].' / '.$d['target_inspection'] : '-' }}</td>
                                <td class="num">{{ $d['achieved'] }} <span class="text-muted">({{ $d['pct'] }}%)</span>@if ($d['behind'])<div class="small text-danger">{{ $d['behind'] }} belum</div>@endif</td>
                                <td class="num">@if ($d['volume_pct'] !== null)<span class="pill pill-{{ $pctTone($d['volume_pct']) }}">{{ $d['volume_pct'] }}%</span>@else - @endif</td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                </div>
            </div>
        @endif
    </div>

    <div class="s-card mb-3">
        <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-person-lines-fill"></i>{{ $filters['only'] === 'behind' ? 'Anggota yang belum mencapai target' : 'Capaian per anggota' }}@if ($filters['q'] !== '') <span class="fw-normal text-muted">&middot; hasil &ldquo;{{ $filters['q'] }}&rdquo;</span>@endif</h3><span class="s-card-sub">{{ $pager->total() }} orang @if ($pager->hasPages()) &middot; halaman {{ $pager->currentPage() }} dari {{ $pager->lastPage() }}@endif</span></div>
        <div class="table-responsive">
            <table class="table table-ssap table-board table-fit mb-0">
                <thead><tr><th>Karyawan</th><th class="d-none d-md-table-cell">Departemen / atasan</th><th class="num d-none d-md-table-cell">Grade</th><th class="d-none d-md-table-cell">Periode</th><th class="num">Hazard</th><th class="num">Inspeksi</th><th class="num">Capaian</th><th class="d-none d-md-table-cell">Status</th></tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td><div class="fw-semibold">{{ $r['name'] }}</div><div class="small text-muted">{{ $r['nik'] }}{{ $r['position'] ? ' · '.$r['position'] : '' }}</div><div class="d-md-none small text-muted">{{ $r['department'] ?? '-' }} &middot; Grade {{ $r['grade'] }}</div></td>
                        <td class="small d-none d-md-table-cell">{{ $r['department'] ?? '-' }}<div class="text-muted">{{ $r['supervisor_name'] ? 'Atasan: '.$r['supervisor_name'] : '' }}</div></td>
                        <td class="num d-none d-md-table-cell">{{ $r['grade'] }}</td>
                        <td class="small text-nowrap d-none d-md-table-cell">{{ $periodNames[$r['period']] }}<div class="text-muted">{{ $r['period_label'] }}</div></td>
                        <td class="num {{ $r['hazard'] < $r['target_hazard'] ? 'text-danger fw-semibold' : '' }}">{{ $r['hazard'] }} / {{ $r['target_hazard'] }}</td>
                        <td class="num {{ $r['inspection'] < $r['target_inspection'] ? 'text-danger fw-semibold' : '' }}">{{ $r['inspection'] }} / {{ $r['target_inspection'] }}</td>
                        <td class="num"><span class="pill pill-{{ $pctTone($r['pct']) }}">{{ $r['pct'] }}%</span></td>
                        <td class="d-none d-md-table-cell"><span class="pill pill-{{ $r['achieved'] ? 'success' : 'danger' }}">{{ $r['achieved'] ? 'Tercapai' : 'Belum' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty-state py-4"><i class="bi bi-people"></i>{{ $filters['q'] !== '' ? 'Tidak ada anggota yang cocok dengan pencarian.' : ($summary['members'] ? 'Semua anggota sudah mencapai target.' : 'Tidak ada karyawan bertarget yang dapat dilihat. Pastikan HRIS tersambung dan atasan langsung terisi.') }}</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($pager->hasPages())<div class="p-3 border-top">{{ $pager->links() }}</div>@endif
    </div>
@endif
@endsection

@if ($mode !== 'self')
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/ssap-charts.js') }}?v=9"></script>
<script>document.addEventListener('DOMContentLoaded', function () { window.SSAPCharts && window.SSAPCharts(@json($charts)); });</script>
@endpush
@endif
