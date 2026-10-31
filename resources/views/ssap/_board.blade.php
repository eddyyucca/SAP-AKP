{{-- Papan akuntabilitas. $rows (SafetyStats::board), $withDept (tampilkan kolom departemen), $empty --}}
@php($pctTone = fn ($v) => $v === null ? 'secondary' : ($v >= 85 ? 'success' : ($v >= 65 ? 'warning' : 'danger')))
<div class="table-responsive">
    <table class="table table-ssap table-board mb-0">
        <thead><tr><th>{{ ($withDept ?? true) ? 'PIC' : 'Departemen PIC' }}</th><th class="num">Ditugaskan</th><th class="num">Terbuka</th><th class="num">Terlambat</th><th class="num">Selesai</th><th class="num">Tepat waktu</th><th class="num">Rata-rata hari</th></tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr>
                <td class="fw-semibold">{{ $r['name'] }}@if (($withDept ?? true) && $r['dept'])<div class="small text-muted fw-normal">{{ $r['dept'] }}</div>@endif</td>
                <td class="num">{{ $r['assigned'] }}</td>
                <td class="num">{{ $r['open'] ?: '-' }}</td>
                <td class="num">{!! $r['overdue'] ? '<span class="pill pill-danger">'.$r['overdue'].'</span>' : '-' !!}</td>
                <td class="num">{{ $r['closed'] }}</td>
                <td class="num">@if ($r['sla_pct'] !== null)<span class="pill pill-{{ $pctTone($r['sla_pct']) }}">{{ $r['sla_pct'] }}%</span>@else - @endif</td>
                <td class="num">{{ $r['avg_days'] !== null ? number_format($r['avg_days'], 1, ',', '.') : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty-state py-4"><i class="bi bi-person-check"></i>{{ $empty ?? 'Belum ada hazard yang ditugaskan pada periode ini.' }}</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
