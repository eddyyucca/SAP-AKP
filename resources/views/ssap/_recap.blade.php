{{-- Rekap kasus per jenis, format laporan kecelakaan A-E. $recap dari SafetyStats::recap() --}}
<table class="table table-sm table-bordered mb-0" style="font-size:.86rem;">
    <tbody>
    @foreach ($recap as $cat)
        <tr><td colspan="3" class="fw-bold" style="background:#f1f5f9;">{{ $cat['code'] }}. {{ $cat['title'] }}{{ $cat['ref'] ? ' ('.$cat['ref'].')' : '' }}</td></tr>
        @foreach ($cat['rows'] as $i => $row)
            <tr><td style="width:36px;" class="text-center">{{ $i + 1 }}</td><td>{{ $row['label'] }}</td><td class="text-center fw-semibold" style="width:90px;">{{ $row['count'] }}</td></tr>
        @endforeach
        @if ($cat['total_label'])
            <tr style="background:#28a8e0;color:#fff;" class="fw-bold"><td colspan="2" class="text-center" style="background:#28a8e0;color:#fff;">{{ $cat['total_label'] }}</td><td class="text-center" style="background:#28a8e0;color:#fff;">{{ $cat['total'] }}</td></tr>
        @endif
    @endforeach
    </tbody>
</table>
