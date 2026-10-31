@extends('layouts.app')

@section('title', 'Pengaturan')
@section('page-title', 'Pengaturan & Integrasi')
@section('breadcrumb')
    <li class="breadcrumb-item active">Pengaturan</li>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="s-card mb-3">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-bell"></i>Penerima notifikasi email</h3></div>
            <div class="s-card-body">
                <form method="POST" action="{{ route('ssap.settings.update') }}">
                    @csrf @method('PUT')
                    <p class="small text-muted">Isi alamat email dipisah koma atau baris baru. Pelapor, investigator, dan PIC otomatis menerima email sesuai perannya (alamat diambil dari HRIS).</p>
                    @foreach (['notify_hse' => ['Tim HSE', 'Menerima setiap hazard baru, penutupan hazard, dan ringkasan harian.'] ] as $key => [$label, $hint])
                        <div class="mb-3">
                            <label class="form-label fw-semibold small mb-1" for="{{ $key }}">{{ $label }}</label>
                            <textarea name="{{ $key }}" id="{{ $key }}" rows="2" class="form-control form-control-sm @error($key) is-invalid @enderror" placeholder="nama@akp.co.id, ...">{{ old($key, $emails[$key]) }}</textarea>
                            <div class="form-text">{!! $hint !!}</div>
                            @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="notify_reporter" value="1" id="notify_reporter" @checked(old('notify_reporter', $notifyReporter))>
                        <label class="form-check-label small" for="notify_reporter">Kirim email konfirmasi &amp; kabar perkembangan ke pelapor</label>
                    </div>
                    <div class="mb-3">
                        <div class="form-label fw-semibold small mb-1">Target pelaporan menurut grade HRIS</div>
                        <div class="table-responsive"><table class="table table-sm table-ssap mb-1">
                            <thead><tr><th>Grade</th><th>Periode</th><th class="num">Hazard</th><th class="num">Inspeksi</th></tr></thead>
                            <tbody>
                            @foreach ($targets as $g => $t)
                                <tr><td class="align-middle text-nowrap">Grade {{ $g }}<div class="small text-muted">{{ isset($gradeCounts[$g]) ? ($gradeCounts[$g]['category'] ? $gradeCounts[$g]['category'].' · ' : '').$gradeCounts[$g]['count'].' karyawan' : 'belum ada di HRIS' }}</div></td>
                                    <td><select name="target_period[{{ $g }}]" class="form-select form-select-sm">@foreach (\App\Services\TargetStats::PERIODS as $k => $l)<option value="{{ $k }}" @selected(old('target_period.'.$g, $t['period']) === $k)>{{ $l }}</option>@endforeach</select></td>
                                    <td><input type="number" min="0" max="50" name="target_hazard[{{ $g }}]" value="{{ old('target_hazard.'.$g, $t['hazard']) }}" class="form-control form-control-sm text-end" style="min-width:70px;" required></td>
                                    <td><input type="number" min="0" max="50" name="target_inspection[{{ $g }}]" value="{{ old('target_inspection.'.$g, $t['inspection']) }}" class="form-control form-control-sm text-end" style="min-width:70px;" required></td></tr>
                            @endforeach
                            </tbody>
                        </table></div>
                        <div class="form-text">Contoh: grade 3, per minggu, hazard 2 = tiap karyawan grade 3 harus membuat 2 laporan hazard dalam seminggu (Senin-Minggu). Isi 0 dan 0 bila grade tidak punya target. Daftar grade diambil dari HRIS. Grade 5 melihat capaian timnya; grade 6 ke atas melihat seluruh departemen. Hazard = laporan yang dibuat sendiri (temuan otomatis inspeksi tidak dihitung).</div>
                        @error('target_hazard.*')<div class="text-danger small">{{ $message }}</div>@enderror
                        @error('target_inspection.*')<div class="text-danger small">{{ $message }}</div>@enderror
                        @error('target_period.*')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3" style="max-width:280px;">
                        <label class="form-label fw-semibold small mb-1" for="remind_days_before">Pengingat tindakan (hari sebelum tenggat)</label>
                        <input type="number" min="0" max="14" name="remind_days_before" id="remind_days_before" value="{{ old('remind_days_before', $remindDays) }}" class="form-control form-control-sm">
                        <div class="form-text">Pengingat dikirim tiap pagi 07:00 hingga tindakan selesai.</div>
                    </div>
                    <button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Simpan pengaturan</button>
                </form>
            </div>
        </div>

        <div class="s-card">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-envelope-paper"></i>Email terakhir</h3>
                <form method="POST" action="{{ route('ssap.settings.refresh-mail') }}">@csrf<button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Perbarui status</button></form>
            </div>
            <div class="table-responsive">
                <table class="table table-ssap mb-0">
                    <thead><tr><th>Waktu</th><th>Penerima &amp; subjek</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap small">{{ $log->created_at->translatedFormat('d M H:i') }}</td>
                            <td><div class="small fw-semibold">{{ \Illuminate\Support\Str::limit($log->subject, 56) }}</div><div class="small text-muted">{{ \Illuminate\Support\Str::limit($log->to, 50) }}</div>@if ($log->error)<div class="small text-danger">{{ \Illuminate\Support\Str::limit($log->error, 90) }}</div>@endif</td>
                            <td><span class="pill pill-{{ ['sent' => 'success', 'queued' => 'info', 'failed' => 'danger', 'pending' => 'secondary'][$log->status] ?? 'secondary' }}">{{ ['sent' => 'Terkirim', 'queued' => 'Antri di AKP Mail', 'failed' => 'Gagal', 'pending' => 'Menunggu'][$log->status] ?? $log->status }}</span></td>
                            <td>@if ($log->status === 'failed')<form method="POST" action="{{ route('ssap.settings.resend-mail', $log) }}">@csrf<button class="btn btn-sm btn-outline-primary">Kirim ulang</button></form>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty-state py-4"><i class="bi bi-envelope"></i>Belum ada email.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="s-card mb-3">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-person-vcard"></i>Sinkron HRIS (data karyawan)</h3>
                <span class="pill pill-{{ $hrisConfigured ? 'success' : 'warning' }}">{{ $hrisConfigured ? 'Dikonfigurasi' : 'Belum dikonfigurasi' }}</span></div>
            <div class="s-card-body small">
                <p>Karyawan dibaca langsung dari HRIS lewat API baca-saja (<code>HRIS_API_URL</code>, <code>HRIS_API_TOKEN</code>), tanpa menyalin tabel karyawan. Dipakai untuk pencarian pelapor, PIC, investigator, dan korban. Disegarkan otomatis tiap jam.</p>
                @if ($hrisOverview['total'])
                    <div class="row g-2 mb-2 text-center">
                        <div class="col-3"><div class="fw-bold fs-5">{{ number_format($hrisOverview['total'], 0, ',', '.') }}</div><div class="text-muted">Karyawan aktif</div></div>
                        <div class="col-3"><div class="fw-bold fs-5">{{ count($hrisOverview['departments']) }}</div><div class="text-muted">Departemen</div></div>
                        <div class="col-3"><div class="fw-bold fs-5">{{ count($hrisOverview['positions']) }}</div><div class="text-muted">Jabatan</div></div>
                        <div class="col-3"><div class="fw-bold fs-5">{{ count($gradeCounts) }}</div><div class="text-muted">Grade</div></div>
                    </div>
                    <div class="mb-2">Grade terbaca: <strong>{{ $hrisOverview['with_grade'] }}</strong> karyawan &middot; atasan langsung terisi: <strong>{{ $hrisOverview['with_supervisor'] }}</strong>.@if (! $hrisOverview['with_grade']) <span class="text-danger">HRIS belum mengirim grade; pasang ulang HRIS versi terbaru.</span>@endif</div>
                    <details class="mb-2"><summary class="text-primary" style="cursor:pointer;">Departemen &amp; jabatan dari HRIS</summary>
                        <div class="mt-2"><strong>Departemen</strong><div class="d-flex flex-wrap gap-1 mt-1">@foreach (array_slice($hrisOverview['departments'], 0, 30, true) as $name => $n)<span class="pill pill-secondary">{{ $name }} ({{ $n }})</span>@endforeach</div></div>
                        <div class="mt-2"><strong>Jabatan terbanyak</strong><div class="d-flex flex-wrap gap-1 mt-1">@foreach (array_slice($hrisOverview['positions'], 0, 20, true) as $name => $n)<span class="pill pill-secondary">{{ $name }} ({{ $n }})</span>@endforeach</div></div>
                    </details>
                @endif
                <div class="text-muted mb-2">Sinkron terakhir: {{ $hrisSyncedAt ? \Illuminate\Support\Carbon::parse($hrisSyncedAt)->translatedFormat('d M Y H:i') : 'belum pernah' }}</div>
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('ssap.settings.test-hris') }}">@csrf<button class="btn btn-outline-primary btn-sm"><i class="bi bi-plug me-1"></i>Tes koneksi</button></form>
                    <form method="POST" action="{{ route('ssap.settings.sync-hris') }}">@csrf<button class="btn btn-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Sinkronkan sekarang</button></form>
                </div>
            </div>
        </div>
        <div class="s-card mb-3">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-envelope-at"></i>AKP Mail (email)</h3>
                <span class="pill pill-{{ $mailConfigured ? 'success' : 'warning' }}">{{ $mailConfigured ? 'Dikonfigurasi' : 'Belum dikonfigurasi' }}</span></div>
            <div class="s-card-body small">
                <p>Email dikirim lewat layanan AKP Mail (<code>MAILSVC_URL</code>, <code>MAILSVC_TOKEN</code>). Tanpa token, email memakai pengaturan <code>MAIL_*</code> Laravel (di komputer lokal hanya dicatat ke log).</p>
                <form method="POST" action="{{ route('ssap.settings.test-mail') }}">@csrf<button class="btn btn-outline-primary btn-sm"><i class="bi bi-send me-1"></i>Kirim email uji ke saya</button></form>
            </div>
        </div>
        <div class="s-card">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-box-arrow-in-right"></i>Portal AKPSatu</h3></div>
            <div class="s-card-body small">
                <p class="mb-1">Login &amp; level akses dikelola di Portal: <a href="{{ $portalUrl }}" target="_blank" rel="noopener">{{ $portalUrl }}</a></p>
                <p class="mb-0 text-muted">Level SAP Safety: Administrator, Petugas HSE, Manajer, Pelapor. Beri akses di Portal &gt; Pengguna &amp; Akses.</p>
            </div>
        </div>
    </div>
</div>
@endsection
